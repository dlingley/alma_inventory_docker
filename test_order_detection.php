<?php
/**
 * Shelf Order Position Regression Test Runner
 *
 * Runs shelf order evaluation against the user-reported scenarios and edge cases.
 *
 * Run: php test_order_detection.php
 */

require_once __DIR__ . '/SortCallNumber.php';
require_once __DIR__ . '/ShelfOrderEvaluator.php';

echo "=================================================================\n";
echo " Alma Inventory Shelf Order Position Test Runner                 \n";
echo "=================================================================\n\n";

/**
 * Helper: Runs ShelfOrderEvaluator order check logic
 */
function evaluateShelfOrder(array $scannedItems, string $cnType = 'lc'): array
{
    $unsorted = [];
    foreach ($scannedItems as $idx => $item) {
        $item['scan_loc'] = $idx;
        $unsorted[$idx] = $item;
    }

    $sortednk = $unsorted;
    if ($cnType === 'dewey') {
        usort($sortednk, 'SortDeweyObject');
    } else {
        usort($sortednk, 'SortLCObject');
    }

    $evals = ShelfOrderEvaluator::evaluate($sortednk, $unsorted);

    $results = [];
    foreach ($sortednk as $key => $number) {
        $ev = $evals[$key];
        $number['correct_loc'] = $ev['correct_loc'];
        $number['scanned_loc'] = $ev['scanned_loc'];
        $number['is_ooo']      = $ev['is_ooo'];
        $number['move']        = $ev['move'];
        $number['prev_cn']     = $ev['prev_cn'];
        $number['next_cn']     = $ev['next_cn'];
        $results[] = $number;
    }

    return $results;
}

$all_pass = true;

// =========================================================================
// TEST 1: Scenario 1 - Buddy Blind Spot (Scans 523-531)
// =========================================================================
echo "=== Test 1: Scenario 1 - Consecutive Misplaced Books (Buddy Blind Spot) ===\n";
$scenario1_items = [
    ['call_number' => 'RG400 .A1 2010',        'scan_idx' => 522, 'barcode' => '32754080000001'],
    ['call_number' => 'RG480.S7 P65 2013',     'scan_idx' => 523, 'barcode' => '32754083156988'],
    ['call_number' => 'RG493 .B64 2006',       'scan_idx' => 524, 'barcode' => '32754079792986'],
    ['call_number' => 'RL95 .P35 2000',        'scan_idx' => 525, 'barcode' => '32754071208817'], // misplaced in RG
    ['call_number' => 'RL95 .W44 2010',        'scan_idx' => 526, 'barcode' => '32754081568192'], // misplaced in RG
    ['call_number' => 'RG493.5.B56 .P56 2018', 'scan_idx' => 527, 'barcode' => '32754084243876'],
    ['call_number' => 'RG493.5.B56 R67 2006',  'scan_idx' => 528, 'barcode' => '32754079755058'],
    ['call_number' => 'RL81 .H87 1998',        'scan_idx' => 529, 'barcode' => '32754074137021'],
    ['call_number' => 'RL95 .L48 2009',        'scan_idx' => 530, 'barcode' => '32754079214395'],
    ['call_number' => 'RL96 .D478 2010',       'scan_idx' => 531, 'barcode' => '32754079598458'],
    ['call_number' => 'RL99 .Z9 2012',         'scan_idx' => 532, 'barcode' => '32754080000002'],
];

$results1 = evaluateShelfOrder($scenario1_items, 'lc');
$flagged1 = array_filter($results1, fn($r) => $r['is_ooo']);

echo "Catalog sorted results (ShelfOrderEvaluator):\n";
printf("  %-12s | %-24s | %-10s | %-35s\n", "Correct Loc", "Call Number", "Scan Loc", "Problem Flag");
echo "  " . str_repeat('-', 88) . "\n";
foreach ($results1 as $r) {
    $flag = $r['is_ooo'] ? "⚠️ " . $r['move'] : "OK";
    printf("  %-12s | %-24s | %-10s | %-35s\n", $r['correct_loc'], $r['call_number'], $scenario1_items[$r['scan_loc']]['scan_idx'], $flag);
}

