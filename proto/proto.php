<?php
$database_file = $_ENV['DATABASE'];
$next_link = $_ENV['NEXT_LINK'];
$instruction = $_POST['statement'];
$sequence_number = $_POST['sequence_number'];

$db = new SQLite3($database_file);
$db->exec('begin transaction');

if ($sequence_number === null) {
    $insert = $db->prepare('insert into instruction_log (statement) values (:statement)');

    if ($insert === false) {
        die($db->lastErrorMsg());
    }
}
else {
    $insert = $db->prepare('insert into instruction_log (sequence_number, statement) values (:sequence_number, :statement)');
    $insert->bindValue(':sequence_number', $sequence_number);
}

$insert->bindValue(':statement', $instruction);

# TODO Handle insert failure because sequence number already exists
if (!$insert->execute()) {
    $sql_code = $db->lastErrorCode();
    $sql_message = $db->lastErrorMsg();

    $db->exec('rollback');

    if ($sql_code === 19) {
        // Duplicate sequence_number. Return the corresponding instruction
        $select = $db->prepare('select statement from instruction_log where sequence_number = :sequence_number');
        $select->bindValue(':sequence_number', $sequence_number);
        $result = $select->execute();

        $row = $result->fetchArray();
        if ($row !== false) {
            die(json_encode([
                "error" => [
                    "code" => 4,
                    "message" => "That sequence number is already used.",
                    "instruction" => $row[0],
                    "sequence_number" => $sequence_number
                ]
            ]));
        } else {
            die(json_encode([
                "error" => [
                    "code" => 3,
                    "message" => "This sequence number is already taken, but we can't get the corresponding instruction."
                ]
            ]));
        } 
    }

    die(json_encode([
        "error" => [
            "code" => 2,
            "message" => "Could not insert instruction into log",
            "sql_code" => $sql_code,
            "sql_message" => $sql_message
        ]
    ]));
}

if ($sequence_number === null) {
    $sequence_number = $db->lastInsertRowID();
}

$db->exec('savepoint before_data_update');
$db->exec($instruction);

if ($db->lastErrorCode() !== 0) {
    $sql_code = $db->lastErrorCode();
    $sql_message = $db->lastErrorMsg();

    $db->exec("rollback");
    die(json_encode([
        "error" => [
            "code" => 5,
            "message" => "Instruction failed",
            "sql_code" => $sql_code,
            "sql_message" => $sql_message
        ]
    ]));
}

if ($next_link !== null) {
    $data = array('statement' => $instruction, 'sequence_number' => $sequence_number);

    // use key 'http' even if you send the request to https://...
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        )
    );
    $context  = stream_context_create($options);
    $raw_result = file_get_contents($next_link, false, $context);

    if ($raw_result === false) {
        $db->exec('rollback');

        die(json_encode([
            "error" => [
                "code" => 1,
                "message" => "Could not contact next link",
                "next_link" => $next_link
            ]
        ]));
    }

    $result = json_decode($raw_result);

    if (isset($result->error)) {
        $code = $result->error->code;

        if ($code === 4) {
            // Reused sequence number. Abort the current data change and replay given instruction
            $instruction = $result->error->instruction;
            $sequence_number = $result->error->sequence_number;
            $db->exec('rollback to before_data_update');

            $update_log = $db->prepare('update instruction_log set statement = :statement where sequence_number = :sequence_number');
            $update_log->bindValue(':statement', $instruction);
            $update_log->bindValue(':sequence_number', $sequence_number);
            $update_log->execute();

            $db->exec($instruction);

            if ($db->lastErrorCode() !== 0) {
                echo "Errored";
                $sql_code = $db->lastErrorCode();
                $sql_message = $db->lastErrorMsg();

                $db->exec("rollback");
                die(json_encode([
                    "error" => [
                        "code" => 6,
                        "message" => "Cannot recover. Manual intervention is required",
                        "sql_code" => $sql_code,
                        "sql_message" => $sql_message
                    ]
                ]));
            }
            else {
                echo $raw_result;
            }
        }

        if ($code === 1) {
            $db->exec('rollback');

            die($raw_result);
        }
    }
}

$db->exec('commit');
