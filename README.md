# PHP SQLite Chain

A distributed sqlite database implementation for php.

- Highly available read: data can be read locally even if other hosts are down. Data is replicated fully on each link.
- Transactions are run strictly in the same order on each link.
- Writes should be done on the head link, but can be sent to any link if parts of the chain are down. Recovery is possible later.

Think of it as a daisy-chain of sqlite databases. Each server (called `link` hereafter) will wait for his next link to validate the transaction before committing locally. At any time, there is at most 1 instruction executed on the tail link but not yet on the head link.

This system can be deployed on any PHP server with SQLite support.

Important note: the system relies on write requests to recover from unexpected crashes in the chain. For example, if your head link fails after the tail link committed an instruction, but before it could commit its own transaction (on the head link), the tail link will have different data until a recovery is performed. For this reason, you should monitor unexpected process/server crashes and do a no-op write (instruction with no statement) after that. A cron can also be considered.

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
- The head link assigns a sequence number to that instruction, and transmits it to the end of the chain
- The tail link has the final decision on whether an instruction can be played or not.
- Any link in the chain can decide to fail the instruction because of an inconsistent state
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

## Scenarios

### Happy path with 2 links

1.  head link receives instruction
2.  head link starts a transaction locally
3.  head link inserts instruction into the log and gets its sequence number
4.  head link runs instruction locally
4.  head link sends instruction and sequence number to its next link
5.  next link is the tail link
6.  tail link starts a transaction locally
7.  tail link inserts instruction into log
8.  tail link runs the instruction locally
9.  tail link commits the transaction
10. tail link sends OK to previous link
11. previous link is head link
12. head link commits the transaction
13. success

### Instruction cannot run because of constraint/sql error

1. head link receives instruction
2. head link starts a transaction locally
3. 

### Next link is down

1. head link receives instruction
2. head link starts a transaction locally
3. head link inserts instruction into the log and gets its sequence number
4. head link sends instruction and sequence number to its next link
5. next link is down
6. head link rolls back the transaction
7. error: link down (retry later)

### Head link dies after next link validates, but before head commits

1.  head link receives instruction #1
2.  head link starts a transaction locally
3.  head link inserts instruction #1 into the log and gets its sequence number
4.  head link sends instruction #1 and sequence number to its next link
5.  next link is the tail link
6.  tail link starts a transaction locally
7.  tail link inserts instruction #1 into log
8.  tail link runs instruction #1
9.  tail link commits the transaction
10. tail link sends OK to previous link
11. previous link is head link
12. head link unexpectedly died

Situation: instruction has been executed on tail link, but there's no trace of it on the head link.
The situation will be resolved when the head link receives another instruction.

13. head link receives instruction #2
14. head link starts a transaction locally
15. head link inserts instruction #2 into the log and gets its sequence number
16. head link sends instruction #2 and sequence number to its next link
17. next link is the tail link
18. tail link starts a transaction locally
19. tail link fails to insert sequence number because of UNIQUE constraint
20. tail link sends back instruction #1 to previous link
21. previous link is head link
22. head link updates local instruction log to include instruction #1 at the right sequence number
23. head link runs instruction #1
24. head link commits transaction
25. error: recovery happened (retry now)

It is the responsibility of the client to retry the instruction to the head server.
Note that other concurrent instructions may have been executed before you have a chance to retry.

### Concurrent submissions to head link

Step 3 must be blocking to safeguard against concurrency issues.

Due to SQLite3 internal locking mechanism, this will be ensured by using transactions and autoincrement primary keys.

Consider the following DDL:

```
create table instruction (
    sequence_number integer primary key autoincrement,
    statements text
);
```


