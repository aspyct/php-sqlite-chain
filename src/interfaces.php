<?php
interface Statement {
    function getSql() : string;
    function getParameters() : array;
}

interface Instruction {
    function getStatements() : array;
    function toJson() : string;
}

interface Choreographer {
    /**
     * Runs an instruction, possibly updating from the next node.
     * Returns the total number of instructions executed, including updates fetched from the next node.
     * 
     * @throw IntegrityError
     */
    function runInstruction(Instruction $instruction) : int;

    /**
     * Returns all the instructions for which the sequence number > $lastKnownSequenceNumber
     */
    function getInstructionsSince(int $lastKnownSequenceNumber) : array;
}

interface NextNode {
    /**
     * Execute the new instruction, and then return an array containing
     * all the instructions executed since $lastKnownSequenceNumber, including
     * this new instruction.
     * 
     * The keys of the array are the sequence numbers, the values are instructions.
     * 
     * @throw CantRunInstructionException Could not run one of the statements in the instruction
     * @throw UnsupportedOperationException if the node can't provide missing instructions since $lastKnownSequenceNumber
     * @throw NodeDownException if the node is unreachable or returns a 5xx
     * @throw UnknownError for other errors
     */
    function runInstruction(int $lastKnownSequenceNumber, Instruction $newInstruction) : array;
}

interface Database {
    /**
     * Prevent any other process from updating the database.
     * Release with validateChanges() or cancelChanges()
     */
    function beginTransaction() : void;

    function commit() : void;
    function rollback() : void;

    /**
     * Run the given instruction, and record it in the instruction log with the correct sequence number.
     */
    function runInstruction(int $sequenceNumber, Instruction $instruction) : bool;

    /**
     * Returns the last known sequence number
     */
    function getLastSequenceNumber() : int;

    /**
     * Returns a list of the instructions executed after $lastKnownSequenceNumber
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
    function handleRequest(array $get, array $post, array $server) : void;
}
