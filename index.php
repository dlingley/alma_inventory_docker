<?php
$progress_id = uniqid('prog_', true);
// Clean up any stale progress files older than 1 hour
foreach (glob('/tmp/progress_*.json') as $f) {
    if (filemtime($f) < time() - 3600) @unlink($f);
}
require_once(__DIR__ . "/login.php");
require("key.php");

// Fetch libraries from Alma API
$ch = curl_init();
$url = 'https://api-na.hosted.exlibrisgroup.com/almaws/v1/conf/libraries';
$queryParams = '?' . urlencode('lang') . '=' . urlencode('en') . '&' . urlencode('apikey') . '=' . urlencode(trim(ALMA_SHELFLIST_API_KEY));
curl_setopt($ch, CURLOPT_URL, $url . $queryParams);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HEADER, FALSE);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
$response = curl_exec($ch);
curl_close($ch);
$xml_result = @simplexml_load_string($response);
$libraries = [];
if ($xml_result && isset($xml_result->library)) {
    foreach ($xml_result->library as $library) {
        $libraries[] = ['code' => (string)$library->code, 'name' => (string)$library->name];
    }
}

// Fallback default libraries if Alma API returns error/not allowed
if (empty($libraries)) {
    $libraries = [
        ['code' => 'hsse', 'name' => 'HSSE Library'],
        ['code' => 'hicks', 'name' => 'Hicks Undergraduate Library'],
        ['code' => 'math', 'name' => 'Mathematical Sciences Library'],
        ['code' => 'vet', 'name' => 'Veterinary Medical Library'],
        ['code' => 'pavl', 'name' => 'Archives & Special Collections'],
        ['code' => 'walc', 'name' => 'WALC Active Learning Center']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alma Inventory Scanner</title>
    <meta name="description" content="Alma library inventory scanning and shelf-reading tool for barcode processing and call number order verification.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        /* ===== Design Tokens ===== */
        :root {
            --color-bg: #f0f2f5;
            --color-card: #ffffff;
            --color-header: #1e293b;
            --color-header-accent: #334155;
            --color-primary: #3b82f6;
            --color-primary-hover: #2563eb;
            --color-primary-light: #eff6ff;
            --color-text: #1e293b;
            --color-text-secondary: #64748b;
            --color-border: #e2e8f0;
            --color-border-focus: #93c5fd;
            --color-success: #22c55e;
            --color-danger: #ef4444;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -2px rgba(0,0,0,0.05);
            --shadow-lg: 0 10px 25px -3px rgba(0,0,0,0.08), 0 4px 6px -4px rgba(0,0,0,0.04);
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-full: 9999px;
            --font: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ===== Reset & Base ===== */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font);
            background: var(--color-bg);
            color: var(--color-text);
            min-height: 100vh;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* ===== Header ===== */
        .header {
            background: linear-gradient(135deg, var(--color-header) 0%, var(--color-header-accent) 100%);
            padding: 2rem 1.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .user-bar {
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            padding: 0.35rem 0.75rem 0.35rem 0.875rem;
            border-radius: var(--radius-full);
            font-size: 0.8125rem;
            border: 1px solid rgba(255, 255, 255, 0.18);
            z-index: 10;
        }
        .user-bar .user-info {
            color: rgba(255, 255, 255, 0.95);
            font-weight: 500;
        }
        .user-bar .logout-btn {
            background: rgba(239, 68, 68, 0.85);
            color: #ffffff;
            text-decoration: none;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-weight: 600;
            font-size: 0.75rem;
            transition: all var(--transition);
        }
        .user-bar .logout-btn:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }
        .user-bar .history-btn {
            background: rgba(255, 255, 255, 0.18);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-weight: 600;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all var(--transition);
            font-family: var(--font);
        }
        .user-bar .history-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }
        .user-bar .cache-btn {
            background: rgba(16, 185, 129, 0.85);
            color: #ffffff;
            border: none;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-weight: 600;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all var(--transition);
            font-family: var(--font);
        }
        .user-bar .cache-btn:hover {
            background: #059669;
            transform: translateY(-1px);
        }
        .user-bar .admin-btn {
            background: rgba(59, 130, 246, 0.85);
            color: #ffffff;
            border: none;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-weight: 600;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all var(--transition);
            font-family: var(--font);
        }
        .user-bar .admin-btn:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        /* ===== Cache Manager Modal ===== */
        #cache-modal {
            position: fixed;
            inset: 0;
            display: none;
            z-index: 1040;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }
        #cache-modal.active { display: flex; }
        .cache-modal-card {
            background: var(--color-card);
            border-radius: var(--radius-lg);
            padding: 2rem;
            width: 95%;
            max-width: 860px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        .cache-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
            margin-bottom: 1.5rem;
        }
        .cache-card-item {
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .cache-card-title {
            font-size: 0.9375rem;
            font-weight: 700;
            color: var(--color-text);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .cache-stat-val {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--color-primary);
        }
        .cache-stat-sub {
            font-size: 0.75rem;
            color: var(--color-text-secondary);
            margin-top: 0.25rem;
            margin-bottom: 1rem;
        }
        .btn-cache-action {
            width: 100%;
            padding: 0.45rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all var(--transition);
            font-family: var(--font);
            margin-top: 0.25rem;
        }
        .btn-cache-prune { background: #e0f2fe; color: #0369a1; }
        .btn-cache-prune:hover { background: #bae6fd; }
        .btn-cache-purge { background: #fee2e2; color: #dc2626; }
        .btn-cache-purge:hover { background: #fca5a5; }

        /* ===== Run History Modal ===== */
        #history-modal {
            position: fixed;
            inset: 0;
            display: none;
            z-index: 1040;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }
        #history-modal.active { display: flex; }
        .history-modal-card {
            background: var(--color-card);
            border-radius: var(--radius-lg);
            padding: 2rem;
            width: 95%;
            max-width: 880px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-height: 85vh;
            display: flex;
            flex-direction: column;
        }
        .history-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--color-border);
        }
        .history-modal-header h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--color-text);
        }
        .history-filter-bar {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1rem;
            align-items: center;
        }
        .history-filter-bar input[type="text"] {
            flex: 1;
            padding: 0.45rem 0.75rem;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-sm);
            font-family: var(--font);
            font-size: 0.8125rem;
        }
        .history-table-wrapper {
            overflow-y: auto;
            flex: 1;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            max-height: 380px;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
            text-align: left;
        }
        .history-table th, .history-table td {
            padding: 0.625rem 0.875rem;
            border-bottom: 1px solid var(--color-border);
        }
        .history-table th {
            background: var(--color-bg);
            font-weight: 600;
            color: var(--color-text-secondary);
            position: sticky;
            top: 0;
            z-index: 2;
        }
        .btn-download-sm {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: all var(--transition);
        }
        .btn-download-csv { background: #dbeafe; color: #1e40af; }
        .btn-download-csv:hover { background: #bfdbfe; }
        .btn-download-input { background: #f1f5f9; color: #475569; }
        .btn-download-input:hover { background: #e2e8f0; }

        /* ===== Admin User Modal ===== */
        #user-modal {
            position: fixed;
            inset: 0;
            display: none;
            z-index: 1050;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }
        #user-modal.active { display: flex; }
        .admin-modal-card {
            background: var(--color-card);
            border-radius: var(--radius-lg);
            padding: 2rem;
            width: 92%;
            max-width: 620px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-height: 85vh;
            display: flex;
            flex-direction: column;
        }
        .admin-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--color-border);
        }
        .admin-modal-header h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--color-text);
        }
        .admin-modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--color-text-secondary);
        }
        .admin-add-form {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 0.75rem;
            align-items: center;
            margin-bottom: 1.25rem;
            background: var(--color-bg);
            padding: 0.875rem;
            border-radius: var(--radius-md);
        }
        .admin-add-form input[type="text"] {
            padding: 0.5rem 0.75rem;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-sm);
            font-family: var(--font);
            font-size: 0.875rem;
        }
        .admin-add-form label {
            font-size: 0.8125rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            margin: 0;
            cursor: pointer;
        }
        .btn-add-user {
            padding: 0.5rem 1rem;
            background: var(--color-primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.8125rem;
            cursor: pointer;
            font-family: var(--font);
        }
        .btn-add-user:hover { background: var(--color-primary-hover); }
        .admin-user-table-wrapper {
            overflow-y: auto;
            flex: 1;
            margin-bottom: 1rem;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            max-height: 280px;
        }
        .admin-user-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.84rem;
            text-align: left;
        }
        .admin-user-table th, .admin-user-table td {
            padding: 0.625rem 0.875rem;
            border-bottom: 1px solid var(--color-border);
        }
        .admin-user-table th {
            background: var(--color-bg);
            font-weight: 600;
            color: var(--color-text-secondary);
        }
        .badge-role {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: var(--radius-full);
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-admin { background: #dbeafe; color: #1e40af; }
        .badge-user { background: #f1f5f9; color: #475569; }
        .badge-source { font-size: 0.7rem; color: var(--color-text-secondary); }
        .btn-remove-user {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-remove-user:hover { background: #fca5a5; }
        .admin-modal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0.75rem;
            border-top: 1px solid var(--color-border);
        }
        .btn-reset-users {
            background: none;
            border: 1px solid var(--color-border);
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            color: var(--color-text-secondary);
            cursor: pointer;
        }
        .btn-reset-users:hover { border-color: var(--color-danger); color: var(--color-danger); }
        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 50%, rgba(59,130,246,0.08) 0%, transparent 50%);
            pointer-events: none;
        }
        .header h1 {
            color: #fff;
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            position: relative;
        }
        .header h1 .icon {
            display: inline-block;
            margin-right: 0.5rem;
            font-size: 1.5rem;
            vertical-align: middle;
            opacity: 0.9;
        }
        .header p {
            color: rgba(255,255,255,0.6);
            font-size: 0.875rem;
            margin-top: 0.25rem;
            position: relative;
        }

        /* ===== Main Card ===== */
        .card {
            max-width: 640px;
            margin: -1.5rem auto 2rem;
            background: var(--color-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        /* ===== Form Section ===== */
        .form-section {
            margin-bottom: 1.75rem;
        }
        .form-section:last-of-type { margin-bottom: 0; }

        .form-section-title {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--color-text-secondary);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--color-border);
        }

        /* ===== Labels ===== */
        label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--color-text);
            margin-bottom: 0.375rem;
        }

        /* ===== File Upload ===== */
        .file-upload-area {
            border: 2px dashed var(--color-border);
            border-radius: var(--radius-md);
            padding: 2rem 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all var(--transition);
            position: relative;
            background: var(--color-bg);
        }
        .file-upload-area:hover,
        .file-upload-area.dragover {
            border-color: var(--color-primary);
            background: var(--color-primary-light);
        }
        .file-upload-area .upload-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            display: block;
            opacity: 0.5;
        }
        .file-upload-area .upload-text {
            font-size: 0.875rem;
            color: var(--color-text-secondary);
        }
        .file-upload-area .upload-text strong {
            color: var(--color-primary);
        }
        .file-upload-area .file-name {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--color-success);
            margin-top: 0.5rem;
            display: none;
        }
        .file-upload-area input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        /* ===== Selects ===== */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .form-group { margin-bottom: 1rem; }
        .form-group:last-child { margin-bottom: 0; }

        select {
            width: 100%;
            padding: 0.625rem 2rem 0.625rem 0.75rem;
            font-family: var(--font);
            font-size: 0.875rem;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-sm);
            background: var(--color-card);
            color: var(--color-text);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            cursor: pointer;
            transition: all var(--transition);
        }
        select:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }
        select:hover { border-color: #cbd5e1; }

        /* ===== Pill Radio Buttons ===== */
        .pill-group {
            display: flex;
            gap: 0;
            background: var(--color-bg);
            border-radius: var(--radius-full);
            padding: 3px;
            border: 1.5px solid var(--color-border);
        }
        .pill-group label {
            flex: 1;
            text-align: center;
            padding: 0.5rem 1rem;
            font-size: 0.8125rem;
            font-weight: 500;
            border-radius: var(--radius-full);
            cursor: pointer;
            transition: all var(--transition);
            color: var(--color-text-secondary);
            margin-bottom: 0;
            user-select: none;
        }
        .pill-group input[type="radio"] { display: none; }
        .pill-group input[type="radio"]:checked + label {
            background: var(--color-primary);
            color: #fff;
            box-shadow: var(--shadow-sm);
        }

        /* ===== Toggle Switch ===== */
        .toggle-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem 1.5rem;
        }
        .toggle-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.625rem 0.875rem;
            background: var(--color-bg);
            border-radius: var(--radius-sm);
            transition: background var(--transition);
        }
        .toggle-item:hover { background: #e8edf3; }
        .toggle-item .toggle-label {
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--color-text);
            margin-bottom: 0;
        }

        .toggle {
            position: relative;
            width: 40px;
            height: 22px;
            flex-shrink: 0;
        }
        .toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #cbd5e1;
            border-radius: var(--radius-full);
            transition: all var(--transition);
        }
        .toggle .slider::before {
            content: '';
            position: absolute;
            height: 16px;
            width: 16px;
            left: 3px;
            bottom: 3px;
            background: #fff;
            border-radius: 50%;
            transition: all var(--transition);
            box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        }
        .toggle input:checked + .slider {
            background: var(--color-primary);
        }
        .toggle input:checked + .slider::before {
            transform: translateX(18px);
        }

        /* ===== Submit Button ===== */
        .submit-btn {
            width: 100%;
            padding: 0.875rem 1.5rem;
            font-family: var(--font);
            font-size: 0.9375rem;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-hover) 100%);
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition);
            margin-top: 1.5rem;
            letter-spacing: -0.01em;
            position: relative;
            overflow: hidden;
        }
        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59,130,246,0.35);
        }
        .submit-btn:active {
            transform: translateY(0);
        }
        .submit-btn::after {
            content: ' →';
        }

        /* ===== Progress Overlay ===== */
        #loading {
            position: fixed;
            inset: 0;
            display: none;
            z-index: 1000;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }
        #loading.active { display: flex; }

        .progress-card {
            background: var(--color-card);
            border-radius: var(--radius-lg);
            padding: 2.5rem 2rem;
            width: 90%;
            max-width: 420px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .progress-card h3 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .progress-card .progress-status {
            font-size: 0.8125rem;
            color: var(--color-text-secondary);
            margin-bottom: 1.25rem;
        }
        .progress-bar-wrapper {
            width: 100%;
            height: 10px;
            background: var(--color-bg);
            border-radius: var(--radius-full);
            overflow: hidden;
            margin-bottom: 0.75rem;
        }
        .progress-bar-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--color-primary), #60a5fa);
            border-radius: var(--radius-full);
            transition: width 0.5s ease;
            position: relative;
        }
        .progress-bar-fill::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        .progress-percentage {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--color-primary);
        }

        /* ===== Footer ===== */
        .footer {
            text-align: center;
            padding: 1rem;
            font-size: 0.75rem;
            color: var(--color-text-secondary);
        }

        /* ===== Responsive ===== */
        @media (max-width: 600px) {
            .header { padding-top: 3.5rem; }
            .user-bar { top: 0.75rem; right: 50%; transform: translateX(50%); width: max-content; }
            .card { margin: -1rem 0.75rem 1.5rem; padding: 1.25rem; }
            .form-row { grid-template-columns: 1fr; }
            .toggle-grid { grid-template-columns: 1fr; }
            .header h1 { font-size: 1.375rem; }
        }
    </style>
