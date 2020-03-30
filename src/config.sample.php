<?php
define('DATABASE_PATH', $_ENV['SQLITE_TREE_DATABASE_FILE']);
define('NEXT_NODE', $_ENV['SQLITE_TREE_NEXT_NODE']);

$database = new InstructionLogDatabase(DATABASE_PATH);
$nextNode = new NoNextNode($database);
$choreographer = new StandardChoreographer($database, $nextNode);
$api = new JsonPublicApi($choreographer);

return $api;
