<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('/(\.db)|scripts/', $uri)) {
    http_response_code(404);
    echo "Not found";
    return true;
}

return false;
