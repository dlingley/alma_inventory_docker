<?php
header('Content-Type: application/json');

$progress_id = isset($_GET['id']) ? preg_replace('/[^a-zA-Z0-9_.]/', '', $_GET['id']) : '';
$progress_file = '/tmp/progress_' . $progress_id . '.json';

if ($progress_id && file_exists($progress_file)) {
    $data = json_decode(file_get_contents($progress_file), true);
    $percentage = isset($data['percentage']) ? $data['percentage'] : 0;
    $job = isset($data['job']) ? $data['job'] : '';
} else {
    $percentage = 0;
    $job = '';
}

echo json_encode(array("job" => $job, "percentage" => $percentage));
