<?php
header('Content-Type: application/json; charset=utf-8');

if ($q === '') {
    http_response_code(400);
    echo json_encode(['error' => 'word must not be empty'], JSON_UNESCAPED_UNICODE);
    exit;
}

$results = lookup($q, $db);

if (!$results) {
    http_response_code(404);
    echo json_encode(['error' => "\"$q\" is not in the dictionary."], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
