<?php
require_once('config.php');
require_once('classes.php');

$database = new InstructionLogDatabase(DATABASE_PATH);
$nextNode = new NoNextNode($database);
$choreographer = new StandardChoreographer($database, $nextNode);
$api = new JsonPublicApi($choreographer);

$api->handleRequest();
