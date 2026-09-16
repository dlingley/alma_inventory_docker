<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../SortCallNumber.php';
require_once __DIR__ . '/../ShelfOrderEvaluator.php';

/**
 * ShelfOrderDetectionTest
 *
 * Test suite for shelf order position evaluation in Alma Inventory.
 * Verifies resolution of user-reported issues:
 *   - Scenario 1 (Buddy Blind Spot): Consecutive misplaced books (scans 525-526)
 *   - Scenario 2 (Year suffix order): LC year letter suffixes (1974b vs 1975)
 *   - Scenario 3 (Block transposition): Transposed blocks of call numbers (scans 875-885)
 *   - Clean shelf baseline (no false positives)
 *   - Single misplaced item baseline
 *   - Dewey work marks (nothing before something: C936 vs C936e)
 */
class ShelfOrderDetectionTest extends TestCase
{
    /**
     * Helper: Runs ShelfOrderEvaluator order detection algorithm
     *
     * @param array $scannedItems List of items in physical scan order. Each item: ['call_number' => string, 'barcode' => string]
     * @param string $cnType 'lc' or 'dewey'
     * @return array Annotated items in sorted catalog order, with 'is_ooo' and 'move' fields
     */
    public static function evaluateShelfOrder(array $scannedItems, string $cnType = 'lc'): array
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

    /**
     * Test Scenario 1: User Reported Scans 523-531
     *
     * Scanned items:
     *   523: RG480.S7 P65 2013     (correct: 524)
     *   524: RG493 .B64 2006       (correct: 525)
     *   525: RL95 .P35 2000        (correct: 530) <- MISPLACED in RG section
     *   526: RL95 .W44 2010        (correct: 531) <- MISPLACED in RG section
     *   527: RG493.5.B56 .P56 2018 (correct: 526)
     *   528: RG493.5.B56 R67 2006  (correct: 527)
     *   529: RL81 .H87 1998        (correct: 528)
     *   530: RL95 .L48 2009        (correct: 529)
     *   531: RL96 .D478 2010       (correct: 532)
     *
     * Resolution:
     * With ShelfOrderEvaluator (LNDS), both consecutive misplaced books (scans 525 & 526)
     * are correctly identified and flagged as out of order.
     */
    public function testScenario1_BuddyBlindSpot_Resolved(): void
    {
        $items = [
            ['call_number' => 'RG400 .A1 2010',        'scan_idx' => 522],
            ['call_number' => 'RG480.S7 P65 2013',     'scan_idx' => 523],
            ['call_number' => 'RG493 .B64 2006',       'scan_idx' => 524],
            ['call_number' => 'RL95 .P35 2000',        'scan_idx' => 525], // misplaced
            ['call_number' => 'RL95 .W44 2010',        'scan_idx' => 526], // misplaced
            ['call_number' => 'RG493.5.B56 .P56 2018', 'scan_idx' => 527],
            ['call_number' => 'RG493.5.B56 R67 2006',  'scan_idx' => 528],
            ['call_number' => 'RL81 .H87 1998',        'scan_idx' => 529],
            ['call_number' => 'RL95 .L48 2009',        'scan_idx' => 530],
            ['call_number' => 'RL96 .D478 2010',       'scan_idx' => 531],
            ['call_number' => 'RL99 .Z9 2012',         'scan_idx' => 532],
        ];

        $results = self::evaluateShelfOrder($items, 'lc');
        $flagged = array_filter($results, fn($r) => $r['is_ooo']);

        $this->assertCount(2, $flagged, 'Both misplaced RL95 books must be flagged');
        $flaggedCalls = array_values(array_column($flagged, 'call_number'));
        $this->assertContains('RL95 .P35 2000', $flaggedCalls);
        $this->assertContains('RL95 .W44 2010', $flaggedCalls);

        // Verify forward movement instructions
        foreach ($flagged as $f) {
            $this->assertStringContainsString('Move item forward', $f['move']);
        }
    }

