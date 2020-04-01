<?php declare(strict_types=1);
function array_get($key, array $array, $default=null) {
    if (array_key_exists($key, $array)) {
        return $array[$key];
    }
    else {
        return $default;
    }
}

function uuidv4() : string {
    $data = random_bytes(16);

    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
