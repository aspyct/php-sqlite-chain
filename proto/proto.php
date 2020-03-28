<?php
$database_file = $_ENV['DATABASE'];
$next_link = $_ENV['NEXT_LINK'];
$instruction = $_POST['statement'];
$sequence_number = $_POST['sequence_number'];

$db = new SQLite3($database_file);
$db->exec('begin transaction');

if ($sequence_number === null) {
    $insert = $db->prepare('insert into instruction_log (statements) values (:statement)');
}
else {
    $insert = $db->prepare('insert into instruction_log (sequence_number, statements) values (:sequence_number, :statement)');
    $insert->bindValue(':sequence_number', $sequence_number);
}

$insert->bindValue(':statement', $instruction);

# TODO Handle insert failure because sequence number already exists
if (!$insert->execute()) {
    $db->exec('rollback'); # TODO Is this necessary?

    die(json_encode([
        "error" => [
            "code" => 2,
            "message" => "Could not insert instruction into log",
            "sql_code" => $db->lastErrorCode(),
            "sql_message" => $db->lastErrorMsg()
        ]
    ]));
}

if ($sequence_number === null) {
    $sequence_number = $db->lastInsertRowID();
}

$db->exec('savepoint before_data_update');
$db->exec($instruction);

if ($next_link !== null) {
    $data = array('statement' => $instruction, 'sequence_numben' => $sequence_number);

    // use key 'http' even if you send the request to https://...
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        )
    );
    $context  = stream_context_create($options);
    $result = file_get_contents($next_link, false, $context);

    if ($result === false) {
        $db->exec('rollback');

        die(json_encode([
            "error" => [
                "code" => 1,
                "message" => "Could not contact next link",
                "next_link" => $next_link
            ]
        ]));
    }

    var_dump($result);

    $result = json_decode($result);

    if (isset($result['error'])) {
        if ($result['error']['code'] === 1) {
            $db->exec('rollback');

            die(json_encode($result));
        }
    }
}

$db->exec('commit');
