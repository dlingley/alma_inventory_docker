<?php
/**
 * Dewey Call Number Sort Regression Tests
 *
 * Run: docker compose exec app php test_sort.php
 *
 * Tests cover:
 *   1. Volume numbers (t.1, t.2, t.10) sort numerically
 *   2. Dewey class number ordering (709 < 759 < 867 < 868)
 *   3. Case-insensitive cutter work marks (Am15L before Am15p)
 *   4. Consistent cutter letter ordering (P69A before P69c)
 *   5. Comprehensive mixed sort
 */
require_once(__DIR__ . '/SortCallNumber.php');

$all_pass = true;

echo "=== Test 1: Volume number sorting ===\n";
$volume_tests = [
    '867 P69A t.1', '867 P69A t.2', '867 P69A t.3',
    '867 P69A t.9', '867 P69A t.10', '867 P69A t.11',
    '867 P69A t.19', '867 P69A t.20', '867 P69A t.25',
];
$shuffled = $volume_tests;
shuffle($shuffled);
usort($shuffled, 'SortDewey');
echo "Result:\n";
foreach ($shuffled as $i => $cn) echo "  " . ($i+1) . ". $cn\n";
$pass1 = ($shuffled === $volume_tests);
echo ($pass1 ? "✅ PASS" : "❌ FAIL") . "\n\n";
$all_pass = $all_pass && $pass1;

echo "=== Test 2: Class number ordering preserved ===\n";
$class_tests = [
    '709.04 M453',
    '759.06 E96',
    '759.1 H766',
    '867 P69A t.1',
    '868 A123',
];
$shuffled3 = $class_tests;
shuffle($shuffled3);
usort($shuffled3, 'SortDewey');
echo "Result:\n";
foreach ($shuffled3 as $i => $cn) echo "  " . ($i+1) . ". $cn\n";
$pass2 = ($shuffled3 === $class_tests);
echo ($pass2 ? "✅ PASS" : "❌ FAIL") . "\n\n";
$all_pass = $all_pass && $pass2;

echo "=== Test 3: Case-insensitive cutter work marks (Am15L before Am15p) ===\n";
$case_tests = [
    '868.09 Am12f 2002',
    '868.09 Am15L',
    '868.09 Am15p',
    '868.09 Am35 1993 v. 1',
];
$shuffled4 = $case_tests;
shuffle($shuffled4);
usort($shuffled4, 'SortDewey');
echo "Result:\n";
foreach ($shuffled4 as $i => $cn) echo "  " . ($i+1) . ". $cn\n";
$pass3 = ($shuffled4 === $case_tests);
echo ($pass3 ? "✅ PASS" : "❌ FAIL") . "\n";
echo "Normalized keys:\n";
foreach ($case_tests as $cn) {
    echo "  " . str_pad($cn, 30) . " => " . normalizeDewey($cn) . "\n";
}
echo "\n";
$all_pass = $all_pass && $pass3;

echo "=== Test 4: P69A sorts before P69c (consistent ! insertion) ===\n";
$cutter_tests = [
    '867 P343B 1999',
    '867 P343mS 2002',
    '867 P69A t.1',
    '867 P69A t.2',
    '867 P69c 1973',
    '867 P69ca 1977',
    '867 P69g 1970',
];
$shuffled5 = $cutter_tests;
shuffle($shuffled5);
usort($shuffled5, 'SortDewey');
echo "Result:\n";
foreach ($shuffled5 as $i => $cn) echo "  " . ($i+1) . ". $cn\n";
$pass4 = ($shuffled5 === $cutter_tests);
echo ($pass4 ? "✅ PASS" : "❌ FAIL") . "\n";
echo "Normalized keys:\n";
foreach ($cutter_tests as $cn) {
    echo "  " . str_pad($cn, 25) . " => " . normalizeDewey($cn) . "\n";
}
echo "\n";
$all_pass = $all_pass && $pass4;

echo "=== Test 5: Mixed comprehensive sort ===\n";
$full_tests = [
    '709.04 M453',
    '759.06 E96',
    '866.09 D713n t.1',
    '866.09 Ep609',
    '866.09 Ep61',
    '866.09 Es61',
    '867 P343B 1999',
    '867 P343mS 2002',
    '867 P69A t.1',
    '867 P69A t.2',
    '867 P69A t.10',
    '867 P69c 1973',
    '868.09 Am12f 2002',
    '868.09 Am15L',
    '868.09 Am15p',
    '868.09 Am35 1993 v. 1',
];
$shuffled6 = $full_tests;
shuffle($shuffled6);
usort($shuffled6, 'SortDewey');
echo "Result:\n";
foreach ($shuffled6 as $i => $cn) echo "  " . ($i+1) . ". $cn\n";
$pass5 = ($shuffled6 === $full_tests);
echo ($pass5 ? "✅ PASS" : "❌ FAIL") . "\n\n";
$all_pass = $all_pass && $pass5;

echo "========================================\n";
echo ($all_pass ? "✅ ALL TESTS PASSED!" : "❌ SOME TESTS FAILED") . "\n";
