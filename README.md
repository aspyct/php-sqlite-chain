# PHP SQLite Tree

A distributed sqlite database implementation for php.

This database offers various levels of consistency and availability depending on your configuration.

## Overview

- Strict transaction ordering on all nodes (depending on the chosen Choreographer).
- Highly available read: data can be read locally even if other hosts are down. Data is replicated fully on each node.
- Writes propagate through the whole chain before returning
- Unexpected crashes causing a partial write can be recovered.

Think of it as a daisy-chain of sqlite databases. Each server (called `node` hereafter) will wait for his next node to validate the transaction before committing locally.

Important note: the system relies on write requests to recover from unexpected crashes in the chain. For example, if your leaf node fails after the root node committed an instruction, but before it could commit its own transaction (on the leaf node), the root node will have different data until a recovery is performed. For this reason, you should monitor unexpected process/server crashes and do a no-op write (instruction with no statement) after that. A cron can also be considered.

## Use cases

- Multi-region HA websites
- Redundant storage for critical data
- Live backup
- Deferred backup
- Point-in-time recovery

## Requirements

Any PHP server with SQLite3 and `allow_url_fopen = 1` will work. It is tested with PHP 7.3, but no bleeding edge feature is used, so should work in older versions as well.

## License: Public Domain

Like SQLite, this library is public domain.

If that's annoying for legal reasons, you can also get it with MIT, BSD or GPLv3 license. Please open an issue to discuss this matter.

## Terminology

node
: One of the servers of the sqlite chain.

next node
: From the perspective of a specific node, the next node to which the transactions should be sent. A node must wait for his next node to validate before the transaction can be committed.

leaf node
: A node to which no other node is referring to as its "next node"

root node
: A node that has no "next node".

write node
: The node to which you sent the instruction

downstream nodes
: From the perspective of a specific node, downsteam nodes include the next node and all its downstream nodes.

upstream nodes
: From the perspective of a specific node, upstream nodes include all nodes that refer to this particular node as their "next node".

instruction
: One or more SQL statements that must be executed within a transaction.

instruction sequence number
: The unique, monotonically increasing, no-gap sequence number of th

instruction log
: A simple log of executed (sequence_number, instruction)

## Configuration

All nodes in the cluster should have the same choreographer.

Chose your database type based on the choreographer you have.
Choreographers may also enforce requirements on the RemoteNodes.

## Mechanics overview (Chain Choreographer)

The system's basic principles are relatively simple, and based on recursion.

```
Open a transaction, play changes locally, send changes to N+1, commit transaction.
```

More specifically, for each (sequence_number, instruction) received, do:

1. If sequence_number exists in instruction log, return the corresponding instruction from the log.
2. Run the instruction locally. If it fails, return the error
3. Send (sequence_number, instruction) to node N+1 if any
4. If N+1 returned an existing instruction for that sequence number, roll back step #2 and play that instruction instead. Return that instruction

This has the following practical implications:

- You can technically send your instructions to any node in the chain, but upnodes will not be aware of the changes until they try to write as well.
- If a node is down in the chain, it is possible to write to downstream nodes, but not upstream nodes.
- Conversely, if the root node is down, it is not possible to write to the chain.
- Any node in the chain can decide to fail the instruction because of an inconsistent state.
- A node will refuse to execute an instruction with a reused sequence number. Previous nodes in the chain are responsible for recovering.

### Concurrent submissions to the chain

SQLite3 uses an internal locking mechanism to make sure that only one connection writes to the database at the same time.

Consider the following DDL:

```
create table instruction_log (
    sequence_number integer primary key autoincrement,
    statements text
);
```

Client 1:

```
begin transaction;
insert into instruction_log (statements) values ('...');
```

Client 2:

```
begin transaction;

-- The insert will hang until client 1 terminates the transaction.
insert into instruction_log (statements) values ('...');
```

We use this property to ensure that each instruction has a unique sequence number across the whole chain.

This is done by keeping a transaction open until the N+1 node itself completes the transaction. For example, here's the transactions for a 3-node chain:

```
leaf node opens transaction
    mid node opens transaction
        root node opens transaction
        root node commits
    mid node commits
leaf node commits
```

The chain is always traversed in the same order, which prevents deadlocks.

It could also happen that two updates are triggered from different levels of the chain. For example, one update sent to the leaf node, and another update sent to the mid node.

Consider an empty database. Concurrent updates are sent to head (update A) and mid node (update B). Because the database is empty, leaf node will assign sequence_number 1 to update A. It then forwards the instruction to mid node with that sequence_number.

One of two things can happen:

1. mid node hasn't assigned a sequence_number to update B yet. It will have to wait until update A finishes.
2. mid node has already assigned sequence_number 1 to update B. If update B finishes successfully, sequence_number 1 will no longer be available, and mid will return update B to the leaf node. If update B fails, sequence_number 1 will be available, and update A will continue normally.
