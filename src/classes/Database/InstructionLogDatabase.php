<?php
/**
 * The standard synchronizable database.
 * It records every instruction it ran, and can
 * also extract the history for upstream nodes that need to be updated.
 */
class InstructionLogDatabase implements Database {
    const DEFAULT_SEQUENCE_NUMBER = 0;

    /**
     * @property string
     */
    private $path;

    /**
     * @property SQLite3
     */
    private $handle;

    public function __construct(string $path) {
        $this->path = $path;
    }

    function beginTransaction() : void {
        /**
         * BEGIN IMMEDIATE ensures that no other process is writing to the database.
         * See https://www.sqlite.org/lang_transaction.html
         */
        $this->getHandle()->exec('begin immediate');
    }

    function commit() : void {
        $this->getHandle()->exec('commit');
    }

    function rollback() : void {
        $this->getHandle()->exec('rollback');
    }

    function runInstruction(int $sequenceNumber, Instruction $instruction) : bool {
        // First, insert the instruction into the log
        $insertLog = $this->getHandle()->prepare('insert into instruction_log (sequence_number, instruction) values (:sequence_number, :instruction');
        $insertLog->bindValue(':sequence_number', $sequenceNumber);
        $insertLog->bindValue(':instruction', $instruction->toJson());
        $insertLog->execute();

        // Then, run the statements in the instruction
        foreach ($instruction->getStatements() as $statement) {
            $preparedStatement = $this->getHandle()->prepare($statement->getSql());

            foreach ($statement->getParameters() as $name => $value) {
                $preparedStatement->bindValue($name, $value);
            }

            $preparedStatement->execute();
        }
    }

    function getLastSequenceNumber() : int {
        $lastOrNull = $this->getHandle()->querySingle('select max(sequence_number) from instruction_log');

        return $lastOrNull ?? self::DEFAULT_SEQUENCE_NUMBER;
    }

    private function getHandle() : SQLite3 {
        if ($this->handle === null) {
            $this->handle = new SQLite3($this->path);
        }

        return $this->handle;
    }
}
