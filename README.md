# PHP SQLite Chain

A distributed sqlite database implementation for php.

## Overview

- Strict transaction ordering on all links
- Highly available read: data can be read locally even if other hosts are down. Data is replicated fully on each link.
- Writes propagate through the whole chain before returning
- Unexpected crashes causing a partial write can be recovered.

Think of it as a daisy-chain of sqlite databases. Each server (called `link` hereafter) will wait for his next link to validate the transaction before committing locally.

Important note: the system relies on write requests to recover from unexpected crashes in the chain. For example, if your head link fails after the tail link committed an instruction, but before it could commit its own transaction (on the head link), the tail link will have different data until a recovery is performed. For this reason, you should monitor unexpected process/server crashes and do a no-op write (instruction with no statement) after that. A cron can also be considered.

## Requirements

Any PHP server with SQLite3 and `allow_url_fopen = 1` will work.

## Use cases

- Multi-region HA websites
- Live backup
- Point-in-time recovery

## Summary

The system's basic principles are relatively simple, and based on recursion.

For each (sequence_number, instruction) received, do:

1. If sequence_number exists in instruction log, return the corresponding instruction from the log.
2. Run the instruction locally. If it fails, return the error
3. Send (sequence_number, instruction) to link N+1 if any
4. If N+1 returned an existing instruction for that sequence number, roll back step #2 and play that instruction instead. Return that instruction

This has the following practical implications:

- You can technically send your instructions to any link in the chain, but uplinks will not be aware of the changes until they try to write as well.
- If a link is down in the chain, it is possible to write to links further down the chain, but not up the chain.
- Conversely, if the tail link is down, it is not possible to write to the chain.
- Any link in the chain can decide to fail the instruction because of an inconsistent state.
- A link will refuse to execute an instruction with a reused sequence number. Previous links in the chain are responsible for recovering.

## Terminology

link
: One of the servers of the sqlite chain.

next link
: From the perspective of a link, the next link to which the transactions should be sent. A link must wait for his next link to validate before the transaction can be committed.

head link
: The first link in the sqlite chain. No other link references to this one as his "next link". New transactions must be initiated from this link.

tail link
: A link that has no "next link".

write link
: The link to which you sent the instruction

instruction
: One or more SQL statements that must be executed within a transaction.

instruction sequence number
: The unique, monotonically increasing, no-gap sequence number of th

instruction log
: A simple log of executed (sequence_number, instruction)

## Concurrent submissions to the chain

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

This is done by keeping a transaction open until the N+1 link itself completes the transaction. For example, here's the transactions for a 3-link chain:

```
head link opens transaction
    mid link opens transaction
        tail link opens transaction
        tail link commits
    mid link commits
head link commits
```

The chain is always traversed in the same order, which prevents deadlocks.

It could also happen that two updates are triggered from different levels of the chain. For example, one update sent to the head link, and another update sent to the mid link.

Consider an empty database. Concurrent updates are sent to head (update A) and mid link (update B). Because the database is empty, head link will assign sequence_number 1 to update A. It then forwards the instruction to mid link with that sequence_number.

One of two things can happen:

1. mid link hasn't assigned a sequence_number to update B yet. It will have to wait until update A finishes.
2. mid link has already assigned sequence_number 1 to update B. If update B finishes successfully, sequence_number 1 will no longer be available, and mid will return update B to the head link. If update B fails, sequence_number 1 will be available, and update A will continue normally.