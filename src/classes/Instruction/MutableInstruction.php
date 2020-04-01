<?php
class MutableInstruction implements Instruction {
    private $statements = [];

    public function addStatement(Statement $statement) {
        $this->statements[] = $statement;
    }

    function getStatements() : array {
        return $this->statements;
    }

    function hash() : string {
        throw new NotImplementedException();
    }
}
