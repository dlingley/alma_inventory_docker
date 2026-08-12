<?php
/**
 * Dewey & LC Call Number Sort Regression Tests
 *
 * Run: docker compose exec app php test_sort.php
 *   or: php test_sort.php
 *
 * Tests cover:
 *   Dewey:
 *     1. Volume numbers (t.1, t.2, t.10) sort numerically
 *     2. Dewey class number ordering (709 < 759 < 867 < 868)
 *     3. Case-insensitive cutter work marks (Am15L before Am15p)
 *     4. Consistent cutter letter ordering (P69A before P69c)
 *     5. Comprehensive mixed sort
 *   LC:
 *     6. Dot-prefixed second cutter (.G359) sorts by letter, not by ASCII dot
 *     7. Year-only call numbers (BP109 2010) sort before cuttered items at same class
 *     8. LC pairwise ordering spot-checks
 *     9. Bare single-letter LC classes (K .C845 R 1970) with no class number
 *    10. DVD/media-prefix call numbers are NOT parsed as bare LC classes
 */
require_once(__DIR__ . '/SortCallNumber.php');

$all_pass = true;

echo "=== Test 1: Volume number sorting ===\n";
$volume_tests = [
    '867 P69A t.1',
    '867 P69A t.2',
    '867 P69A t.3',
    '867 P69A t.9',
    '867 P69A t.10',
    '867 P69A t.11',
    '867 P69A t.19',
    '867 P69A t.20',
    '867 P69A t.25',
];
$shuffled = $volume_tests;
shuffle($shuffled);
usort($shuffled, 'SortDewey');
echo "Result:\n";
foreach ($shuffled as $i => $cn)
    echo "  " . ($i + 1) . ". $cn\n";
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
foreach ($shuffled3 as $i => $cn)
    echo "  " . ($i + 1) . ". $cn\n";
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
foreach ($shuffled4 as $i => $cn)
    echo "  " . ($i + 1) . ". $cn\n";
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
foreach ($shuffled5 as $i => $cn)
    echo "  " . ($i + 1) . ". $cn\n";
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
foreach ($shuffled6 as $i => $cn)
    echo "  " . ($i + 1) . ". $cn\n";
$pass5 = ($shuffled6 === $full_tests);
echo ($pass5 ? "✅ PASS" : "❌ FAIL") . "\n\n";
$all_pass = $all_pass && $pass5;

echo "=== Pairwise sort order tests ===\n";
$colon_pair_tests = [
    ["866.09 N75n t.2:bk.1", "866.09 N75n t.3"],
    ["868.03 P191d t.1:pt.1-2", "868.03 P191d t.2"],
    ["868.03 P191d t.1:pt.1", "868.03 P191d t.1:pt.2"],
    ["865.09 An13 v.44:iss.1 2019", "865.09 An13 v.44:iss.2 2019"],
    ["865.09 An13 v.44:iss.1 2019", "865.09 An13 v.45:iss.1 2020"],
    ["866.09 N75n t.2", "866.09 N75n t.2:bk.1"],
    ["866.09 N75n t.2:bk.1", "866.09 N75n t.2:bk.2"],
];

foreach ($colon_pair_tests as $pair) {
    $earlier = $pair[0];
    $later = $pair[1];
    $n_early = normalizeDewey($earlier);
    $n_later = normalizeDewey($later);
    $passed = strcmp($n_early, $n_later) < 0;
    $status = $passed ? "✅ PASS" : "❌ FAIL";
    echo "  $status: '$earlier' < '$later'\n";
    if (!$passed) {
        echo "         got: $n_early\n";
        echo "          vs: $n_later\n";
    }
    $all_pass = $all_pass && $passed;
}