</head>
<body>

<!-- ===== Header ===== -->
<header class="header">
    <?php if (!empty($loggedInUser)): ?>
    <div class="user-bar">
        <span class="user-info">👤 <strong><?php echo htmlspecialchars($loggedInUser); ?></strong></span>
        <button id="open-history-modal-btn" class="history-btn">📜 History</button>
        <?php if (!empty($isSuperAdmin) && $isSuperAdmin === true): ?>
        <button id="open-cache-modal-btn" class="cache-btn">💾 Cache Manager</button>
        <button id="open-user-modal-btn" class="admin-btn">⚙️ Manage Users</button>
        <?php endif; ?>
        <a href="/saml/logout" class="logout-btn">Log Out</a>
    </div>
    <?php endif; ?>
    <h1><span class="icon">📋</span> Alma Inventory Scanner</h1>
    <p>Upload barcodes, verify shelf order, generate reports</p>
</header>

<!-- ===== Run History Modal ===== -->
<div id="history-modal">
    <div class="history-modal-card">
        <div class="history-modal-header">
            <h3>📜 Inventory Run History <?php echo (!empty($isSuperAdmin) && $isSuperAdmin === true) ? '<span style="font-size:0.8rem; font-weight:normal; color:var(--color-primary);">(System-wide View)</span>' : ''; ?></h3>
            <button class="admin-modal-close" id="close-history-modal-btn">&times;</button>
        </div>

        <div class="history-filter-bar">
            <input type="text" id="history-search-input" placeholder="Filter by user, library, location, or file..." />
        </div>

        <div class="history-table-wrapper">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <?php if (!empty($isSuperAdmin) && $isSuperAdmin === true): ?><th>User</th><?php endif; ?>
                        <th>Library : Location</th>
                        <th>Barcodes / Issues</th>
                        <th>Files & Downloads</th>
                    </tr>
                </thead>
                <tbody id="history-table-body">
                    <tr><td colspan="5" style="text-align:center;">Loading history...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===== Progress Overlay ===== -->
