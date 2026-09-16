<?php
require_once __DIR__ . '/../SortCallNumber.php';

$outputDir = __DIR__ . '/fixtures/cache_history/output';

$worseFiles = [
    'ShelfList_bcc_bcc_JC58_KF22_20240723.csv'        => '96 items, 0->2 OOO (LC)',
    'ShelfList_hsse_hss2lc_GT40_GV16_20250228.csv'    => '849 items, 23->24 OOO (LC)',
    'ShelfList_hsse_hss3lc_N624_N653_20240318.csv'     => '231 items, 10->14 OOO (LC)',
    'ShelfList_hsse_hss3_7092_7817_20260331.csv'       => '1429 items, 135->148 OOO (Dewey)',
];

foreach ($worseFiles as $file => $label) {
    echo "\n" . str_repeat('=', 65) . "\n";
    echo "FILE: $file\n$label\n";
    echo str_repeat('=', 65) . "\n";

    $path = "$outputDir/$file";
    if (!file_exists($path)) { echo "  NOT FOUND\n"; continue; }

    $fh = fopen($path, 'r');
    $header = fgetcsv($fh, 0, ',', '"', '\\');
    $callIdx  = array_search('call_number', $header);
    $titleIdx = array_search('title', $header);
    $rows = [];
    while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false)
        $rows[] = $row;
    fclose($fh);

    $isDewey = (str_contains($file, 'hss3_') || str_contains($file, 'vetm')) && !str_contains($file, 'lc');
    $sortFn  = $isDewey ? 'SortDewey' : 'SortLC';
    $normFn  = $isDewey ? 'normalizeDewey' : 'NormalizeLC';

    $oldCalls = array_map(fn($r) => trim($r[$callIdx] ?? ''), $rows);
    $items = array_map(fn($r) => ['call' => trim($r[$callIdx] ?? ''), 'title' => substr(trim($r[$titleIdx] ?? ''), 0, 35)], $rows);
    usort($items, fn($a,$b) => $sortFn($a['call'], $b['call']));
    $newCalls = array_column($items, 'call');

    $scanPos = array_flip($oldCalls);

    $isOOO = function(array $sorted, array $sp, int $i) {
        $pos  = $sp[$sorted[$i]]        ?? PHP_INT_MAX;
        $prev = isset($sorted[$i-1]) ? ($sp[$sorted[$i-1]] ?? PHP_INT_MAX) : PHP_INT_MAX;
        $next = isset($sorted[$i+1]) ? ($sp[$sorted[$i+1]] ?? PHP_INT_MAX) : PHP_INT_MAX;
        return abs($pos - $prev) !== 1 && abs($pos - $next) !== 1;
    };

    $newOOOSet = [];
    for ($i = 1; $i < count($newCalls) - 1; $i++)
        if ($isOOO($newCalls, $scanPos, $i)) $newOOOSet[$newCalls[$i]] = $i;

    $scanPosForOld = array_flip($newCalls);
    $oldOOOSet = [];
    for ($i = 1; $i < count($oldCalls) - 1; $i++)
        if ($isOOO($oldCalls, $scanPosForOld, $i)) $oldOOOSet[$oldCalls[$i]] = $i;

    $newOnly = array_diff_key($newOOOSet, $oldOOOSet);
    $oldOnly = array_diff_key($oldOOOSet, $newOOOSet);

    echo "\nNew OOO not caught by old app (" . count($newOnly) . "):\n";
    foreach ($newOnly as $call => $si) {
        $prev = $newCalls[$si-1] ?? '?';
        $next = $newCalls[$si+1] ?? '?';
        $pos  = $scanPos[$call] ?? '?';
        $pp   = $scanPos[$prev] ?? '?';
        $np   = $scanPos[$next] ?? '?';
        echo "  * \"$call\" (shelf:$pos sorted:$si)\n";
        echo "    prev: \"$prev\" (shelf:$pp)\n";
        echo "    next: \"$next\" (shelf:$np)\n";
        echo "    norm: " . $normFn($call) . "\n";
    }

    echo "\nOld OOO now fixed by new app (" . count($oldOnly) . "):\n";
    $n = 0;
    foreach ($oldOnly as $call => $idx) {
        echo "  - \"$call\"\n";
        if (++$n >= 3) { echo "  ...and " . (count($oldOnly)-3) . " more\n"; break; }
    }
}
