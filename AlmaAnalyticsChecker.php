<?php
/**
 * Alma Analytics Call Number Comparison Checker Engine
 *
 * Queries Alma Analytics REST API using sawx XML filters to fetch Alma's
 * normalized call numbers and server-side sort order, then performs a
 * side-by-side comparison against our local SortCallNumber.php logic.
 */

require_once __DIR__ . '/key.php';
require_once __DIR__ . '/SortCallNumber.php';

class AlmaAnalyticsChecker
{
    private string $apiKey;
    private string $reportPath;
    private int $batchSize;

    public function __construct(?string $apiKey = null, ?string $reportPath = null, int $batchSize = 50)
    {
        $this->apiKey     = $apiKey ?: (defined('ALMA_ANALYTICS_API_KEY') ? ALMA_ANALYTICS_API_KEY : ALMA_SHELFLIST_API_KEY);
        $this->reportPath = $reportPath ?: (defined('ALMA_ANALYTICS_REPORT_PATH') ? ALMA_ANALYTICS_REPORT_PATH : '/shared/Purdue University/Reports/CallNumberSortCheck');
        $this->batchSize  = max(1, min($batchSize, 100));
    }

    /**
     * Build Siebel/OBIEE Analytics sawx XML filter expression for barcodes
     */
    public function buildBarcodeFilterXml(array $barcodes): string
    {
        $barcodes = array_values(array_unique(array_filter(array_map('trim', $barcodes))));
        if (empty($barcodes)) {
            return '';
        }

        $op = (count($barcodes) === 1) ? 'equal' : 'in';

        $xml = '<sawx:expr xsi:type="sawx:comparison" op="' . $op . '" ' .
            'xmlns:saw="com.siebel.analytics.web/report/v1.1" ' .
            'xmlns:sawx="com.siebel.analytics.web/expression/v1.1" ' .
            'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ' .
            'xmlns:xsd="http://www.w3.org/2001/XMLSchema">' .
            '<sawx:expr xsi:type="sawx:sqlExpression">"Physical Items"."Barcode"</sawx:expr>';

        foreach ($barcodes as $bc) {
            $xml .= '<sawx:expr xsi:type="xsd:string">' . htmlspecialchars($bc, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</sawx:expr>';
        }

        $xml .= '</sawx:expr>';
        return $xml;
    }

    /**
     * Query Alma Analytics API for a batch of barcodes
     *
     * @param array $barcodes List of barcode strings
     * @return array Map of barcode => ['barcode' => ..., 'call_number' => ..., 'norm_call_number' => ...]
     */
    public function fetchAnalyticsData(array $barcodes): array
    {
        $cleanBarcodes = array_values(array_unique(array_filter(array_map('trim', $barcodes))));
        if (empty($cleanBarcodes)) {
            return ['success' => true, 'data' => [], 'errors' => []];
        }

        $chunks = array_chunk($cleanBarcodes, $this->batchSize);
        $results = [];
        $errors = [];

        foreach ($chunks as $chunkIndex => $chunk) {
            $filterXml = $this->buildBarcodeFilterXml($chunk);
            $baseUrl   = 'https://api-na.hosted.exlibrisgroup.com/almaws/v1/analytics/reports';
            $params    = [
                'path'   => $this->reportPath,
                'apikey' => $this->apiKey,
                'filter' => $filterXml,
                'limit'  => 1000
            ];

            $requestUrl = $baseUrl . '?' . http_build_query($params);
            $response = $this->executeCurl($requestUrl);

            if (!$response['success']) {
                $errors[] = "Batch " . ($chunkIndex + 1) . " API error: " . $response['error'];
                continue;
            }

            $parsed = $this->parseAnalyticsXml($response['body']);
            if (!$parsed['success']) {
                $errors[] = "Batch " . ($chunkIndex + 1) . " XML parse error: " . $parsed['error'];
                continue;
            }

            foreach ($parsed['rows'] as $row) {
                if (!empty($row['barcode'])) {
                    $results[$row['barcode']] = $row;
                }
            }
        }

        return [
            'success' => empty($errors) || !empty($results),
            'data'    => $results,
            'errors'  => $errors
        ];
    }

    /**
     * Parse Alma Analytics XML response payload
     */
    public function parseAnalyticsXml(string $xmlContent): array
    {
        libxml_use_internal_errors(true);

        // Remove XML namespaces for easy SimpleXML traversal
        $cleanedXmlStr = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xmlContent);
        $xml = simplexml_load_string($cleanedXmlStr);

        if ($xml === false) {
            $errors = [];
            foreach (libxml_get_errors() as $err) {
                $errors[] = trim($err->message);
            }
            libxml_clear_errors();
            return ['success' => false, 'error' => 'XML parse failed: ' . implode('; ', $errors)];
        }

        // Check for Alma API error response
        if (isset($xml->errorsExist) && (string)$xml->errorsExist === 'true') {
            $msg = (string)($xml->errorList->error->errorMessage ?? 'Unknown Alma API error');
            return ['success' => false, 'error' => $msg];
        }

        $rows = [];

        // Check if ResultXml contains an inner CDATA string
        if (isset($xml->ResultXml)) {
            $innerStr = trim((string)$xml->ResultXml);
            if (!empty($innerStr) && str_contains($innerStr, '<')) {
                $innerXml = simplexml_load_string(preg_replace('/xmlns[^=]*="[^"]*"/i', '', $innerStr));
                if ($innerXml !== false) {
                    $xml = $innerXml;
                }
            }
        } elseif (isset($xml->QueryResult->ResultXml)) {
            $innerStr = trim((string)$xml->QueryResult->ResultXml);
            if (!empty($innerStr) && str_contains($innerStr, '<')) {
                $innerXml = simplexml_load_string(preg_replace('/xmlns[^=]*="[^"]*"/i', '', $innerStr));
                if ($innerXml !== false) {
                    $xml = $innerXml;
                }
            }
        }

        // Locate Row elements using XPath or direct children
        $rowNodes = $xml->xpath('//Row') ?: [];

        foreach ($rowNodes as $row) {
            $rowArray = (array)$row;
            $cols = array_values($rowArray);

            $barcode = trim($row->Column0 ?? $row->Barcode ?? $cols[0] ?? '');
            $callNo  = trim($row->Column1 ?? $row->CallNumber ?? $cols[1] ?? '');
            $normKey = trim($row->Column2 ?? $row->NormalizedCallNumber ?? $cols[2] ?? '');

            if ($barcode !== '') {
                $rows[] = [
                    'barcode'          => (string)$barcode,
                    'call_number'      => (string)$callNo,
                    'norm_call_number' => (string)$normKey
                ];
            }
        }

        return ['success' => true, 'rows' => $rows];
    }

