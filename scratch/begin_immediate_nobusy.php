<?php
$db = new SQLite3('test.db');
$db->busyTimeout(0);

if ($db->exec('begin immediate')) {
    echo "Lock acquired\n";
    $db->busyTimeout(60000);
    
    $statement = $db->prepare('insert into lotsofvalues (value) values (:value)');

    for ($i = 0; $i < 100; ++$i) {
        $statement->bindValue(':value', $i);
        $statement->execute();
    }

    $db->exec('commit');
}
else {
    echo "Could not acquire lock\n";
}

