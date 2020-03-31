<?php
require_once(__DIR__.'/interfaces.php');

$all_classes = [
    'Choreographer' => [
        'StandardChoreographer'
    ],
    'Database' => [
        'InstructionLogDatabase'
    ],
    'RemoteNode' => [
        'HttpRemoteNode',
        'NoRemoteNode'
    ]
];

foreach ($all_classes as $interface => $class_list) {
    foreach ($class_list as $class) {
        require_once(__DIR__."/$interface/$class.php");
    }
}