<div id="loading">
    <div class="progress-card">
        <h3>Processing Barcodes</h3>
        <p class="progress-status" id="progress-job">Initializing...</p>
        <div class="progress-bar-wrapper">
            <div class="progress-bar-fill" id="pg-fill"></div>
        </div>
        <div class="progress-percentage" id="pg-percent">0%</div>
        <progress id="pg" max="100" value="0" style="display:none;"></progress>
    </div>
</div>

<?php if (!empty($isSuperAdmin) && $isSuperAdmin === true): ?>
<!-- ===== Cache Manager Modal ===== -->
<div id="cache-modal">
    <div class="cache-modal-card">
        <div class="admin-modal-header">
            <h3>💾 Cache Storage Management</h3>
            <button class="admin-modal-close" id="close-cache-modal-btn">&times;</button>
        </div>

        <div style="background:var(--color-bg); padding:1rem; border-radius:var(--radius-md); border:1px solid var(--color-border); margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <span style="font-size:0.875rem; color:var(--color-text-secondary);">Total PVC Cache Storage:</span>
                <strong id="cache-total-size" style="font-size:1.25rem; color:var(--color-primary); margin-left:0.5rem;">0 B</strong>
            </div>
            <div>
                <span style="font-size:0.875rem; color:var(--color-text-secondary);">Total Files:</span>
                <strong id="cache-total-count" style="font-size:1.25rem; color:var(--color-text); margin-left:0.5rem;">0</strong>
            </div>
        </div>

        <div class="cache-grid">
            <!-- Barcodes Cache Card -->
            <div class="cache-card-item">
                <div>
                    <div class="cache-card-title">🏷️ Barcode XML Cache</div>
                    <div class="cache-stat-val" id="stat-barcode-count">0</div>
                    <div class="cache-stat-sub" id="stat-barcode-sub">Size: 0 B &middot; Oldest: N/A</div>
                </div>
                <div>
                    <button type="button" class="btn-cache-action btn-cache-prune" id="btn-prune-barcodes-30">🧹 Prune Expired (>30 days)</button>
                    <button type="button" class="btn-cache-action btn-cache-purge" id="btn-clear-barcodes-all">🗑️ Clear All Barcode Cache</button>
                </div>
            </div>

            <!-- Report CSV Archives Card -->
            <div class="cache-card-item">
                <div>
                    <div class="cache-card-title">📊 Report CSV Archives</div>
                    <div class="cache-stat-val" id="stat-output-count">0</div>
                    <div class="cache-stat-sub" id="stat-output-sub">Size: 0 B &middot; Oldest: N/A</div>
                </div>
                <div>
                    <button type="button" class="btn-cache-action btn-cache-prune" id="btn-prune-archives-90">🧹 Prune Archives (>90 days)</button>
                </div>
            </div>

            <!-- Archived Input Files Card -->
            <div class="cache-card-item">
                <div>
                    <div class="cache-card-title">📥 Uploaded Input Files</div>
                    <div class="cache-stat-val" id="stat-uploads-count">0</div>
                    <div class="cache-stat-sub" id="stat-uploads-sub">Size: 0 B &middot; Oldest: N/A</div>
                </div>
                <div>
                    <span style="font-size:0.75rem; color:var(--color-text-secondary);">Pruned automatically with reports (>90d)</span>
                </div>
            </div>

            <!-- Staging Uploads Card -->
            <div class="cache-card-item">
                <div>
                    <div class="cache-card-title">📁 Temporary Staging</div>
                    <div class="cache-stat-val" id="stat-staging-count">0</div>
                    <div class="cache-stat-sub" id="stat-staging-sub">Size: 0 B</div>
                </div>
                <div>
                    <button type="button" class="btn-cache-action btn-cache-purge" id="btn-purge-staging">🧹 Purge Staging Files</button>
                </div>
            </div>
        </div>

        <div class="admin-modal-footer">
            <button type="button" class="action-btn action-btn-outline" id="btn-refresh-cache-stats">🔄 Refresh Metrics</button>
            <span style="font-size:0.75rem; color:var(--color-text-secondary);">Superadmin Cache Operations</span>
        </div>
    </div>
