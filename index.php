<?php
$progress_id = uniqid('prog_', true);
// Clean up any stale progress files older than 1 hour
foreach (glob('/tmp/progress_*.json') as $f) {
    if (filemtime($f) < time() - 3600) @unlink($f);
}
//require("login.php");
require("key.php");

// Fetch libraries from Alma API
$ch = curl_init();
$url = 'https://api-na.hosted.exlibrisgroup.com/almaws/v1/conf/libraries';
$queryParams = '?' . urlencode('lang') . '=' . urlencode('en') . '&' . urlencode('apikey') . '=' . ALMA_SHELFLIST_API_KEY;
curl_setopt($ch, CURLOPT_URL, $url . $queryParams);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HEADER, FALSE);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
$response = curl_exec($ch);
curl_close($ch);
$xml_result = simplexml_load_string($response);
$libraries = [];
if ($xml_result) {
    foreach ($xml_result->library as $library) {
        $libraries[] = ['code' => (string)$library->code, 'name' => (string)$library->name];
    }
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
    <h1><span class="icon">📋</span> Alma Inventory Scanner</h1>
    <p>Upload barcodes, verify shelf order, generate reports</p>
</header>

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
</script>

</body>
</html>
