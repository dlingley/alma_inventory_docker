<?php
/**
 * History Manager Helper
 * Records, retrieves, and archives inventory run history entries and files in /srv/app/cache/
 */

define('HISTORY_FILE_PATH', __DIR__ . '/cache/run_history.json');
define('UPLOADS_DIR_PATH', __DIR__ . '/cache/uploads');

function loadRunHistory() {
    $path = HISTORY_FILE_PATH;
    if (file_exists($path) && is_readable($path)) {
        $content = file_get_contents($path);
        $data = json_decode($content, true);
        if (is_array($data)) {
            return $data;
        }
    }
    return [];
}

function saveRunHistory($history) {
    $dir = dirname(HISTORY_FILE_PATH);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return file_put_contents(HISTORY_FILE_PATH, json_encode($history, JSON_PRETTY_PRINT)) !== false;
}

function recordRun($data) {
    $history = loadRunHistory();

    $runId = 'run_' . uniqid('', true);
    $runEntry = [
        'id' => $runId,
        'timestamp' => date('c'), // ISO8601
        'formatted_date' => date('M j, Y g:i A'),
        'user' => $data['user'] ?? 'Unknown',
        'library' => $data['library'] ?? '',
        'location' => $data['location'] ?? '',
        'original_filename' => $data['original_filename'] ?? 'uploaded_file.xlsx',
        'upload_file' => $data['upload_file'] ?? '',
        'output_filename' => $data['output_filename'] ?? '',
        'output_file' => $data['output_file'] ?? '',
        'barcode_count' => intval($data['barcode_count'] ?? 0),
        'problem_count' => intval($data['problem_count'] ?? 0)
    ];

    // Insert at beginning so newest runs are first
    array_unshift($history, $runEntry);

    // Keep up to 1000 history entries
    if (count($history) > 1000) {
        $history = array_slice($history, 0, 1000);
    }

    saveRunHistory($history);
    return $runEntry;
}

function getRunHistoryForUser($userIdentifier = null, $isSuperAdmin = false) {
    $all = loadRunHistory();
    $tz = new DateTimeZone(getenv('APP_TIMEZONE') ?: 'America/Indiana/Indianapolis');

    foreach ($all as &$entry) {
        if (!empty($entry['timestamp'])) {
            try {
                $dt = new DateTime($entry['timestamp']);
                $dt->setTimezone($tz);
                $entry['formatted_date'] = $dt->format('M j, Y g:i A');
            } catch (Exception $e) {}
        }
    }
    unset($entry);

    if ($isSuperAdmin || empty($userIdentifier)) {
        return $all;
    }

    $userLower = strtolower(trim($userIdentifier));
    return array_values(array_filter($all, function($entry) use ($userLower) {
        return strtolower(trim($entry['user'] ?? '')) === $userLower;
    }));
}