</div>

<!-- ===== User Management Modal ===== -->
<div id="user-modal">
    <div class="admin-modal-card">
        <div class="admin-modal-header">
            <h3>⚙️ User Access Management</h3>
            <button class="admin-modal-close" id="close-user-modal-btn">&times;</button>
        </div>

        <form id="form-add-user" class="admin-add-form">
            <input type="text" id="new-user-id" placeholder="Username or email (e.g. user@purdue.edu)" required />
            <label>
                <input type="checkbox" id="new-user-is-admin" />
                Make Superadmin
            </label>
            <button type="submit" class="btn-add-user">+ Add User</button>
        </form>

        <div class="admin-user-table-wrapper">
            <table class="admin-user-table">
                <thead>
                    <tr>
                        <th>Identifier</th>
                        <th>Role</th>
                        <th>Source</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody id="user-table-body">
                    <tr><td colspan="4" style="text-align:center;">Loading users...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="admin-modal-footer">
            <button type="button" class="btn-reset-users" id="btn-reset-users">Reset to ConfigMap Defaults</button>
            <span style="font-size:0.75rem; color:var(--color-text-secondary);">Changes take effect immediately</span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== Main Card ===== -->
<main class="card">
    <iframe name="process_frame" id="process_frame" style="display:none;"></iframe>
    <form method="post" name="ShelfLister" id="ShelfLister" action="process_barcodes.php" enctype="multipart/form-data" target="process_frame">
        <input type="hidden" name="progress_id" value="<?php echo $progress_id; ?>" />

        <!-- File Upload -->
        <div class="form-section">
            <div class="form-section-title">File Upload</div>
            <div class="file-upload-area" id="file-drop-area">
                <span class="upload-icon">📁</span>
                <p class="upload-text"><strong>Click to browse</strong> or drag & drop your file</p>
                <p class="upload-text" style="font-size:0.75rem; margin-top:0.25rem;">Accepts .xlsx barcode files</p>
                <p class="file-name" id="file-display-name"></p>
                <input type="file" id="flie" class="required" name="file" accept=".xlsx" />
            </div>
        </div>

        <!-- Call Number Type -->
        <div class="form-section">
            <div class="form-section-title">Classification</div>
            <label>Call Number Type</label>
            <div class="pill-group">
                <input type="radio" class="required" id="cnLC" name="cnType" value="lc" checked="checked" />
                <label for="cnLC">LC</label>
                <input type="radio" class="required" id="cnDewey" name="cnType" value="dewey" />
                <label for="cnDewey">Dewey</label>
                <input type="radio" class="required" id="cnOther" name="cnType" value="other" />
                <label for="cnOther">Other</label>
            </div>
        </div>

        <!-- Library & Location -->
        <div class="form-section">
            <div class="form-section-title">Location</div>
            <div class="form-row">
                <div class="form-group">
                    <label for="library">Library</label>
                    <select size="1" name="library" id="library" class="required">
                        <?php foreach ($libraries as $lib): ?>
                        <option value="<?php echo htmlspecialchars($lib['code']); ?>"><?php echo htmlspecialchars($lib['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="location">Scan Location</label>
                    <select size="1" name="location" id="location" class="required">
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="itemType">Primary Item Type</label>
                    <select size="1" name="itemType" id="itemType" class="required">
                        <option value="BOOK">Book</option>
                        <option value="PERIODICAL">Periodical</option>
                        <option value="DVD">DVD</option>
                        <option value="THESIS">Thesis</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="policy">Primary Policy</label>
                    <select size="1" name="policy" id="policy" class="required">
                        <option value="core">Core</option>
                        <option value="reserve">Reserve</option>
                        <option value="cont lit">Contemporary Lit</option>
                        <option value="media">Media</option>
                        <option value="juvenile">Juvenile</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Report Options -->
        <div class="form-section">
            <div class="form-section-title">Report Options</div>
            <div class="toggle-grid">
                <div class="toggle-item">
                    <span class="toggle-label">Only CN Order Problems</span>
                    <label class="toggle">
                        <input type="checkbox" id="toggle-onlyorder" />
                        <span class="slider"></span>
                    </label>
                    <input type="hidden" name="onlyorder" id="onlyorder-val" value="false" />
                </div>
                <div class="toggle-item">
                    <span class="toggle-label">Only Non-CN Problems</span>
                    <label class="toggle">
                        <input type="checkbox" id="toggle-onlyother" />
                        <span class="slider"></span>
                    </label>
                    <input type="hidden" name="onlyother" id="onlyother-val" value="false" />
                </div>
                <div class="toggle-item">
                    <span class="toggle-label">Report Only Problems</span>
                    <label class="toggle">
                        <input type="checkbox" id="toggle-onlyproblems" />
                        <span class="slider"></span>
                    </label>
                    <input type="hidden" name="onlyproblems" id="onlyproblems-val" value="false" />
                </div>
                <div class="toggle-item">
                    <span class="toggle-label">Clear Cache</span>
                    <label class="toggle">
                        <input type="checkbox" id="toggle-clearcache" />
                        <span class="slider"></span>
                    </label>
                    <input type="hidden" name="clearCache" id="clearCache-val" value="false" />
                </div>
            </div>
        </div>

        <!-- Submit -->
        <button type="submit" class="submit-btn" name="submit">Scan & Process Inventory</button>
    </form>
</main>

<footer class="footer">
    Alma Inventory Scanner &middot; Powered by Alma API
</footer>

<!-- ===== JavaScript ===== -->
<script>
$(document).ready(function() {

    // --- File Upload Display ---
    var fileInput = document.getElementById('flie');
    var dropArea = document.getElementById('file-drop-area');
    var fileDisplay = document.getElementById('file-display-name');

    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            fileDisplay.textContent = '✓ ' + this.files[0].name;
            fileDisplay.style.display = 'block';
            dropArea.style.borderColor = '#22c55e';
        }
    });
    ['dragenter','dragover'].forEach(function(evt) {
        dropArea.addEventListener(evt, function(e) {
            e.preventDefault();
            dropArea.classList.add('dragover');
        });
    });
    ['dragleave','drop'].forEach(function(evt) {
        dropArea.addEventListener(evt, function(e) {
            e.preventDefault();
            dropArea.classList.remove('dragover');
        });
    });

    // --- Toggle Switches → Hidden Radio Values ---
    $('#toggle-onlyorder').change(function()    { $('#onlyorder-val').val(this.checked ? 'true' : 'false'); });
    $('#toggle-onlyother').change(function()    { $('#onlyother-val').val(this.checked ? 'true' : 'false'); });
    $('#toggle-onlyproblems').change(function() { $('#onlyproblems-val').val(this.checked ? 'true' : 'false'); });
    $('#toggle-clearcache').change(function()   { $('#clearCache-val').val(this.checked ? 'true' : 'false'); });

    // --- Library → Location AJAX Lookup ---
    $('#library').on('change', function() { loadLocations(); });
    function loadLocations() {
        var libId = $('#library').val();
        $.ajax({
            url: 'almaLocationsAPI.php',
            data: { lib_id: libId, sid: Math.random() },
            dataType: 'json',
            success: function(data) {
                var $loc = $('#location');
                $loc.empty();
                if (data && data.locationData) {
                    for (var i = 0; i < data.locationData.length; i++) {
                        $loc.append($('<option>', {
                            value: data.locationData[i].code,
                            text: data.locationData[i].name
                        }));
                    }
                }
            }
        });
    }
    // Load locations for the initially selected library
    loadLocations();

    // --- Form Submit ---
    $('#ShelfLister').on('submit', function() {
        startProgress('pg', '<?php echo $progress_id; ?>');
        $('#loading').addClass('active');
        return true;
    });
});