    /**
     * Run full side-by-side comparison between local sort engine and Alma Analytics
     *
     * @param array $items Array of items: [['barcode' => ..., 'call_number' => ..., 'title' => ...], ...]
     * @param string $callNumberType 'LC' or 'Dewey'
     * @return array Comparison report details and stats
     */
    public function compare(array $items, string $callNumberType = 'LC'): array
    {
        $barcodes = array_column($items, 'barcode');
        $analyticsResult = $this->fetchAnalyticsData($barcodes);

        $analyticsMap = $analyticsResult['data'];
        $apiErrors    = $analyticsResult['errors'];

        // Normalize call numbers locally
        $localProcessed = [];
        foreach ($items as $idx => $item) {
            $cn = $item['call_number'] ?? '';
            $normLocal = ($callNumberType === 'Dewey') ? normalizeDewey($cn) : NormalizeLC($cn);

            $bc = $item['barcode'] ?? ('item_' . $idx);
            $almaData = $analyticsMap[$bc] ?? null;

            $localProcessed[] = [
                'orig_index'        => $idx,
                'barcode'           => $bc,
                'title'             => $item['title'] ?? '',
                'call_number'       => $cn,
                'local_norm_key'    => $normLocal,
                'alma_call_number'  => $almaData['call_number'] ?? null,
                'alma_norm_key'     => $almaData['norm_call_number'] ?? null,
                'found_in_analytics'=> ($almaData !== null)
            ];
        }

        // Sort items using local sort engine
        $localSorted = $localProcessed;
        if ($callNumberType === 'Dewey') {
            usort($localSorted, function ($a, $b) {
                return SortDewey($a['call_number'], $b['call_number']);
            });
        } else {
            usort($localSorted, function ($a, $b) {
                return SortLC($a['call_number'], $b['call_number']);
            });
        }

        // Sort items using Alma Analytics normalized keys (if available)
        $almaSorted = $localProcessed;
        usort($almaSorted, function ($a, $b) {
            $keyA = $a['alma_norm_key'] ?? $a['local_norm_key'];
            $keyB = $b['alma_norm_key'] ?? $b['local_norm_key'];
            return strcmp($keyA, $keyB);
        });

        // Compute sequence rank matches
        $totalItems = count($localProcessed);
        $foundCount = 0;
        $rankMatches = 0;
        $discrepancies = [];

        for ($i = 0; $i < $totalItems; $i++) {
            if ($localProcessed[$i]['found_in_analytics']) {
                $foundCount++;
            }

            $localBc = $localSorted[$i]['barcode'] ?? '';
            $almaBc  = $almaSorted[$i]['barcode'] ?? '';

            if ($localBc === $almaBc) {
                $rankMatches++;
            } else {
                $discrepancies[] = [
                    'rank'               => $i + 1,
                    'local_item'         => $localSorted[$i],
                    'alma_item'          => $almaSorted[$i],
                    'local_pos_in_alma'  => $this->findBarcodePos($almaSorted, $localBc),
                    'alma_pos_in_local'  => $this->findBarcodePos($localSorted, $almaBc),
                ];
            }
        }

        $rankMatchPercent = ($totalItems > 0) ? round(($rankMatches / $totalItems) * 100, 1) : 0;

        return [
            'total_items'        => $totalItems,
            'analytics_found'    => $foundCount,
            'rank_matches'       => $rankMatches,
            'rank_match_percent' => $rankMatchPercent,
            'local_sorted'       => $localSorted,
            'alma_sorted'        => $almaSorted,
            'discrepancies'      => $discrepancies,
            'api_errors'         => $apiErrors
        ];
    }

    private function findBarcodePos(array $list, string $barcode): int
    {
        foreach ($list as $pos => $item) {
            if (($item['barcode'] ?? '') === $barcode) {
                return $pos + 1;
            }
        }
        return -1;
    }

    private function executeCurl(string $url): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AlmaInventoryApp/2.0');

        // Proxy support
        $proxy = getenv('HTTP_PROXY') ?: getenv('HTTPS_PROXY');
        if (!empty($proxy)) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }

        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code >= 400) {
            return [
                'success' => false,
                'error'   => "HTTP $code: " . ($err ?: substr(strip_tags((string)$body), 0, 200))
            ];
        }

        return ['success' => true, 'body' => (string)$body];
    }
}