echo "\n=== Padding consistency tests ===\n";
$pairs = [
    ["866.09 N75n t.2:bk.1", "866.09 N75n t.10:bk.1"],
    ["868.03 P191d t.1:pt.2", "868.03 P191d t.1:pt.10"],
];
foreach ($pairs as $pair) {
    $earlier = $pair[0];
    $later = $pair[1];
    $n_early = normalizeDewey($earlier);
    $n_later = normalizeDewey($later);
    $passed = strcmp($n_early, $n_later) < 0;
    $status = $passed ? "✅ PASS" : "❌ FAIL";
    echo "  $status: '$earlier' < '$later'\n";
    if (!$passed) {
        echo "         got: $n_early\n";
        echo "          vs: $n_later\n";
    }
    $all_pass = $all_pass && $passed;
}

// ============================================================
// LC-specific tests
// ============================================================

echo "\n=== Test 6: Dot-prefixed second cutter sorts by letter, not ASCII dot ===\n";
// Bug: 'BL2532.R37 .G359 2024' was normalizing to '.G359' key, which sorts
// before 'B37' and 'C47' because '.' (ASCII 46) < any letter (A=65).
// Fix: strip leading dot from second cutter in NormalizeLC().
$dot_cutter_tests = [
    'BL2532.R37 B37 1997',
    'BL2532.R37 C47 1994',
    'BL2532.R37 .G359 2024',
    'BL2532.R37 K43 2020',
];
$shuffled_dc = $dot_cutter_tests;
shuffle($shuffled_dc);
usort($shuffled_dc, 'SortLC');
echo "Result:\n";
foreach ($shuffled_dc as $i => $cn)
    echo "  " . ($i + 1) . ". $cn\n";
$pass6 = ($shuffled_dc === $dot_cutter_tests);
echo ($pass6 ? "✅ PASS" : "❌ FAIL") . "\n";
echo "Normalized keys:\n";
foreach ($dot_cutter_tests as $cn)
    echo "  " . str_pad($cn, 28) . " => " . NormalizeLC($cn) . "\n";
echo "\n";
$all_pass = $all_pass && $pass6;

echo "=== Test 7: Year-only LC call numbers sort before cuttered items at same class ===\n";
// Bug: 'BP109 2010' (no cutter, year only) had whitespace stripped producing
// 'BP1092010' — a fake class number of 1,092,010 that sorted after 'BP395'.
// Fix: tilde sentinel before stripping whitespace keeps year in the_trimmings,
// which sorts before any cutter letter (space ASCII 32 < A ASCII 65).
$year_only_tests = [
    'BP75 .L56 1983',
    'BP109 1965',    // year-only, earlier edition
    'BP109 2010',    // year-only, later edition
    'BP109 .K45 1991', // same class, but has a cutter — sorts after year-only
    'BP395.G73 M67 1982',
];
$shuffled_yo = $year_only_tests;
shuffle($shuffled_yo);
usort($shuffled_yo, 'SortLC');
echo "Result:\n";
foreach ($shuffled_yo as $i => $cn)
    echo "  " . ($i + 1) . ". $cn\n";
$pass7 = ($shuffled_yo === $year_only_tests);
echo ($pass7 ? "✅ PASS" : "❌ FAIL") . "\n";
echo "Normalized keys:\n";
foreach ($year_only_tests as $cn)
    echo "  " . str_pad($cn, 25) . " => " . NormalizeLC($cn) . "\n";
echo "\n";
$all_pass = $all_pass && $pass7;

echo "=== Test 8: LC pairwise ordering spot-checks ===\n";
$lc_pair_tests = [
    // Basic class number ordering
    ['BL2532.R37 B37 1997',   'BL2532.R37 K43 2020'],
    // Dot-cutter vs plain cutter at same class
    ['BL2532.R37 C47 1994',   'BL2532.R37 .G359 2024'],
    ['BL2532.R37 .G359 2024', 'BL2532.R37 K43 2020'],
    // Year-only before cuttered at same class number
    ['BP109 2010',             'BP109 .K45 1991'],
    ['BP109 1965',             'BP109 2010'],
    // Cross-class ordering
    ['BP109 2010',             'BP395.G73 M67 1982'],
];
foreach ($lc_pair_tests as $pair) {
    [$earlier, $later] = $pair;
    $n_early = NormalizeLC($earlier);
    $n_later = NormalizeLC($later);
    $passed  = strcmp($n_early, $n_later) < 0;
    $status  = $passed ? "✅ PASS" : "❌ FAIL";
    echo "  $status: '$earlier' < '$later'\n";
    if (!$passed) {
        echo "         got: $n_early\n";
        echo "          vs: $n_later\n";
    }
    $all_pass = $all_pass && $passed;
}

