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

    // Check optional EXTRA_ALLOWED_USERS / ALLOWED_USERS env vars (comma-separated list)
    $envUsersStr = getenv('EXTRA_ALLOWED_USERS') ?: getenv('ALLOWED_USERS');
    if (!empty($envUsersStr)) {
        $envList = explode(',', $envUsersStr);
        foreach ($envList as $entry) {
            $entry = strtolower(trim($entry));
            if (empty($entry)) continue;
            $parts = explode(':', $entry);
            $identifier = trim($parts[0]);
            $role = (isset($parts[1]) && trim($parts[1]) === 'admin') ? 'admin' : 'user';
            if (!empty($identifier) && !in_array($identifier, $removed, true)) {
                $merged[$identifier] = [
                    'identifier' => $identifier,
                    'role' => $role,
                    'source' => 'env'
                ];
            }
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

/**
 * Extracts a human-readable identifier (email, display name, or username)
 * from SAML NameID and SAML attribute assertions.
 */
function getDisplayUserFromSaml($nameId = '', $attributes = []) {
    $preferredKeys = [
        'emailAddress', 'displayName', 'mail', 'email', 'userPrincipalName', 'eduPersonPrincipalName',
        'cn', 'name', 'uid',
        'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
        'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
        'urn:oid:0.9.2342.19200300.100.1.3', // mail OID
        'urn:oid:2.16.840.1.113730.3.1.241'  // displayName OID
    ];

    if (is_array($attributes) && !empty($attributes)) {
        foreach ($preferredKeys as $key) {
            if (!empty($attributes[$key])) {
                $val = is_array($attributes[$key]) ? $attributes[$key][0] : $attributes[$key];
                if (is_string($val) && !empty(trim($val)) && strlen(trim($val)) < 80) {
                    return trim($val);
                }
            }
        }
        // Scan all attribute values for any valid email string if preferred keys didn't match
        foreach ($attributes as $k => $v) {
            $vals = is_array($v) ? $v : [$v];
            foreach ($vals as $val) {
                if (is_string($val) && strpos($val, '@') !== false && strlen(trim($val)) < 80) {
                    return trim($val);
                }
            }
        }
    }

    if (!empty($nameId)) {
        $rawUser = trim($nameId);
        if (strlen($rawUser) <= 50) {
            return $rawUser;
        }
    }

    return '';
}
