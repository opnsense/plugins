<?php

$log_file = '/var/log/nginx/csp_violations.log';

// make sure we don't have any formatting issues here
if (stristr($_SERVER['CONTENT_TYPE'], 'json') === false) {
    http_response_code(400);
    echo "This endpoint expects JSON data. Please send data using a json mime time (for example application/json)";
    exit(0);
}

if ($json_data = json_decode(file_get_contents('php://input'), true)) {
  http_response_code(204);
  // inject some data for a log viewer to get a relation with the server entry
  $json_data['server_time'] = time();
  $json_data['server_uuid'] = $_SERVER['SERVER-UUID'];
  $json_data = json_encode($json_data);
  file_put_contents($log_file, $json_data . PHP_EOL, FILE_APPEND | LOCK_EX);
} else {
    http_response_code(400);
    echo "Your request data cannot be decoded. Please send compliant JSON data.";
    exit(0);
}