    /**
     * Test Scenario 2: User Reported Scans 696-700
     *
     * Physical shelf scan:
     *   696: SF613.E45 A3 2013
     *   697: SF613.H44 A3 2015      <- Misplaced ahead of A283 books
     *   698: SF613.H44 A283 1974b   <- Correctly precedes 1975
     *   699: SF613.H44 A283 1975    <- Correctly follows 1974b
     *   700: SF613.I54 A3 2008
     *
     * True correct order:
     *   1. SF613.E45 A3 2013
     *   2. SF613.H44 A283 1974b
     *   3. SF613.H44 A283 1975
     *   4. SF613.H44 A3 2015
     *   5. SF613.I54 A3 2008
     */
    public function testScenario2_LCYearSuffixOrdering(): void
    {
        $items = [
            ['call_number' => 'SF613.E45 A3 2013'],
            ['call_number' => 'SF613.H44 A3 2015'],
            ['call_number' => 'SF613.H44 A283 1974b'],
            ['call_number' => 'SF613.H44 A283 1975'],
            ['call_number' => 'SF613.I54 A3 2008'],
        ];

        // 1974b must sort before 1975
        $cmp1974b_1975 = SortLC('SF613.H44 A283 1974b', 'SF613.H44 A283 1975');
        $this->assertLessThan(0, $cmp1974b_1975, 'SF613.H44 A283 1974b must sort before SF613.H44 A283 1975');

        // A283 cutter must sort before A3 cutter (0.283 < 0.3)
        $cmpA283_A3 = SortLC('SF613.H44 A283 1975', 'SF613.H44 A3 2015');
        $this->assertLessThan(0, $cmpA283_A3, 'SF613.H44 A283 1975 must sort before SF613.H44 A3 2015');

        // Full sort order
        usort($items, 'SortLCObject');
        $sorted = array_column($items, 'call_number');
        $this->assertEquals([
            'SF613.E45 A3 2013',
            'SF613.H44 A283 1974b',
            'SF613.H44 A283 1975',
            'SF613.H44 A3 2015',
            'SF613.I54 A3 2008',
        ], $sorted);

        // Evaluation: only scan 697 (A3 2015) is out of order
        $results = self::evaluateShelfOrder($items, 'lc');
        $flagged = array_filter($results, fn($r) => $r['is_ooo']);
        $this->assertCount(1, $flagged);
        $this->assertEquals('SF613.H44 A3 2015', reset($flagged)['call_number']);
    }

    /**
     * Test Scenario 3: User Reported Scans 875-885 (Block Transposition)
     *
     * Physical shelf scan:
     *   875: SF774.4 .C66 2009
     *   876: SF774.5 .B75 2011  \
     *   877: SF774.5 .B76 2010   |-- SF774.5 block (scanned before SF774.4)
     *   878: SF774.5 .C55 2014   |
     *   879: SF774.5 .C66 2011  /
     *   880: SF774.4 .J83 2014  \
     *   881: SF774.4 .P75 2011   |-- SF774.4 block
     *   882: SF774.4 .P751 2011  |
     *   883: SF774.4 .W65 2014  /
     *   884: SF774.5 .C55 2019
     *   885: SF774.5 .K47 2016
     *
     * Resolution:
     * All 4 misplaced items in the block are flagged with coherent move instructions.
     */
    public function testScenario3_BlockTransposition_Resolved(): void
    {
        $items = [
            ['call_number' => 'SF774.0 .A1 2000',  'scan_idx' => 874],
            ['call_number' => 'SF774.4 .C66 2009', 'scan_idx' => 875],
            ['call_number' => 'SF774.5 .B75 2011', 'scan_idx' => 876],
            ['call_number' => 'SF774.5 .B76 2010', 'scan_idx' => 877],
            ['call_number' => 'SF774.5 .C55 2014', 'scan_idx' => 878],
            ['call_number' => 'SF774.5 .C66 2011', 'scan_idx' => 879],
            ['call_number' => 'SF774.4 .J83 2014', 'scan_idx' => 880],
            ['call_number' => 'SF774.4 .P75 2011', 'scan_idx' => 881],
            ['call_number' => 'SF774.4 .P751 2011','scan_idx' => 882],
            ['call_number' => 'SF774.4 .W65 2014', 'scan_idx' => 883],
            ['call_number' => 'SF774.5 .C55 2019', 'scan_idx' => 884],
            ['call_number' => 'SF774.5 .K47 2016', 'scan_idx' => 885],
            ['call_number' => 'SF774.6 .Z9 2020',  'scan_idx' => 886],
        ];

        $results = self::evaluateShelfOrder($items, 'lc');
        $flagged = array_filter($results, fn($r) => $r['is_ooo']);

        // Exactly the 4 misplaced SF774.5 items are flagged
        $this->assertCount(4, $flagged);
        $flaggedCalls = array_values(array_column($flagged, 'call_number'));
        $this->assertContains('SF774.5 .B75 2011', $flaggedCalls);
        $this->assertContains('SF774.5 .B76 2010', $flaggedCalls);
        $this->assertContains('SF774.5 .C55 2014', $flaggedCalls);
        $this->assertContains('SF774.5 .C66 2011', $flaggedCalls);

        // All 4 have forward move instructions
        foreach ($flagged as $f) {
            $this->assertStringContainsString('Move item forward', $f['move']);
        }
    }

