<?php
/**
 * A fake next node.
 * It will not execute any instruction, and will return a new sequence number.
 */
class NoRemoteNode implements RemoteNode {
    private $database;

    public function __construct(Database $database) {
        $this->database = $database;
    }

    function runInstruction(int $lastKnownSequenceNumber, Instruction $newInstruction) : array {
        $nextSequenceNumber = $this->database->getLastSequenceNumber() + 1;

        return [$nextSequenceNumber => $instruction];
    }
}
