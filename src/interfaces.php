<?php
interface Statement {
    function getSql() : string;
    function getParameters() : array;
}

interface Instruction {
    function getStatements() : array;
    function hash() : string;
}

interface Peer {
    function getId() : string;
    function getUrl() : string;
}

interface Transaction {
    /**
     * @return Peer a random unmarked peer, or null if no peer is left unmarked.
     */
    function pickRandomUnmarkedPeer() : ?Peer;

    /**
     * @throw InvalidArgumentException if we don't know its sequence number, of if peer doesn't exist
     */
    function getLastSequenceNumber(Peer $peerId) : int;
    function getMostRecentSequenceNumber() : int;
    function getPeersAtSequenceNumber(int $sequenceNumber) : array;

    function markPeerReady(Peer $peer, int $lastKnownSequenceNumber) : void;
    function markPeerLocked(Peer $peer, int $lastKnownSequenceNumber) : void;
    function markPeerDown(Peer $peer) : void;

    function countPeers() : int;
    function countPeersReady() : int;
    function countPeersLocked() : int;
    function countPeersDown() : int;
}

interface TransactionFactory {
    function createFromExistingTransaction(Transaction $transaction);
    function createNewTransaction();
}

interface Choreographer {
    /**
     * Runs an instruction, possibly updating from the next node.
     * Returns the total number of instructions executed, including updates fetched from the next node.
     * 
     * @throw IntegrityError
     */
    function runInstruction(Instruction $instruction, Transaction $transaction = null) : int;

    /**
     * Returns all the instructions for which the sequence number > $lastKnownSequenceNumber
     */
    function getInstructionsSince(int $lastKnownSequenceNumber) : array;
}

interface PeerProvider {
    function getPeer(string $peerId) : Peer;
    function getLocalPeer() : Peer;
    function listAllPeers() : array;
}

interface RunResult {
    function getRecordedInstructions() : array;
}

interface RecordedInstruction {
    function getInstruction() : Instruction;
    function getSequenceNumber() : int;
    function getStateHash() : string;
}

interface RemotePeerClient {
    /**
     * Execute the new instruction, and then return an array containing
     * all the instructions executed since $lastKnownSequenceNumber, including
     * this new instruction.
     * 
     * The keys of the array are the sequence numbers, the values are instructions.
     * 
     * @throw CantRunInstructionException Could not run one of the statements in the instruction
     * @throw UnsupportedOperationException if the node can't provide missing instructions since $lastKnownSequenceNumber
     * @throw PeerDownException if the peer is unreachable or returns a 5xx
     * @throw PeerLockedException if the peer is locked by another instruction
     * @throw TooManyPeersDownException
     * @throw TooManyPeersLockedException
     */
    function runInstruction(string $myPeerId, Peer $remotePeer, Instruction $newInstruction, Transaction $transaction) : RunResult;

    /**
     * @return array<RecordedInstruction>
     * @throw PeerDownException
     * @throw InvalidSequenceNumberException if we don't have any instruction for that sequence number (either it's lost in the past, or too far into the future).
     */
    function getInstructionsSince(Peer $remotePeer, int $sequenceNumber);
}

interface Database {
    /**
     * Prevent any other process from updating the database.
     * 
     * Returns true if the lock was acquired. False otherwise.
     */
    function lock() : bool;

    function commit() : void;
    function rollback() : void;

    /**
     * Run the given instruction, and record it in the instruction log with the correct sequence number.
     * 
     * Returns the current State hash after insertion into the log.
     */
    function runInstruction(int $sequenceNumber, Instruction $instruction) : string;

    /**
     * Returns the last known sequence number and corresponding state hash
     */
    function getLastSequenceNumber() : int;

    /**
     * Returns a list of the instructions executed after $lastKnownSequenceNumber
     * 
     * @return array<RecordedInstruction>
     */
    function getInstructionsSince(int $lastKnownSequenceNumber) : array;
}

interface ApiRequest {
    const DONT_RETURN_INSTRUCTIONS = -1;

    function getInstruction() : Instruction;

    /**
     * -1 means that we are not interested in instruction history.
     * All other values means we need to know the instructions executed after this sequence number.
     */
    function getLastKnownSequenceNumber() : int;
}

interface ApiResponse {
    function listMissingInstructions() : array;
}

interface ApiError {
    function getCode() : int;
    function getMessage() : string;
    function getDetails() : array;
}

interface PublicApi {
    function handleRequest() : void;
}
