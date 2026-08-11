<?php
/**
 * Run History API & File Download Endpoint
 * Handles fetching run history list and securely downloading input/output files.
 */

require_once __DIR__ . '/login.php';
require_once __DIR__ . '/history_manager.php';

$action = $_REQUEST['action'] ?? 'list';

if ($action === 'list') {
    header('Content-Type: application/json');
    $userRuns = getRunHistoryForUser($loggedInUser, $isSuperAdmin);
    echo json_encode([
        'success' => true,
        'is_admin' => $isSuperAdmin,
        'current_user' => $loggedInUser,
        'runs' => $userRuns
    ]);
    exit;
}

if ($action === 'download') {
    $runId = trim($_GET['id'] ?? '');
    $type = trim($_GET['type'] ?? 'output'); // 'output' or 'input'

    if (empty($runId)) {
        http_response_code(400);
        die('Missing run ID.');
    }

    $allRuns = loadRunHistory();
    $targetRun = null;
    foreach ($allRuns as $r) {
        if ($r['id'] === $runId) {
            $targetRun = $r;
            break;
        }
    }

    if (!$targetRun) {
        http_response_code(404);
        die('Run not found.');
    }

    // Access control: Non-admins can only download their own runs
    if (!$isSuperAdmin && strtolower(trim($targetRun['user'])) !== strtolower(trim($loggedInUser))) {
        http_response_code(403);
        die('Access denied.');
    }

    if ($type === 'input') {
        $filePath = __DIR__ . '/' . ltrim($targetRun['upload_file'], '/');
        $downloadName = $targetRun['original_filename'];
        $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    } else {
        $filePath = __DIR__ . '/' . ltrim($targetRun['output_file'], '/');
        $downloadName = $targetRun['output_filename'];
        $contentType = 'text/csv';
    }

    if (!file_exists($filePath) || !is_readable($filePath)) {
        http_response_code(404);
        die('Requested file does not exist on server.');
    }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

http_response_code(400);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'error' => 'Invalid action.']);
