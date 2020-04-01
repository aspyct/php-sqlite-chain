<?php
require_once(__DIR__.'/interfaces.php');
require_once(__DIR__.'/functions.php');

$all_classes = [
    'Choreographer' => [
        'QuorumChoreographer'
    ],
    'Exception' => [
        'PeerLockedException'
    ],
    'Transaction' => [
        'MutableTransaction'
    ]
];

foreach ($all_classes as $interface => $class_list) {
    foreach ($class_list as $class) {
        require_once(__DIR__."/classes/$interface/$class.php");
    }
}