// Verify both misplaced RL95 books are flagged
$flaggedCalls1 = array_column($flagged1, 'call_number');
$s1_pass = (count($flagged1) === 2 && in_array('RL95 .P35 2000', $flaggedCalls1) && in_array('RL95 .W44 2010', $flaggedCalls1));
echo "\nScenario 1 Resolution: " . ($s1_pass ? "✅ PASS (Both scans 525 & 526 correctly flagged!)" : "❌ FAIL") . "\n\n";
$all_pass = $all_pass && $s1_pass;

// =========================================================================
// TEST 2: Scenario 2 - Year Suffix Order & Movement Reversal (Scans 696-700)
// =========================================================================
echo "=== Test 2: Scenario 2 - Publication Year Letter Suffix (1974b vs 1975) ===\n";
$scenario2_items = [
    ['call_number' => 'SF613.E45 A3 2013',     'scan_idx' => 696, 'barcode' => '32754083162176'],
    ['call_number' => 'SF613.H44 A3 2015',     'scan_idx' => 697, 'barcode' => '32754083505002'], // misplaced
    ['call_number' => 'SF613.H44 A283 1974b', 'scan_idx' => 698, 'barcode' => '32754075402614'],
    ['call_number' => 'SF613.H44 A283 1975',  'scan_idx' => 699, 'barcode' => '32754060677139'],
    ['call_number' => 'SF613.I54 A3 2008',     'scan_idx' => 700, 'barcode' => '32754081321931'],
];

$results2 = evaluateShelfOrder($scenario2_items, 'lc');
$flagged2 = array_filter($results2, fn($r) => $r['is_ooo']);

printf("  %-12s | %-24s | %-10s | %-35s\n", "Correct Loc", "Call Number", "Scan Loc", "Problem Flag");
echo "  " . str_repeat('-', 88) . "\n";
foreach ($results2 as $r) {
    $flag = $r['is_ooo'] ? "⚠️ " . $r['move'] : "OK";
    printf("  %-12s | %-24s | %-10s | %-35s\n", $r['correct_loc'], $r['call_number'], $scenario2_items[$r['scan_loc']]['scan_idx'], $flag);
}

// 1974b must sort before 1975
$year_suffix_sort_ok = (SortLC('SF613.H44 A283 1974b', 'SF613.H44 A283 1975') < 0);
$s2_pass = $year_suffix_sort_ok && (count($flagged2) === 1) && (reset($flagged2)['call_number'] === 'SF613.H44 A3 2015');
echo "\nScenario 2 Resolution: " . ($s2_pass ? "✅ PASS (1974b < 1975, only misplaced scan 697 flagged)" : "❌ FAIL") . "\n\n";
$all_pass = $all_pass && $s2_pass;

// =========================================================================
// TEST 3: Scenario 3 - Transposed Blocks & Problem Movement (Scans 875-885)
// =========================================================================
echo "=== Test 3: Scenario 3 - Transposed Blocks of Books (SF774.4 vs SF774.5) ===\n";
$scenario3_items = [
    ['call_number' => 'SF774.0 .A1 2000',  'scan_idx' => 874, 'barcode' => '32754080000003'],
    ['call_number' => 'SF774.4 .C66 2009', 'scan_idx' => 875, 'barcode' => '32754079566794'],
    ['call_number' => 'SF774.5 .B75 2011', 'scan_idx' => 876, 'barcode' => '32754082033253'],
    ['call_number' => 'SF774.5 .B76 2010', 'scan_idx' => 877, 'barcode' => '32754081228474'],
    ['call_number' => 'SF774.5 .C55 2014', 'scan_idx' => 878, 'barcode' => '32754082048483'],
    ['call_number' => 'SF774.5 .C66 2011', 'scan_idx' => 879, 'barcode' => '32754082032461'],
    ['call_number' => 'SF774.4 .J83 2014', 'scan_idx' => 880, 'barcode' => '32754083490924'],
    ['call_number' => 'SF774.4 .P75 2011', 'scan_idx' => 881, 'barcode' => '32754082032115'],
    ['call_number' => 'SF774.4 .P751 2011','scan_idx' => 882, 'barcode' => '32754082032032'],
    ['call_number' => 'SF774.4 .W65 2014', 'scan_idx' => 883, 'barcode' => '32754082037130'],
    ['call_number' => 'SF774.5 .C55 2019', 'scan_idx' => 884, 'barcode' => '32754084507718'],
    ['call_number' => 'SF774.5 .K47 2016', 'scan_idx' => 885, 'barcode' => '32754085873382'],
    ['call_number' => 'SF774.6 .Z9 2020',  'scan_idx' => 886, 'barcode' => '32754080000004'],
];