// ===== Progress Bar =====
function startProgress(barName, progressId) {
    console.log("PG Process Started");
    window._progressId = progressId;
    setTimeout(function() { progressLoop(barName); }, 2000);
}

function progressLoop(barName) {
    console.log("Progress Called");
    $.ajax({
        url: "getProgress.php?id=" + window._progressId,
        cache: false,
        dataType: "json",
        success: function(data) {
            try {
                var obj = data;
                var pct = obj.percentage || 0;
                var job = obj.job || 'Working...';

                // Update the visual progress bar
                document.getElementById('pg').value = pct;
                document.getElementById('pg-fill').style.width = pct + '%';
                document.getElementById('pg-percent').textContent = pct + '%';
                document.getElementById('progress-job').textContent = job === 'complete' ? 'Finishing up...' : job;

                if (obj.job === "complete") {
                    document.getElementById('pg-fill').style.width = '100%';
                    document.getElementById('pg-percent').textContent = '100%';
                    document.getElementById('progress-job').textContent = 'Loading results...';
                    // Show results from iframe
                    var iframe = document.getElementById('process_frame');
                    var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    if (iframeDoc && iframeDoc.body && iframeDoc.body.innerHTML.length > 0) {
                        document.open();
                        document.write(iframeDoc.documentElement.outerHTML);
                        document.close();
                    } else {
                        setTimeout(function() { progressLoop(barName); }, 1000);
                    }
                } else {
                    setTimeout(function() { progressLoop(barName); }, 2000);
                }
            } catch(e) {
                console.log("Progress error: " + e);
                setTimeout(function() { progressLoop(barName); }, 2000);
            }
        },
        error: function(xhr, status, err) {
            console.log("pERROR: " + err + " — retrying...");
            setTimeout(function() { progressLoop(barName); }, 2000);
        }
    });
}

