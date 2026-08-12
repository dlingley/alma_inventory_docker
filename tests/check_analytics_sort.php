<?php
/**
 * CLI Tool: Alma Analytics Call Number Comparison Checker
 *
 * Usage:
 *   php tests/check_analytics_sort.php --barcodes=32754069643793,32754002161580
 *   php tests/check_analytics_sort.php --file=tests/fixtures/cache_history/output/ShelfList_bcc_bcc_E185_E185_20250620.csv
 *   php tests/check_analytics_sort.php --history=ShelfList_hsse_hss2lc_BP10_BT98_20240426.csv
 */

require_once __DIR__ . '/../AlmaAnalyticsChecker.php';

$options = getopt('', ['barcodes:', 'file:', 'history:', 'type:', 'help']);

if (isset($options['help']) || (empty($options) && $argc === 1)) {
    echo "=== Alma Analytics Call Number Comparison Checker CLI ===\n\n";
    echo "Usage:\n";
    echo "  php tests/check_analytics_sort.php --barcodes=32754069643793,32754002161580\n";
    echo "  php tests/check_analytics_sort.php --file=path/to/output.csv [--type=LC|Dewey]\n";
    echo "  php tests/check_analytics_sort.php --history=ShelfList_...csv\n\n";

    echo "Running self-test unit verification...\n";
    runSelfTest();
    exit(0);
}

$callNumberType = strtoupper($options['type'] ?? 'LC');
$items = [];

if (!empty($options['barcodes'])) {
    $rawBarcodes = explode(',', $options['barcodes']);
    foreach ($rawBarcodes as $bc) {
        $bc = trim($bc);
        if ($bc !== '') {
            $items[] = [
                'barcode'     => $bc,
                'call_number' => 'FETCH_FROM_ALMA',
                'title'       => 'Item ' . $bc
            ];
        }
    }
} elseif (!empty($options['file'])) {
    $filePath = $options['file'];
    if (!file_exists($filePath)) {
        echo "❌ Error: File not found: $filePath\n";
        exit(1);
    }
    $items = parseCsvFile($filePath);
} elseif (!empty($options['history'])) {
    $historyFile = $options['history'];
    $dir = __DIR__ . '/fixtures/cache_history/output/';
    $filePath = $dir . $historyFile;
    if (!file_exists($filePath)) {
        echo "❌ Error: History file not found: $filePath\n";
        exit(1);
    }
    $items = parseCsvFile($filePath);
}

if (empty($items)) {
    echo "❌ No valid barcodes or items supplied.\n";
    exit(1);
}

echo "=== Running Alma Analytics Call Number Sort Comparison ===\n";
echo "Items to check: " . count($items) . " ($callNumberType)\n";

$checker = new AlmaAnalyticsChecker();

// Check if XML sawx filter generator works
$testXml = $checker->buildBarcodeFilterXml(array_column($items, 'barcode'));
echo "Sawx XML Filter Payload Length: " . strlen($testXml) . " bytes\n\n";

$report = $checker->compare($items, $callNumberType);

echo "========================================\n";
echo "SUMMARY METRICS:\n";
echo "  Total Items          : " . $report['total_items'] . "\n";
echo "  Found in Analytics   : " . $report['analytics_found'] . " / " . $report['total_items'] . "\n";
echo "  Sequence Rank Match  : " . $report['rank_matches'] . " (" . $report['rank_match_percent'] . "%)\n";
echo "========================================\n\n";

if (!empty($report['api_errors'])) {
    echo "⚠️ API Warnings/Errors:\n";
    foreach ($report['api_errors'] as $err) {
        echo "  - $err\n";
    }
    echo "\n";
}

if (!empty($report['discrepancies'])) {
    echo "❌ DISCREPANCIES DETECTED (" . count($report['discrepancies']) . " items):\n\n";
    echo str_pad("Rank", 6) . str_pad("Barcode", 16) . str_pad("Call Number", 28) . str_pad("Local Key", 30) . "Alma Key\n";
    echo str_repeat("-", 100) . "\n";

    foreach ($report['discrepancies'] as $disc) {
        $locItem = $disc['local_item'];
        echo str_pad("#" . $disc['rank'], 6) .
            str_pad($locItem['barcode'], 16) .
            str_pad(substr($locItem['call_number'], 0, 26), 28) .
            str_pad(substr($locItem['local_norm_key'], 0, 28), 30) .
            ($locItem['alma_norm_key'] ?? '[Not returned]') . "\n";
    }
} else {
    echo "✅ PERFECT MATCH! Local sort sequence matches Alma Analytics 100%.\n";
}

function parseCsvFile(string $filePath): array
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

function runSelfTest()
{
    $checker = new AlmaAnalyticsChecker('MOCK_KEY', '/shared/TestReport');

    // Test XML Filter Generation
    $barcodes = ['32754069643793', '32754002161580'];
    $xml = $checker->buildBarcodeFilterXml($barcodes);

    $passFilter = (str_contains($xml, 'op="in"') &&
                   str_contains($xml, '32754069643793') &&
                   str_contains($xml, '32754002161580') &&
                   str_contains($xml, 'sawx:sqlExpression'));

    echo "  " . ($passFilter ? "✅" : "❌") . " Sawx XML Filter Generation (op=\"in\")\n";

    // Test Single Barcode Filter
    $xmlSingle = $checker->buildBarcodeFilterXml(['32754069643793']);
    $passSingle = str_contains($xmlSingle, 'op="equal"');
    echo "  " . ($passSingle ? "✅" : "❌") . " Sawx XML Filter Generation (op=\"equal\")\n";

    // Test XML Parsing with Mock Alma Analytics Payload
    $mockXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<QueryResult xmlns="urn:schemas-microsoft-com:xml-analysis">
  <ResultXml>
    <rowset xmlns="urn:schemas-microsoft-com:xml-analysis:rowset">
      <Row>
        <Column0>32754069643793</Column0>
        <Column1>BP109 1985b v.10</Column1>
        <Column2>BP  109 1985 B V10</Column2>
      </Row>
      <Row>
        <Column0>32754002161580</Column0>
        <Column1>UA31 10th .L4197 2003</Column1>
        <Column2>UA  31 L 4197 10TH 2003</Column2>
      </Row>
    </rowset>
  </ResultXml>
</QueryResult>
XML;

    $parsed = $checker->parseAnalyticsXml($mockXml);
    $passParse = ($parsed['success'] && count($parsed['rows']) === 2 &&
                  $parsed['rows'][0]['barcode'] === '32754069643793' &&
                  $parsed['rows'][1]['barcode'] === '32754002161580');

    echo "  " . ($passParse ? "✅" : "❌") . " Analytics XML Response Parsing\n";

    // Test Local vs Analytics Comparison logic
    $sampleItems = [
        ['barcode' => '32754069643793', 'call_number' => 'BP109 1985b v.10', 'title' => 'Sample 1'],
        ['barcode' => '32754002161580', 'call_number' => 'UA31 10th .L4197 2003', 'title' => 'Sample 2'],
    ];

    $comp = $checker->compare($sampleItems, 'LC');
    $passComp = ($comp['total_items'] === 2 && isset($comp['rank_match_percent']));
    echo "  " . ($passComp ? "✅" : "❌") . " Comparison Engine Execution\n";

    echo "\nSelf-test finished.\n";
}