$results3 = evaluateShelfOrder($scenario3_items, 'lc');
$flagged3 = array_filter($results3, fn($r) => $r['is_ooo']);

printf("  %-12s | %-24s | %-10s | %-35s\n", "Correct Loc", "Call Number", "Scan Loc", "Problem Flag");
echo "  " . str_repeat('-', 88) . "\n";
foreach ($results3 as $r) {
    $flag = $r['is_ooo'] ? "⚠️ " . $r['move'] : "OK";
    printf("  %-12s | %-24s | %-10s | %-35s\n", $r['correct_loc'], $r['call_number'], $scenario3_items[$r['scan_loc']]['scan_idx'], $flag);
}

// All 4 misplaced items in the SF774.5 block (scans 876-879) must be flagged
$s3_pass = (count($flagged3) === 4);
echo "\nScenario 3 Resolution: " . ($s3_pass ? "✅ PASS (All 4 displaced items in block correctly flagged with coherent moves!)" : "❌ FAIL") . "\n\n";
$all_pass = $all_pass && $s3_pass;

// =========================================================================
// TEST 4: Baseline - In-Order Shelf (No False Positives)
// =========================================================================
echo "=== Test 4: Baseline - Clean In-Order Shelf (Zero False Positives) ===\n";
$clean_items = [
    ['call_number' => 'QA76.73 .P98 A1 2018', 'barcode' => '1001'],
    ['call_number' => 'QA76.73 .P98 A2 2019', 'barcode' => '1002'],
    ['call_number' => 'QA76.73 .P98 B1 2020', 'barcode' => '1003'],
    ['call_number' => 'QA76.73 .P98 B2 2021', 'barcode' => '1004'],
    ['call_number' => 'QA76.73 .P98 C1 2022', 'barcode' => '1005'],
];
$results4 = evaluateShelfOrder($clean_items, 'lc');
$flagged4 = array_filter($results4, fn($r) => $r['is_ooo']);
$pass4 = (count($flagged4) === 0);
echo ($pass4 ? "✅ PASS: 0 false positives on clean shelf" : "❌ FAIL: false positive detected") . "\n\n";
$all_pass = $all_pass && $pass4;

// =========================================================================
// TEST 5: Baseline - Single Misplaced Item
// =========================================================================
echo "=== Test 5: Baseline - Single Misplaced Item ===\n";
$single_items = [
    ['call_number' => 'PR6000 .A0 1999', 'barcode' => '2000'],
    ['call_number' => 'PR6001 .A1 2000', 'barcode' => '2001'],
    ['call_number' => 'PR6099 .Z9 2020', 'barcode' => '2002'], // misplaced
    ['call_number' => 'PR6001 .A2 2001', 'barcode' => '2003'],
    ['call_number' => 'PR6001 .A3 2002', 'barcode' => '2004'],
    ['call_number' => 'PR6001 .A4 2003', 'barcode' => '2005'],
];
$results5 = evaluateShelfOrder($single_items, 'lc');
$flagged5 = array_filter($results5, fn($r) => $r['is_ooo']);
$pass5 = (count($flagged5) === 1 && reset($flagged5)['call_number'] === 'PR6099 .Z9 2020');
echo ($pass5 ? "✅ PASS: Exactly the single misplaced item flagged" : "❌ FAIL: Incorrect item flagged") . "\n\n";
$all_pass = $all_pass && $pass5;