<?php if (!empty($isSuperAdmin) && $isSuperAdmin === true): ?>
// ===== User Management Modal JS =====
function renderUserTable(users) {
    var $tbody = $('#user-table-body');
    $tbody.empty();
    if (!users || users.length === 0) {
        $tbody.append('<tr><td colspan="4" style="text-align:center;">No allowed users configured.</td></tr>');
        return;
    }
    users.forEach(function(u) {
        var roleBadge = u.role === 'admin' 
            ? '<span class="badge-role badge-admin">Admin</span>' 
            : '<span class="badge-role badge-user">User</span>';
        var sourceBadge = u.source === 'overlay' 
            ? '<span class="badge-source">(Custom)</span>' 
            : '<span class="badge-source">(ConfigMap)</span>';
        var removeBtn = '<button type="button" class="btn-remove-user" data-id="' + u.identifier + '">Remove</button>';

        $tbody.append(
            '<tr>' +
                '<td><strong>' + u.identifier + '</strong></td>' +
                '<td>' + roleBadge + '</td>' +
                '<td>' + sourceBadge + '</td>' +
                '<td style="text-align:right;">' + removeBtn + '</td>' +
            '</tr>'
        );
    });
}

function fetchUsers() {
    $.ajax({
        url: 'adminUsersAPI.php',
        data: { action: 'list' },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                renderUserTable(res.users);
            }
        }
    });
}