    /**
     * Negative control: Clean in-order shelf must produce 0 out-of-order flags
     */
    public function testNormalShelf_ZeroOutOfOrder(): void
    {
        $items = [
            ['call_number' => 'QA76.73 .P98 A1 2018', 'barcode' => '1001'],
            ['call_number' => 'QA76.73 .P98 A2 2019', 'barcode' => '1002'],
            ['call_number' => 'QA76.73 .P98 B1 2020', 'barcode' => '1003'],
            ['call_number' => 'QA76.73 .P98 B2 2021', 'barcode' => '1004'],
            ['call_number' => 'QA76.73 .P98 C1 2022', 'barcode' => '1005'],
        ];

        $results = self::evaluateShelfOrder($items, 'lc');
        $flagged = array_filter($results, fn($r) => $r['is_ooo']);
        $this->assertCount(0, $flagged, 'In-order shelf must have 0 out-of-order flags');
    }

    /**
     * Single misplaced book (baseline case):
     * Book Z is scanned between A1 and A2.
     * Only Z should be flagged.
     */
    public function testSingleMisplacedBook_Flagged(): void
    {
        $items = [
            ['call_number' => 'PR6000 .A0 1999', 'barcode' => '2000'],
            ['call_number' => 'PR6001 .A1 2000', 'barcode' => '2001'],
            ['call_number' => 'PR6099 .Z9 2020', 'barcode' => '2002'], // misplaced from end
            ['call_number' => 'PR6001 .A2 2001', 'barcode' => '2003'],
            ['call_number' => 'PR6001 .A3 2002', 'barcode' => '2004'],
            ['call_number' => 'PR6001 .A4 2003', 'barcode' => '2005'],
        ];

        $results = self::evaluateShelfOrder($items, 'lc');
        $flagged = array_filter($results, fn($r) => $r['is_ooo']);
        $this->assertCount(1, $flagged, 'Exactly 1 book should be flagged');
        $flaggedItem = reset($flagged);
        $this->assertEquals('PR6099 .Z9 2020', $flaggedItem['call_number']);
    }

    /**
     * User Report: Dewey "nothing before something" cutter work mark ordering (Scans 512-518)
     *
     * Physical shelf scan:
     *   512: 338.1761082 D991h 1978
     *   513: 338.1762 M879p 1987
     *   514: 338.1762130973 C936 1990     (no work mark - main work)
     *   515: 338.1762130973 C936e 1990    (work mark 'e')
     *   516: 338.1762130973 C936ex 1990   (work mark 'ex')
     *   517: 338.176296 V592 1992
     *   518: 338.176400941 P621 1987
     *
     * Resolution:
     * C936 sorts before C936e and C936ex. Zero false positives.
     */
    public function testDeweyScenario_NothingBeforeSomethingWorkmark_Resolved(): void
    {
        $items = [
            ['call_number' => '338.1761082 D991h 1978',   'scan_idx' => 512, 'barcode' => '32754063686780'],
            ['call_number' => '338.1762 M879p 1987',       'scan_idx' => 513, 'barcode' => '32754061564815'],
            ['call_number' => '338.1762130973 C936 1990',   'scan_idx' => 514, 'barcode' => '32754069278012'],
            ['call_number' => '338.1762130973 C936e 1990',  'scan_idx' => 515, 'barcode' => '32754081417903'],
            ['call_number' => '338.1762130973 C936ex 1990', 'scan_idx' => 516, 'barcode' => '32754081417895'],
            ['call_number' => '338.176296 V592 1992',       'scan_idx' => 517, 'barcode' => '32754065382909'],
            ['call_number' => '338.176400941 P621 1987',   'scan_idx' => 518, 'barcode' => '32754004329425'],
        ];

        $results = self::evaluateShelfOrder($items, 'dewey');
        $flagged = array_filter($results, fn($r) => $r['is_ooo']);

        // Zero false positives
        $this->assertCount(0, $flagged, 'Scan 514 (C936) must not be flagged when in proper nothing-before-something order');
    }
}