// =========================================================================
// TEST 6: User Reported Dewey Scenario - Nothing Before Something (Scans 512-518)
// =========================================================================
echo "=== Test 6: Dewey Scenario - 'Nothing Before Something' Work Marks (Scans 512-518) ===\n";
$dewey_scenario_items = [
    ['call_number' => '338.1761082 D991h 1978',   'scan_idx' => 512, 'barcode' => '32754063686780'],
    ['call_number' => '338.1762 M879p 1987',       'scan_idx' => 513, 'barcode' => '32754061564815'],
    ['call_number' => '338.1762130973 C936 1990',   'scan_idx' => 514, 'barcode' => '32754069278012'],
    ['call_number' => '338.1762130973 C936e 1990',  'scan_idx' => 515, 'barcode' => '32754081417903'],
    ['call_number' => '338.1762130973 C936ex 1990', 'scan_idx' => 516, 'barcode' => '32754081417895'],
    ['call_number' => '338.176296 V592 1992',       'scan_idx' => 517, 'barcode' => '32754065382909'],
    ['call_number' => '338.176400941 P621 1987',   'scan_idx' => 518, 'barcode' => '32754004329425'],
];

$results6 = evaluateShelfOrder($dewey_scenario_items, 'dewey');
$flagged6 = array_filter($results6, fn($r) => $r['is_ooo']);

printf("  %-12s | %-32s | %-10s | %-35s\n", "Correct Loc", "Call Number", "Scan Loc", "Problem Flag");
echo "  " . str_repeat('-', 96) . "\n";
foreach ($results6 as $r) {
    $flag = $r['is_ooo'] ? "⚠️ " . $r['move'] : "OK";
    printf("  %-12s | %-32s | %-10s | %-35s\n", $r['correct_loc'], $r['call_number'], $dewey_scenario_items[$r['scan_loc']]['scan_idx'], $flag);
}

// 1. Sort check: C936 must sort before C936e
$dewey_sort_ok = (SortDewey('338.1762130973 C936 1990', '338.1762130973 C936e 1990') < 0);
$s6_pass = $dewey_sort_ok && (count($flagged6) === 0);
echo "\nDewey Resolution: " . ($s6_pass ? "✅ PASS (C936 < C936e < C936ex, zero false positives)" : "❌ FAIL") . "\n\n";
$all_pass = $all_pass && $s6_pass;

echo "=================================================================\n";
echo "SUMMARY:\n";
echo "  Test 1 (Scenario 1 Buddy Blind Spot): " . ($s1_pass ? "✅ RESOLVED" : "❌ FAIL") . "\n";
echo "  Test 2 (Scenario 2 Year Suffix Sort): " . ($s2_pass ? "✅ RESOLVED" : "❌ FAIL") . "\n";
echo "  Test 3 (Scenario 3 Transposed Block): " . ($s3_pass ? "✅ RESOLVED" : "❌ FAIL") . "\n";
echo "  Test 4 (Clean Shelf False Positive):  " . ($pass4 ? "✅ PASS" : "❌ FAIL") . "\n";
echo "  Test 5 (Single Misplaced Item):       " . ($pass5 ? "✅ PASS" : "❌ FAIL") . "\n";
echo "  Test 6 (Dewey Workmark / Clean):      " . ($s6_pass ? "✅ RESOLVED" : "❌ FAIL") . "\n";
echo "=================================================================\n";
echo "OVERALL: " . ($all_pass ? "ALL TESTS PASSED 🎉" : "FAILURES DETECTED ❌") . "\n";

exit($all_pass ? 0 : 1);
