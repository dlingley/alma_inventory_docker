<?php
/**
 * User Manager Helper
 * Manages allowed users list by merging base seed file (e.g. ConfigMap)
 * with a local JSON overlay file (/srv/app/cache/allowed_users.json).
 */

define('USER_MANAGER_SEED_PATH', getenv('ALLOWED_USERS_FILE') ?: '/srv/app/allowed_users.txt');
define('USER_MANAGER_OVERLAY_PATH', __DIR__ . '/cache/allowed_users.json');

function loadSeedUsers() {
    $seedPath = USER_MANAGER_SEED_PATH;
    $users = [];
    if (file_exists($seedPath) && is_readable($seedPath)) {
        $lines = file($seedPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $trimmed = strtolower(trim($line));
                if (empty($trimmed)) continue;
                $parts = explode(':', $trimmed);
                $identifier = trim($parts[0]);
                $role = (isset($parts[1]) && trim($parts[1]) === 'admin') ? 'admin' : 'user';
                if (!empty($identifier)) {
                    $users[$identifier] = [
                        'identifier' => $identifier,
                        'role' => $role,
                        'source' => 'seed'
                    ];
                }
            }
        }
    }
    return $users;
}

function loadOverlay() {
    $overlayPath = USER_MANAGER_OVERLAY_PATH;
    if (file_exists($overlayPath) && is_readable($overlayPath)) {
        $content = file_get_contents($overlayPath);
        $data = json_decode($content, true);
        if (is_array($data)) {
            return $data; // contains ['added' => [...], 'removed' => [...]]
        }
    }
    return ['added' => [], 'removed' => []];
}

function saveOverlay($overlay) {
    $dir = dirname(USER_MANAGER_OVERLAY_PATH);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return file_put_contents(USER_MANAGER_OVERLAY_PATH, json_encode($overlay, JSON_PRETTY_PRINT)) !== false;
}

function getAllowedUsersMap() {
    $seed = loadSeedUsers();
    $overlay = loadOverlay();

    $removed = array_map('strtolower', $overlay['removed'] ?? []);
    $added = $overlay['added'] ?? [];

    $merged = [];
    foreach ($seed as $id => $info) {
        if (!in_array($id, $removed, true)) {
            $merged[$id] = $info;
        }
    }

    foreach ($added as $id => $info) {
        $idLower = strtolower($id);
        if (!in_array($idLower, $removed, true)) {
            $merged[$idLower] = [
                'identifier' => $idLower,
                'role' => (isset($info['role']) && $info['role'] === 'admin') ? 'admin' : 'user',
                'source' => 'overlay'
            ];
        }
    }

    return $merged;
}

function isUserAllowed($candidates) {
    if (!is_array($candidates)) {
        $candidates = [$candidates];
    }
    $map = getAllowedUsersMap();
    foreach ($candidates as $cand) {
        $candLower = strtolower(trim($cand));
        if (!empty($candLower) && isset($map[$candLower])) {
            return true;
        }
    }
    return false;
}

function isUserAdmin($candidates) {
    if (!is_array($candidates)) {
        $candidates = [$candidates];
    }
    $map = getAllowedUsersMap();
    foreach ($candidates as $cand) {
        $candLower = strtolower(trim($cand));
        if (!empty($candLower) && isset($map[$candLower]) && $map[$candLower]['role'] === 'admin') {
            return true;
        }
    }
    return false;
}

function addUserToOverlay($identifier, $role = 'user') {
    $idLower = strtolower(trim($identifier));
    if (empty($idLower)) return false;

    $overlay = loadOverlay();
    // If it was in removed, un-remove it
    $overlay['removed'] = array_values(array_filter($overlay['removed'] ?? [], function($item) use ($idLower) {
        return strtolower($item) !== $idLower;
    }));

    $overlay['added'][$idLower] = [
        'role' => ($role === 'admin') ? 'admin' : 'user'
    ];

    return saveOverlay($overlay);
}

function removeUserFromOverlay($identifier) {
    $idLower = strtolower(trim($identifier));
    if (empty($idLower)) return false;

    $overlay = loadOverlay();
    // Remove from added
    if (isset($overlay['added'][$idLower])) {
        unset($overlay['added'][$idLower]);
    }
    // Add to removed list if not already there
    $removed = array_map('strtolower', $overlay['removed'] ?? []);
    if (!in_array($idLower, $removed, true)) {
        $overlay['removed'][] = $idLower;
    }

    return saveOverlay($overlay);
}

function resetUsersOverlay() {
    $overlayPath = USER_MANAGER_OVERLAY_PATH;
    if (file_exists($overlayPath)) {
        @unlink($overlayPath);
    }
    return true;
}
