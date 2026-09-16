<?php
/**
 * ShelfOrderEvaluator
 *
 * Evaluates shelf order anomalies using Longest Non-Decreasing Subsequence (LNDS).
 *
 * Mathematically determines the mutually-ordered "backbone" of books on the shelf,
 * eliminating the "buddy blind spot" where consecutive misplaced books shielded each
 * other from detection, while maintaining zero false positives on clean shelves.
 */
class ShelfOrderEvaluator
{
    /**
     * Evaluates scanned items against catalog-sorted items.
     *
     * @param array $sortednk       Array of items sorted by call number (catalog order)
     * @param array $unsortedArray  Array of items in physical scan order
     * @return array Array of evaluation results indexed by $catalogRank (matching $sortednk keys)
     */
    public static function evaluate(array $sortednk, array $unsortedArray): array
    {
        $n = count($sortednk);
        if ($n === 0) {
            return [];
        }

        // Map physical scan_loc => catalogRank
        $scanToRank = [];
        foreach ($sortednk as $catalogRank => $item) {
            $scanLoc = $item['scan_loc'] ?? null;
            if ($scanLoc !== null) {
                $scanToRank[(int)$scanLoc] = $catalogRank;
            }
        }

        // Physical scan positions in ascending order (0, 1, 2, ... N-1)
        $scanPositions = array_keys($scanToRank);
        sort($scanPositions);

        // Compute Longest Non-Decreasing Subsequence (LNDS)
        // O(N log N) patience sorting
        $tails = [];
        $tailScanIndices = [];
        $parent = [];

        foreach ($scanPositions as $sLoc) {
            $r = $scanToRank[$sLoc];
            // Upper bound search: find first element in tails strictly greater than r
            $low = 0;
            $high = count($tails) - 1;
            $pos = count($tails);
            while ($low <= $high) {
                $mid = intdiv($low + $high, 2);
                if ($tails[$mid] > $r) {
                    $pos = $mid;
                    $high = $mid - 1;
                } else {
                    $low = $mid + 1;
                }
            }
            $tails[$pos] = $r;
            $tailScanIndices[$pos] = $sLoc;
            $parent[$sLoc] = ($pos > 0) ? $tailScanIndices[$pos - 1] : -1;
        }

        // Reconstruct in-order scan indices (shelf backbone)
        $inOrderScanIndices = [];
        if (!empty($tailScanIndices)) {
            $curr = end($tailScanIndices);
            while ($curr !== -1 && $curr !== null) {
                $inOrderScanIndices[$curr] = true;
                $curr = $parent[$curr] ?? -1;
            }
        }

        // Build backbone entries sorted by rank / physical position
        $backbone = [];
        foreach ($scanPositions as $sLoc) {
            if (isset($inOrderScanIndices[$sLoc])) {
                $backbone[] = [
                    'scan_loc' => $sLoc,
                    'rank'     => $scanToRank[$sLoc]
                ];
            }
        }

        $evaluations = [];
        foreach ($sortednk as $catalogRank => $item) {
            $sLoc = (int)($item['scan_loc'] ?? 0);
            $isOOO = !isset($inOrderScanIndices[$sLoc]);

            $moveText = '';
            if ($isOOO) {
                // Find nearest preceding backbone item with rank < $catalogRank
                $prevBackbone = null;
                $nextBackbone = null;
                foreach ($backbone as $b) {
                    if ($b['rank'] < $catalogRank) {
                        $prevBackbone = $b;
                    } elseif ($b['rank'] > $catalogRank && $nextBackbone === null) {
                        $nextBackbone = $b;
                    }
                }

                if ($prevBackbone !== null) {
                    $targetScan = $prevBackbone['scan_loc'];
                    $diff = $targetScan - $sLoc;
                    if ($diff > 0) {
                        $moveText = 'Move item forward ' . $diff . ' ' . ($diff === 1 ? 'space' : 'spaces');
                    } elseif ($diff < 0) {
                        $backSpaces = max(1, abs($diff) - 1);
                        $moveText = 'Move item back ' . $backSpaces . ' ' . ($backSpaces === 1 ? 'space' : 'spaces');
                    } else {
                        $moveText = 'Move item forward 1 space';
                    }
                } elseif ($nextBackbone !== null) {
                    // Belongs before the first backbone item
                    $targetScan = $nextBackbone['scan_loc'];
                    $diff = $targetScan - $sLoc;
                    if ($diff < 0) {
                        $backSpaces = max(1, abs($diff));
                        $moveText = 'Move item back ' . $backSpaces . ' ' . ($backSpaces === 1 ? 'space' : 'spaces');
                    } else {
                        $moveText = 'Move item back 1 space';
                    }
                } else {
                    $moveText = 'Move item 1 space';
                }
            }

            $prevCn = $unsortedArray[$sLoc - 1]['call_number'] ?? '';
            $nextCn = $unsortedArray[$sLoc + 1]['call_number'] ?? '';

            $orderProblem = '';
            if ($isOOO) {
                $orderProblem = "**OUT OF ORDER**<BR>Item Currently Between:<BR><em>" . htmlspecialchars((string)$prevCn) . "</em> & <em>" . htmlspecialchars((string)$nextCn) . "</em><BR>" . $moveText . "<BR>";
            }

            $evaluations[$catalogRank] = [
                'is_ooo'        => $isOOO,
                'move'          => $moveText,
                'prev_cn'       => $prevCn,
                'next_cn'       => $nextCn,
                'order_problem' => $orderProblem,
                'correct_loc'   => $catalogRank + 1,
                'scanned_loc'   => $sLoc + 1,
            ];
        }

        return $evaluations;
    }
}
