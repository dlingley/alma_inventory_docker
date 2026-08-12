<?php
require_once(__DIR__ . "/login.php");
require_once(__DIR__ . "/key.php");
require_once(__DIR__ . "/AlmaAnalyticsChecker.php");
require_once(__DIR__ . "/history_manager.php");

$report = null;
$error = null;
$inputMode = $_POST['input_mode'] ?? 'paste';
$callNumberType = $_POST['call_number_type'] ?? 'LC';
$pastedBarcodes = $_POST['pasted_barcodes'] ?? '';
$selectedHistory = $_POST['selected_history'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $items = [];

    if ($inputMode === 'paste') {
        $lines = explode("\n", $pastedBarcodes);
        foreach ($lines as $idx => $line) {
            $line = trim($line);
            if ($line !== '') {
                // If user pasted "BARCODE, CALL_NUMBER", split them; otherwise treat line as barcode
                $parts = explode(',', $line);
                $bc = trim($parts[0]);
                $cn = isset($parts[1]) ? trim($parts[1]) : '';
                if ($bc !== '') {
                    $items[] = [
                        'barcode'     => $bc,
                        'call_number' => $cn ?: 'UNKNOWN',
                        'title'       => 'Item ' . ($idx + 1)
                    ];
                }
            }
        }
    } elseif ($inputMode === 'upload' && !empty($_FILES['upload_file']['tmp_name'])) {
        $tmpFile = $_FILES['upload_file']['tmp_name'];
        $fileName = $_FILES['upload_file']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            $items = parseAnalyticsCsvFile($tmpFile);
        } else {
            $error = "File format .$ext is not supported directly for streaming parsing; please upload a .csv file.";
        }
    } elseif ($inputMode === 'history' && !empty($selectedHistory)) {
        $baseName = basename($selectedHistory);
        $histPath = __DIR__ . '/cache/output/' . $baseName;
        if (!file_exists($histPath)) {
            $histPath = __DIR__ . '/tests/fixtures/cache_history/output/' . $baseName;
        }

        if (file_exists($histPath)) {
            $items = parseAnalyticsCsvFile($histPath);
        } else {
            $error = "Selected history file not found: " . htmlspecialchars($selectedHistory);
        }
    }

    $customApiKey = trim($_POST['custom_api_key'] ?? '');

    if (empty($error)) {
        if (empty($items)) {
            $error = "No valid barcodes or items were provided.";
        } else {
            $checker = new AlmaAnalyticsChecker($customApiKey ?: null);
            $report = $checker->compare($items, $callNumberType);
        }
    }
}

// Get list of historical files for dropdown (checking cache/output as well as test fixtures)
$historyOptions = [];
$dirsToScan = [
    __DIR__ . '/cache/output',
    __DIR__ . '/tests/fixtures/cache_history/output'
];
foreach ($dirsToScan as $d) {
    if (is_dir($d)) {
        $files = glob($d . '/*.csv');
        if (is_array($files)) {
            foreach ($files as $hf) {
                $bn = basename($hf);
                if (!in_array($bn, $historyOptions)) {
                    $historyOptions[] = $bn;
                }
            }
        }
    }
}
sort($historyOptions);

