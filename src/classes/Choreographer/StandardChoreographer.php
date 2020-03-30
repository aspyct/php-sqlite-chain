<?php
class StandardChoreographer implements Choreographer {
    /**
     * @property Database
     */
    private $database;

    /**
     * @property NextNode
     */
    private $nextNode;

    public function runInstruction(Instruction $newInstruction) : int {
        $this->database->beginTransaction();
        
        try {
            $lastKnownSequenceNumber = $this->database->getLastSequenceNumber();
            $instructionList = $this->nextNode->runInstruction($lastKnownSequenceNumber, $newInstruction);

            foreach ($instructionList as $sequenceNumber => $instruction) {
                $this->database->runInstruction($sequenceNumber, $instruction);
            }

            $this->database->commit();
            return count($instructionList);
        }
        catch (Exception $e) {
            $this->database->rollback();
            throw $e;
        }
    }

    public function getInstructionListSince(int $lastKnownSequenceNumber) : array {
        return $this->database->getInstructionListSince($lastKnownSequenceNumber);
    }
}