$('#open-user-modal-btn').on('click', function() {
    $('#user-modal').addClass('active');
    fetchUsers();
});

$('#close-user-modal-btn').on('click', function() {
    $('#user-modal').removeClass('active');
});

$('#user-modal').on('click', function(e) {
    if (e.target === this) {
        $('#user-modal').removeClass('active');
    }
});

$('#form-add-user').on('submit', function(e) {
    e.preventDefault();
    var userId = $('#new-user-id').val().trim();
    var isAdmin = $('#new-user-is-admin').is(':checked');
    if (!userId) return;

    $.ajax({
        url: 'adminUsersAPI.php',
        method: 'POST',
        data: {
            action: 'add',
            identifier: userId,
            role: isAdmin ? 'admin' : 'user'
        },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                $('#new-user-id').val('');
                $('#new-user-is-admin').prop('checked', false);
                renderUserTable(res.users);
            } else {
                alert(res.error || 'Failed to add user');
            }
        }
    });
});

$(document).on('click', '.btn-remove-user', function() {
    var userId = $(this).data('id');
    if (!confirm('Are you sure you want to remove access for ' + userId + '?')) return;

    $.ajax({
        url: 'adminUsersAPI.php',
        method: 'POST',
        data: { action: 'remove', identifier: userId },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                renderUserTable(res.users);
            } else {
                alert(res.error || 'Failed to remove user');
            }
        }
    });
});

$('#btn-reset-users').on('click', function() {
    if (!confirm('Reset custom user changes and revert to base ConfigMap users?')) return;

    $.ajax({
        url: 'adminUsersAPI.php',
        method: 'POST',
        data: { action: 'reset' },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                renderUserTable(res.users);
            } else {
                alert(res.error || 'Failed to reset users');
            }
        }
    });
});
<?php endif; ?>

// ===== Run History Modal JS =====
var _historyData = [];
var _isAdmin = <?php echo (!empty($isSuperAdmin) && $isSuperAdmin === true) ? 'true' : 'false'; ?>;

function renderHistoryTable(runs) {
    var $tbody = $('#history-table-body');
    $tbody.empty();
    if (!runs || runs.length === 0) {
        $tbody.append('<tr><td colspan="' + (_isAdmin ? 5 : 4) + '" style="text-align:center;">No inventory runs recorded yet.</td></tr>');
        return;
    }

    runs.forEach(function(r) {
        var userCol = _isAdmin ? '<td><span class="badge-role badge-user">' + (r.user || 'Unknown') + '</span></td>' : '';
        var inputLink = r.upload_file ? '<a href="runHistoryAPI.php?action=download&type=input&id=' + r.id + '" class="btn-download-sm btn-download-input" title="Download Input .xlsx">📥 Input .xlsx</a>' : '';
        var csvLink = r.output_file ? '<a href="runHistoryAPI.php?action=download&type=output&id=' + r.id + '" class="btn-download-sm btn-download-csv" title="Download Output CSV">📊 Output .csv</a>' : '';

        $tbody.append(
            '<tr>' +
                '<td><strong>' + (r.formatted_date || r.timestamp) + '</strong></td>' +
                userCol +
                '<td>' + (r.library || '') + ' : ' + (r.location || '') + '</td>' +
                '<td>Processed <strong>' + (r.barcode_count || 0) + '</strong> &middot; <span style="color:var(--color-danger);font-weight:600;">' + (r.problem_count || 0) + ' issues</span></td>' +
                '<td><div style="display:flex;gap:0.35rem;">' + csvLink + inputLink + '</div></td>' +
            '</tr>'
        );
    });
}