echo "=== Test 9: Bare single-letter LC class (no class number) ===\n";
// Bug: 'K .C845 R 1970 v. 1' failed the NormalizeLC regex (\d+ required at least
// one digit after the letter class). These books got the unparsable sentinel ' '
// and sorted before JC, KF, etc. — completely wrong.
// Fix: change \d+ to \d* so the class number is optional.
$bare_class_tests = [
    'JC585 .P32 1991 v.1',     // JC section (J < K alphabetically)
    'JC599.U5 A45 2019',
    'K .C845 R 1970 v. 1',    // K section, no class number — sorts after JC, before KF
    'K .C845 R 1970 v. 2',    // same cutter, later volume
    'KF4749 .M38 2015',        // KF section (after K)
];
$shuffled_bc = $bare_class_tests;
shuffle($shuffled_bc);
usort($shuffled_bc, 'SortLC');
echo "Result:\n";
foreach ($shuffled_bc as $i => $cn)
    echo "  " . ($i + 1) . ". $cn\n";
$pass9 = ($shuffled_bc === $bare_class_tests);
echo ($pass9 ? "✅ PASS" : "❌ FAIL") . "\n";
echo "Normalized keys:\n";
foreach ($bare_class_tests as $cn)
    echo "  " . str_pad($cn, 26) . " => " . NormalizeLC($cn) . "\n";
echo "\n";
$all_pass = $all_pass && $pass9;

echo "=== Test 10: DVD/media prefix call numbers not caught by bare-class fallback ===\n";
// Bug guard: the broad \d* fix accidentally parsed 'DVD D21.3 .E54 2006' as a
// single-letter bare class (D=initial_letters). This caused 17 extra OOO in the
// IMC DVD file. The narrow fix requires [A-Z]{1} followed immediately by a dot,
// excluding any multi-letter prefix like DVD, VHS, CD, etc.
// Verify: all DVD items normalize to the same unparsable sentinel ' ',
// and the K-class items still sort correctly around them.
$dvd_calls = [
    'DVD CC165 .O97 1993 disc 4',
    'DVD D21.3 .E54 2006 disc 1',
    'DVD D21.3 .E54 2006 disc 2',
    'DVD D128 .S42 2004',
    'DVD CT275.C4623 I75 2007',
];
echo "DVD items all get the same unparsable sentinel (stable sort preserves input order):\n";
$pass10 = true;
foreach ($dvd_calls as $cn) {
    $norm = NormalizeLC($cn);
    $is_sentinel = ($norm === ' ');
    echo "  " . ($is_sentinel ? '✅' : '❌') . " NormalizeLC(\"$cn\") => \"$norm\"\n";
    $pass10 = $pass10 && $is_sentinel;
}
// Also confirm K .C845 still parses correctly (not regressed)
$k_norm = NormalizeLC('K .C845 R 1970 v. 1');
$k_ok   = ($k_norm !== ' ' && str_starts_with($k_norm, 'K'));
echo "  " . ($k_ok ? '✅' : '❌') . " NormalizeLC(\"K .C845 R 1970 v. 1\") => \"$k_norm\"\n";
$pass10 = $pass10 && $k_ok;
echo ($pass10 ? "✅ PASS" : "❌ FAIL") . "\n\n";
$all_pass = $all_pass && $pass10;

echo "\n========================================\n";
echo ($all_pass ? "✅ ALL TESTS PASSED!" : "❌ SOME TESTS FAILED") . "\n";