function parseAnalyticsCsvFile(string $filePath): array
{
    $items = [];
    if (($handle = fopen($filePath, 'r')) !== false) {
        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if ($header === false) {
            fclose($handle);
            return [];
        }
        $headerMap = array_change_key_case(array_flip($header), CASE_LOWER);

        $bcCol   = $headerMap['barcode'] ?? null;
        $callCol = $headerMap['call_number'] ?? $headerMap['call_no'] ?? null;
        $titleCol= $headerMap['title'] ?? null;

        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $bc = ($bcCol !== null && isset($data[$bcCol])) ? trim(str_replace(['="', '"'], '', $data[$bcCol])) : '';
            $cn = ($callCol !== null && isset($data[$callCol])) ? trim($data[$callCol]) : '';
            $title = ($titleCol !== null && isset($data[$titleCol])) ? trim($data[$titleCol]) : '';

            if ($bc !== '' || $cn !== '') {
                $items[] = [
                    'barcode'     => $bc,
                    'call_number' => $cn,
                    'title'       => $title
                ];
            }
        }
        fclose($handle);
    }
    return $items;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alma Analytics Call Number Checker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        :root {
            --color-bg: #f0f2f5;
            --color-card: #ffffff;
            --color-header: #1e293b;
            --color-header-accent: #334155;
            --color-primary: #3b82f6;
            --color-primary-hover: #2563eb;
            --color-text: #1e293b;
            --color-text-muted: #64748b;
            --color-border: #e2e8f0;
            --color-success: #10b981;
            --color-warning: #f59e0b;
            --color-danger: #ef4444;
            --radius-card: 12px;
            --shadow-card: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--color-bg); color: var(--color-text); line-height: 1.5; }
        header { background-color: var(--color-header); color: #fff; padding: 1.25rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        header h1 { font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
        header nav a { color: #cbd5e1; text-decoration: none; font-size: 0.9rem; font-weight: 500; margin-left: 1.5rem; transition: color 0.2s; }
        header nav a:hover { color: #fff; }
        main { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .card { background-color: var(--color-card); border-radius: var(--radius-card); box-shadow: var(--shadow-card); padding: 1.5rem; margin-bottom: 1.5rem; }
        .card-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; color: var(--color-header); display: flex; align-items: center; justify-content: space-between; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem; }
        .form-control, textarea, select { width: 100%; padding: 0.625rem 0.875rem; border: 1px solid var(--color-border); border-radius: 6px; font-family: inherit; font-size: 0.9rem; }
        textarea { resize: vertical; min-height: 120px; font-family: monospace; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.625rem 1.25rem; border-radius: 6px; font-weight: 600; font-size: 0.9rem; border: none; cursor: pointer; transition: background-color 0.2s; }
        .btn-primary { background-color: var(--color-primary); color: #fff; }
        .btn-primary:hover { background-color: var(--color-primary-hover); }
        .tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; border-bottom: 2px solid var(--color-border); }
        .tab-btn { padding: 0.5rem 1rem; background: none; border: none; font-weight: 600; font-size: 0.9rem; color: var(--color-text-muted); cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; }
        .tab-btn.active { color: var(--color-primary); border-bottom-color: var(--color-primary); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .kpi-card { background: #f8fafc; border: 1px solid var(--color-border); border-radius: 8px; padding: 1.25rem; text-align: center; }
        .kpi-value { font-size: 1.75rem; font-weight: 700; margin-top: 0.25rem; color: var(--color-header); }
        .kpi-label { font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .table-responsive { overflow-x: auto; margin-top: 1rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; text-align: left; }
        th, td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--color-border); }
        th { background-color: #f8fafc; font-weight: 600; color: var(--color-text-muted); }
        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .badge-success { background-color: #d1fae5; color: #065f46; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; }
        .badge-warning { background-color: #fef3c7; color: #92400e; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
        .alert-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-warning { background-color: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
    </style>
</head>
<body>
    <header>
        <h1>🔍 Alma Analytics Call Number Checker</h1>
        <nav>
            <a href="index.php">← Back to Scanner</a>
            <a href="cache_manager.php">Cache Manager</a>
        </nav>
    </header>

    <main>
        <div class="card">
            <div class="card-title">
                <span>Analytics Comparison Query</span>
                <span style="font-size:0.8rem; font-weight:normal; color:var(--color-text-muted);">
                    Report Path: <code><?= htmlspecialchars(ALMA_ANALYTICS_REPORT_PATH) ?></code>
                </span>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="input_mode" id="input_mode" value="<?= htmlspecialchars($inputMode) ?>">

                <div class="tabs">
                    <button type="button" class="tab-btn <?= $inputMode === 'paste' ? 'active' : '' ?>" onclick="switchTab('paste')">Paste Barcodes</button>
                    <button type="button" class="tab-btn <?= $inputMode === 'upload' ? 'active' : '' ?>" onclick="switchTab('upload')">Upload CSV File</button>
                    <button type="button" class="tab-btn <?= $inputMode === 'history' ? 'active' : '' ?>" onclick="switchTab('history')">Select History Fixture</button>
                </div>

                <div id="tab-paste" class="tab-content <?= $inputMode === 'paste' ? 'active' : '' ?>">
                    <div class="form-group">
                        <label for="pasted_barcodes">Barcodes (One per line, optional call number after comma):</label>
                        <textarea name="pasted_barcodes" id="pasted_barcodes" placeholder="32754069643793&#10;32754002161580, UA31 10th .L4197 2003"><?= htmlspecialchars($pastedBarcodes) ?></textarea>
                    </div>
                </div>

                <div id="tab-upload" class="tab-content <?= $inputMode === 'upload' ? 'active' : '' ?>">
                    <div class="form-group">
                        <label for="upload_file">Select Output CSV File:</label>
                        <input type="file" name="upload_file" id="upload_file" class="form-control" accept=".csv">
                    </div>
                </div>

                <div id="tab-history" class="tab-content <?= $inputMode === 'history' ? 'active' : '' ?>">
                    <div class="form-group">
                        <label for="selected_history">Choose Historical Inventory Run:</label>
                        <select name="selected_history" id="selected_history">
                            <option value="">-- Select a cached inventory run --</option>
                            <?php foreach ($historyOptions as $opt): ?>
                                <option value="<?= htmlspecialchars($opt) ?>" <?= $selectedHistory === $opt ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($opt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
                    <div class="form-group" style="margin-bottom:0; width: 200px;">
                        <label for="call_number_type">Classification System:</label>
                        <select name="call_number_type" id="call_number_type">
                            <option value="LC" <?= $callNumberType === 'LC' ? 'selected' : '' ?>>LC (Library of Congress)</option>
                            <option value="Dewey" <?= $callNumberType === 'Dewey' ? 'selected' : '' ?>>Dewey Decimal</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0; flex:1; min-width: 250px;">
                        <label for="custom_api_key">Analytics API Key (Optional Override):</label>
                        <input type="text" name="custom_api_key" id="custom_api_key" class="form-control" placeholder="Default from .env: <?= htmlspecialchars(substr(ALMA_ANALYTICS_API_KEY, 0, 6) . '...') ?>" value="<?= htmlspecialchars($_POST['custom_api_key'] ?? '') ?>">
                    </div>
                    <div style="margin-top:auto;">
                        <button type="submit" class="btn btn-primary">⚡ Query Alma & Compare Sort</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($report !== null): ?>
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-label">Total Items Checked</div>
                    <div class="kpi-value"><?= $report['total_items'] ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Found in Alma Analytics</div>
                    <div class="kpi-value" style="color: <?= $report['analytics_found'] > 0 ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                        <?= $report['analytics_found'] ?> / <?= $report['total_items'] ?>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Sequence Rank Match</div>
                    <div class="kpi-value" style="color: <?= $report['rank_match_percent'] >= 95 ? 'var(--color-success)' : 'var(--color-warning)' ?>;">
                        <?= $report['rank_match_percent'] ?>%
                    </div>
                </div>
            </div>

            <?php if (!empty($report['api_errors'])): ?>
                <div class="alert alert-warning">
                    <strong>API Warnings / Errors:</strong>
                    <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                        <?php foreach ($report['api_errors'] as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-title">
                    <span>Side-by-Side Sort Comparison</span>
                    <span class="badge <?= empty($report['discrepancies']) ? 'badge-success' : 'badge-warning' ?>">
                        <?= empty($report['discrepancies']) ? '100% Sequence Match' : count($report['discrepancies']) . ' Discrepancies' ?>
                    </span>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Barcode</th>
                                <th>Call Number</th>
                                <th>Local Norm Key (SortCallNumber)</th>
                                <th>Alma Norm Key (Analytics)</th>
                                <th>Match Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report['local_sorted'] as $idx => $item): ?>
                                <?php
                                    $rank = $idx + 1;
                                    $bc = $item['barcode'];
                                    $almaItem = $report['alma_sorted'][$idx] ?? null;
                                    $match = ($almaItem && $almaItem['barcode'] === $bc);
                                ?>
                                <tr>
                                    <td><strong>#<?= $rank ?></strong></td>
                                    <td><code><?= htmlspecialchars($bc) ?></code></td>
                                    <td><?= htmlspecialchars($item['call_number']) ?></td>
                                    <td><code><?= htmlspecialchars($item['local_norm_key']) ?></code></td>
                                    <td><code><?= htmlspecialchars($item['alma_norm_key'] ?? '[Not Returned]') ?></code></td>
                                    <td>
                                        <?php if ($match): ?>
                                            <span class="badge badge-success">Match ✅</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Mismatch ❌</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function switchTab(mode) {
            document.getElementById('input_mode').value = mode;
            $('.tab-btn').removeClass('active');
            $('.tab-content').removeClass('active');
            $(`button[onclick="switchTab('${mode}')"]`).addClass('active');
            $(`#tab-${mode}`).addClass('active');
        }
    </script>
</body>
</html>
