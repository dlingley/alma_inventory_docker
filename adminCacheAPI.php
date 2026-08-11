<?php
/**
 * Superadmin Cache Management REST API Endpoint
 * Handles fetching cache storage stats and executing cleanup/pruning operations.
 */

require_once __DIR__ . '/login.php';
require_once __DIR__ . '/cache_manager.php';

header('Content-Type: application/json');

// Strict Superadmin authorization check
if (empty($isSuperAdmin) || $isSuperAdmin !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied. Superadmin privileges required.']);
    exit;
}

$action = $_REQUEST['action'] ?? 'stats';

if ($action === 'stats') {
    $stats = getAllCacheStats();
    echo json_encode(['success' => true, 'stats' => $stats]);
    exit;
}

if ($action === 'prune_barcodes_30') {
    $count = pruneExpiredBarcodes(30);
    $stats = getAllCacheStats();
    echo json_encode(['success' => true, 'message' => "Pruned {$count} barcode XML cache files older than 30 days.", 'stats' => $stats]);
    exit;
}

if ($action === 'clear_barcodes_all') {
    $count = clearAllBarcodeCache();
    $stats = getAllCacheStats();
    echo json_encode(['success' => true, 'message' => "Cleared all {$count} barcode XML cache files.", 'stats' => $stats]);
    exit;
}

if ($action === 'prune_archives_90') {
    $count = pruneOldArchives(90);
    $stats = getAllCacheStats();
    echo json_encode(['success' => true, 'message' => "Pruned {$count} archived report & upload files older than 90 days.", 'stats' => $stats]);
    exit;
}

if ($action === 'purge_staging') {
    $count = purgeStagingUploads();
    $stats = getAllCacheStats();
    echo json_encode(['success' => true, 'message' => "Purged {$count} temporary staging files from cache/upload/.", 'stats' => $stats]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action.']);
