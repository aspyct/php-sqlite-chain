<?php
class MutableInstruction implements Instruction {
    private $statements = [];

    public function addStatement(Statement $statement) {
        $this->statements[] = $statement;
    }

    function getStatements() : array {
        return $this->statements;
    }

    function toJson() : string {
        return json_encode($this->statements);
    }
}
