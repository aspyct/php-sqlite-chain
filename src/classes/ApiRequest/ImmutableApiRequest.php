<?php
class ImmutableApiRequest implements ApiRequest {
    private $lastKnownSequenceNumber;
    private $instruction;

    public function __construct(int $lastKnownSequenceNumber, Instruction $instruction) {
        $this->lastKnownSequenceNumber = $lastKnownSequenceNumber;
        $this->instruction = $instruction;
    }

    public function getLastKnownSequenceNumber() : int {
        return $this->lastKnownSequenceNumber;
    }

    public function getInstruction() : Instruction {
        return $this->instruction;
    }
}
