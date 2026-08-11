<?php
/**
 * Cache Manager Helper
 * Scans, calculates stats, and performs cleanup operations on /srv/app/cache/
 */

define('CACHE_BASE_DIR', __DIR__ . '/cache');

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function getDirStats($dirPath) {
    $stats = [
        'count' => 0,
        'size' => 0,
        'formatted_size' => '0 B',
        'oldest_mtime' => null,
        'newest_mtime' => null,
        'oldest_date' => 'N/A',
        'newest_date' => 'N/A'
    ];

    if (!is_dir($dirPath)) {
        return $stats;
    }

    $files = glob(rtrim($dirPath, '/') . '/*');
    if (!is_array($files)) {
        return $stats;
    }

    foreach ($files as $file) {
        if (is_file($file)) {
            $stats['count']++;
            $size = filesize($file);
            $stats['size'] += ($size !== false ? $size : 0);

            $mtime = filemtime($file);
            if ($mtime !== false) {
                if ($stats['oldest_mtime'] === null || $mtime < $stats['oldest_mtime']) {
                    $stats['oldest_mtime'] = $mtime;
                }
                if ($stats['newest_mtime'] === null || $mtime > $stats['newest_mtime']) {
                    $stats['newest_mtime'] = $mtime;
                }
            }
        }
    }

    $stats['formatted_size'] = formatBytes($stats['size']);
    if ($stats['oldest_mtime'] !== null) {
        $stats['oldest_date'] = date('M j, Y g:i A', $stats['oldest_mtime']);
    }
    if ($stats['newest_mtime'] !== null) {
        $stats['newest_date'] = date('M j, Y g:i A', $stats['newest_mtime']);
    }

    return $stats;
}

function getAllCacheStats() {
    $barcodesStats = getDirStats(CACHE_BASE_DIR . '/barcodes');
    $outputStats   = getDirStats(CACHE_BASE_DIR . '/output');
    $uploadsStats  = getDirStats(CACHE_BASE_DIR . '/uploads');
    $stagingStats  = getDirStats(CACHE_BASE_DIR . '/upload');

    $totalSize = $barcodesStats['size'] + $outputStats['size'] + $uploadsStats['size'] + $stagingStats['size'];
    $totalCount = $barcodesStats['count'] + $outputStats['count'] + $uploadsStats['count'] + $stagingStats['count'];

    return [
        'total_size' => $totalSize,
        'formatted_total_size' => formatBytes($totalSize),
        'total_count' => $totalCount,
        'categories' => [
            'barcodes' => $barcodesStats,
            'output' => $outputStats,
            'uploads' => $uploadsStats,
            'staging' => $stagingStats
        ]
    ];
}

function pruneExpiredBarcodes($days = 30) {
    $dir = CACHE_BASE_DIR . '/barcodes';
    if (!is_dir($dir)) return 0;

    $threshold = strtotime("-{$days} days");
    $deletedCount = 0;

    foreach (glob($dir . '/*.xml') as $file) {
        if (is_file($file) && filemtime($file) < $threshold) {
            if (@unlink($file)) {
                $deletedCount++;
            }
        }
    }

    return $deletedCount;
}

function clearAllBarcodeCache() {
    $dir = CACHE_BASE_DIR . '/barcodes';
    if (!is_dir($dir)) return 0;

    $deletedCount = 0;
    foreach (glob($dir . '/*') as $file) {
        if (is_file($file)) {
            if (@unlink($file)) {
                $deletedCount++;
            }
        }
    }

    return $deletedCount;
}

function pruneOldArchives($days = 90) {
    $threshold = strtotime("-{$days} days");
    $deletedCount = 0;

    $dirs = [CACHE_BASE_DIR . '/output', CACHE_BASE_DIR . '/uploads'];
    foreach ($dirs as $dir) {
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') as $file) {
                if (is_file($file) && filemtime($file) < $threshold) {
                    if (@unlink($file)) {
                        $deletedCount++;
                    }
                }
            }
        }
    }

    return $deletedCount;
}

function purgeStagingUploads() {
    $dir = CACHE_BASE_DIR . '/upload';
    if (!is_dir($dir)) return 0;

    $deletedCount = 0;
    foreach (glob($dir . '/*') as $file) {
        if (is_file($file)) {
            if (@unlink($file)) {
                $deletedCount++;
            }
        }
    }

    return $deletedCount;
}