function fetchRunHistory() {
    $.ajax({
        url: 'runHistoryAPI.php',
        data: { action: 'list' },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                _historyData = res.runs || [];
                filterAndRenderHistory();
            }
        }
    });
}

function filterAndRenderHistory() {
    var q = $('#history-search-input').val().toLowerCase().trim();
    if (!q) {
        renderHistoryTable(_historyData);
        return;
    }
    var filtered = _historyData.filter(function(r) {
        return (r.user && r.user.toLowerCase().indexOf(q) !== -1) ||
               (r.library && r.library.toLowerCase().indexOf(q) !== -1) ||
               (r.location && r.location.toLowerCase().indexOf(q) !== -1) ||
               (r.original_filename && r.original_filename.toLowerCase().indexOf(q) !== -1) ||
               (r.output_filename && r.output_filename.toLowerCase().indexOf(q) !== -1) ||
               (r.formatted_date && r.formatted_date.toLowerCase().indexOf(q) !== -1);
    });
    renderHistoryTable(filtered);
}

$('#history-search-input').on('input', filterAndRenderHistory);

$('#open-history-modal-btn').on('click', function() {
    $('#history-modal').addClass('active');
    fetchRunHistory();
});

$('#close-history-modal-btn').on('click', function() {
    $('#history-modal').removeClass('active');
});

$('#history-modal').on('click', function(e) {
    if (e.target === this) {
        $('#history-modal').removeClass('active');
    }
});

// Auto-open history modal if requested via URL param (e.g. index.php?show_history=1)
if (window.location.search.indexOf('show_history=1') !== -1) {
    $('#history-modal').addClass('active');
    fetchRunHistory();
}

<?php if (!empty($isSuperAdmin) && $isSuperAdmin === true): ?>
// ===== Cache Manager Modal JS =====
function fetchCacheStats() {
    $.ajax({
        url: 'adminCacheAPI.php',
        data: { action: 'stats' },
        dataType: 'json',
        success: function(res) {
            if (res.success && res.stats) {
                var s = res.stats;
                $('#cache-total-size').text(s.formatted_total_size || '0 B');
                $('#cache-total-count').text(s.total_count || 0);

                var b = s.categories.barcodes || {};
                $('#stat-barcode-count').text(b.count || 0);
                $('#stat-barcode-sub').html('Size: ' + (b.formatted_size || '0 B') + ' &middot; Oldest: ' + (b.oldest_date || 'N/A'));

                var o = s.categories.output || {};
                $('#stat-output-count').text(o.count || 0);
                $('#stat-output-sub').html('Size: ' + (o.formatted_size || '0 B') + ' &middot; Oldest: ' + (o.oldest_date || 'N/A'));

                var u = s.categories.uploads || {};
                $('#stat-uploads-count').text(u.count || 0);
                $('#stat-uploads-sub').html('Size: ' + (u.formatted_size || '0 B') + ' &middot; Oldest: ' + (u.oldest_date || 'N/A'));

                var st = s.categories.staging || {};
                $('#stat-staging-count').text(st.count || 0);
                $('#stat-staging-sub').html('Size: ' + (st.formatted_size || '0 B'));
            }
        }
    });
}

function runCacheAction(action, confirmMsg) {
    if (confirmMsg && !confirm(confirmMsg)) return;

    $.ajax({
        url: 'adminCacheAPI.php',
        method: 'POST',
        data: { action: action },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                alert(res.message || 'Cache action completed.');
                fetchCacheStats();
            } else {
                alert(res.error || 'Failed to complete cache operation.');
            }
        }
    });
}

$('#open-cache-modal-btn').on('click', function() {
    $('#cache-modal').addClass('active');
    fetchCacheStats();
});

$('#close-cache-modal-btn').on('click', function() {
    $('#cache-modal').removeClass('active');
});

$('#cache-modal').on('click', function(e) {
    if (e.target === this) {
        $('#cache-modal').removeClass('active');
    }
});

$('#btn-refresh-cache-stats').on('click', fetchCacheStats);

$('#btn-prune-barcodes-30').on('click', function() {
    runCacheAction('prune_barcodes_30', 'Prune barcode cache files older than 30 days?');
});

$('#btn-clear-barcodes-all').on('click', function() {
    runCacheAction('clear_barcodes_all', 'Are you sure you want to clear ALL cached barcode XML files?');
});

$('#btn-prune-archives-90').on('click', function() {
    runCacheAction('prune_archives_90', 'Prune report CSVs and uploaded input files older than 90 days?');
});

$('#btn-purge-staging').on('click', function() {
    runCacheAction('purge_staging', 'Purge all temporary files from staging directory?');
});
<?php endif; ?>
</script>

</body>
</html>
