<?php ob_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory Report Results</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script type="text/javascript" src="reformed/js/jquery.tablesorter.js"></script>
<script type="text/javascript">
    $(document).ready(function(){ $("#CNTable").tablesorter(); });
</script>
<style>
:root {
    --color-bg: #f0f2f5;
    --color-card: #ffffff;
    --color-header: #1e293b;
    --color-header-accent: #334155;
    --color-primary: #3b82f6;
    --color-primary-hover: #2563eb;
    --color-text: #1e293b;
    --color-text-secondary: #64748b;
    --color-border: #e2e8f0;
    --color-danger: #ef4444;
    --color-danger-light: #fef2f2;
    --color-success: #22c55e;
    --color-warning: #f59e0b;
    --color-warning-light: #fffbeb;
    --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -2px rgba(0,0,0,0.05);
    --shadow-lg: 0 10px 25px -3px rgba(0,0,0,0.08), 0 4px 6px -4px rgba(0,0,0,0.04);
    --radius-sm: 6px;
    --radius-md: 10px;
    --radius-lg: 16px;
    --font: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    --transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: var(--font);
    background: var(--color-bg);
    color: var(--color-text);
    min-height: 100vh;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
}
.header {
    background: linear-gradient(135deg, var(--color-header) 0%, var(--color-header-accent) 100%);
    padding: 1.75rem 1.5rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.header::before {
    content: '';
    position: absolute;
    top: -50%; left: -50%; width: 200%; height: 200%;
    background: radial-gradient(circle at 30% 50%, rgba(59,130,246,0.08) 0%, transparent 50%);
    pointer-events: none;
}
.header h1 { color: #fff; font-size: 1.5rem; font-weight: 700; position: relative; letter-spacing: -0.02em; }
.header h1 small { font-weight: 400; font-size: 0.8rem; opacity: 0.6; display: block; margin-top: 0.25rem; }
.header p { color: rgba(255,255,255,0.6); font-size: 0.8125rem; margin-top: 0.25rem; position: relative; }

.container { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }

/* Action bar */
.action-bar {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;
    margin: -1rem auto 1.25rem; max-width: 1200px; padding: 0 1rem; position: relative; z-index: 1;
}
.action-bar .barcode-count {
    background: var(--color-card); padding: 0.625rem 1rem; border-radius: var(--radius-md);
    box-shadow: var(--shadow-md); font-size: 0.8125rem; font-weight: 500;
}
.action-bar .barcode-count strong { color: var(--color-primary); }
.action-btn {
    display: inline-flex; align-items: center; gap: 0.375rem;
    padding: 0.5rem 1rem; border-radius: var(--radius-sm); font-family: var(--font);
    font-size: 0.8125rem; font-weight: 500; text-decoration: none; transition: all var(--transition); border: none; cursor: pointer;
}
.action-btn-primary { background: var(--color-primary); color: #fff; }
.action-btn-primary:hover { background: var(--color-primary-hover); transform: translateY(-1px); box-shadow: 0 2px 8px rgba(59,130,246,0.3); }
.action-btn-outline { background: var(--color-card); color: var(--color-text); border: 1.5px solid var(--color-border); }
.action-btn-outline:hover { border-color: var(--color-primary); color: var(--color-primary); }

/* Stat cards */
.stats-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem;
    margin-bottom: 1.25rem;
}
.stat-card {
    background: var(--color-card); border-radius: var(--radius-md); padding: 1rem 1.125rem;
    box-shadow: var(--shadow-sm); border-left: 3px solid var(--color-border); transition: all var(--transition);
}
.stat-card:hover { box-shadow: var(--shadow-md); }
.stat-card.stat-danger { border-left-color: var(--color-danger); }
.stat-card.stat-warning { border-left-color: var(--color-warning); }
.stat-card.stat-primary { border-left-color: var(--color-primary); }
.stat-card .stat-value { font-size: 1.5rem; font-weight: 700; color: var(--color-text); line-height: 1.2; }
.stat-card .stat-label { font-size: 0.7rem; font-weight: 500; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.125rem; }

.range-bar {
    background: var(--color-card); border-radius: var(--radius-md); padding: 0.75rem 1.125rem;
    box-shadow: var(--shadow-sm); margin-bottom: 1.25rem;
    display: flex; align-items: center; gap: 1.5rem; font-size: 0.8125rem; flex-wrap: wrap;
}
.range-bar .range-item { display: flex; align-items: center; gap: 0.375rem; }
.range-bar .range-label { color: var(--color-text-secondary); font-weight: 500; }
.range-bar .range-value { font-weight: 600; font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.75rem; background: var(--color-bg); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); }

/* Results table */
.table-card {
    background: var(--color-card); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg);
    overflow: hidden; margin-bottom: 2rem;
}
.results-table {
    width: 100%; border-collapse: collapse; font-size: 0.8125rem;
}
.results-table thead th {
    background: #f8fafc; padding: 0.75rem 0.875rem; text-align: left;
    font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--color-text-secondary); border-bottom: 2px solid var(--color-border);
    cursor: pointer; user-select: none; white-space: nowrap; position: sticky; top: 0; z-index: 2;
}
.results-table thead th:hover { color: var(--color-primary); }
.results-table tbody td { padding: 0.625rem 0.875rem; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
.results-table tbody tr { transition: background var(--transition); }
.results-table tbody tr:hover { background: #f8fafc; }
.results-table tbody tr:last-child td { border-bottom: none; }

/* Problem row */
.results-table tbody tr.row-problem { background: var(--color-danger-light); }
.results-table tbody tr.row-problem:hover { background: #fde8e8; }
.results-table tbody tr.row-problem td { font-weight: 500; }

.problem-badge {
    display: inline-block; padding: 0.15rem 0.5rem; border-radius: var(--radius-sm);
    font-size: 0.6875rem; font-weight: 600; line-height: 1.4; margin-bottom: 0.2rem;
}
.badge-order { background: #fef2f2; color: #dc2626; }
.badge-cn-type { background: #fff7ed; color: #ea580c; }
.badge-nip { background: #fefce8; color: #ca8a04; }
.badge-temp { background: #f0fdf4; color: #16a34a; }
.badge-library { background: #eff6ff; color: #2563eb; }
.badge-location { background: #faf5ff; color: #9333ea; }
.badge-policy { background: #fdf2f8; color: #db2777; }
.badge-type { background: #f0f9ff; color: #0284c7; }
.problem-detail { font-size: 0.75rem; color: var(--color-text-secondary); margin-top: 0.125rem; }
.problem-detail em { font-style: normal; font-weight: 600; color: var(--color-text); }

.col-order, .col-scanned { text-align: center; width: 60px; }
.col-cn { min-width: 140px; font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.75rem; }
.col-title { max-width: 200px; }
.col-barcode { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.75rem; color: var(--color-text-secondary); }

.footer { text-align: center; padding: 1rem; font-size: 0.75rem; color: var(--color-text-secondary); }

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .action-bar { flex-direction: column; align-items: stretch; }
    .results-table { font-size: 0.75rem; }
    .results-table thead th, .results-table tbody td { padding: 0.5rem; }
}
</style>
</head>
<body>

<?php
// Allow long-running barcode processing (large inventories can take many minutes)
set_time_limit(600);
//pre($_POST);
//Include XLSX Reader
include 'simplexlsx/simplexlsx.class.php';

//Ensure Authentication and load API Keys
//Uncomment line below to enable authentication after setting up login.php properly
//require("login.php");
require("SortCallNumber.php");
require("almaBarcodeAPI.php");

$shelflist = [];
$output_array = [];
$problem = false;
$orderProblem = '';
$cnTypeProblem = '';
$nipProblem = '';
$tempProblem = '';
$libraryProblem = '';
$locationProblem = '';
$policyProblem = '';
$typeProblem = '';
$orderProblemCount = 0;
$cnTypeProblemCount = 0;
$tempProblemCount = 0;
$requestProblemCount = 0;
$locationProblemCount = 0;
$libraryProblemCount = 0;
$policyProblemCount = 0;
$typeProblemCount = 0;

//Only run code below if form submitted
if (isset($_POST['submit'])) {
  //View Post Data Submitted
  //pre($_POST);
	//Clear cache directory if requested
    if ($_POST['clearCache'] == 'true') {
		foreach(glob("cache/barcodes/*") as $file)
		{
				unlink($file);
		}
   	}
    if (isset($_FILES["file"])) {

        //if there was an error uploading the file
        if ($_FILES["file"]["error"] > 0) {
            echo "Return Code: " . $_FILES["file"]["error"] . "<br />";

        } else {
            //Print file details
            // echo "Upload: " . $_FILES["file"]["name"] . "<br />";
            // echo "Type: " . $_FILES["file"]["type"] . "<br />";
            // echo "Size: " . ($_FILES["file"]["size"] / 1024) . " Kb<br />";
            // echo "Temp file: " . $_FILES["file"]["tmp_name"] . "<br />";

            //if file already exists
            if (file_exists("cache/upload/" . $_FILES["file"]["name"])) {
                //echo $_FILES["file"]["name"] . " already exists. ";
            } else {
                //Store file in directory "upload" with the name of "uploaded_file.txt"
                $storagename = 'uploaded_file_' . $_POST['library'] . '_' . $_POST['location'] . '_' . date('Ymd') .  '.xlsx';
                move_uploaded_file($_FILES["file"]["tmp_name"], "cache/upload/" . $storagename);
                //echo "Stored in: " . "cache/" . $_FILES["file"]["name"] . "<br />";
            }
        }
    } else {
        echo '<H1>Barcode.xlsx file not selected.</H1><BR>';
        echo '<a href="index.php"> Run New File</a><BR>';
        exit();
    }

    //Check Call # type need to implement other types
    if (isset($_POST['cnType']) && $_POST['cnType'] == 'other') {
        echo '<H1>Currently only Dewey and LC callnumber type supported.</H1><BR>';
        echo '<a href="index.php"> Run New File</a><BR>';
        exit();
    }


    if (file_exists("cache/upload/" . $storagename)) {
      $filelocation = "cache/upload/" . $storagename;
      $xlsx = new SimpleXLSX($filelocation);
      list($num_cols, $num_rows) = $xlsx->dimension();

        //load callNumber array and sort for printing below

        // --- Pass 1: read all barcodes from the spreadsheet in scan order ---
        // We collect them first so we can fetch them all in parallel batches
        // without ever losing the original row number (scan_loc).
        $barcodes_by_row = [];
        $row = 1;
        foreach ($xlsx->rows() as $k => $r) {
            if ($k == 0) {
                if ($r[0] == 'barcodes') {
                    continue;
                } else {
                    echo "Upload file must have header row labeled barcodes";
                    exit;
                }
            }
            $barcodes_by_row[$row] = $r[0];
            $row++;
        }

        // --- Pass 2: fetch all barcodes in parallel batches of 10 ---
        // retrieveBarcodesInBatch() returns results keyed by the same row numbers
        // passed in, so scan order is always preserved regardless of response order.
        $batch_size = 10;
        $all_item_data = [];
        $batch_keys = array_keys($barcodes_by_row);
        $total_barcodes = count($batch_keys);
        $processed = 0;

        foreach (array_chunk($batch_keys, $batch_size) as $chunk_row_nums) {
            $chunk = [];
            foreach ($chunk_row_nums as $r) {
                $chunk[$r] = $barcodes_by_row[$r];
            }

            $batch_results = retrieveBarcodesInBatch($chunk);
            $all_item_data += $batch_results; // keys are row numbers; order preserved

            $processed += count($chunk);
            $percentage = round($processed * 100 / $total_barcodes);

            $progress_id = isset($_POST['progress_id']) ? preg_replace('/[^a-zA-Z0-9_.]/', '', $_POST['progress_id']) : '';
            if ($progress_id) {
                $progress_data = array('percentage' => $percentage, 'job' => 'Retrieving Barcodes From API');
                if ($percentage >= 100) {
                    $progress_data['job'] = 'complete';
                    $progress_data['percentage'] = 100;
                }
                file_put_contents('/tmp/progress_' . $progress_id . '.json', json_encode($progress_data));
                error_log("process_barcodes loop - Progress ID: " . $progress_id . " Percentage: " . $percentage . " Processed: " . $processed);
            }
        }

        // --- Pass 3: normalise call numbers and build $unsorted ---
        // scan_loc is assigned from the original row key — never from response order.
        foreach ($all_item_data as $scan_row => $itemData) {
                //If Barcode Not Found Write Scanned Barcode to Item Object So it Will print on report
                if ($itemData->item_barcode == '')
                {
                  $itemData->item_barcode = $barcodes_by_row[$scan_row];
                  $itemData->title = 'NOT FOUND';
                  $itemData->call_sort = '!';
                }
                else {
                  //Barcode was found so we can store a normalized call number to use for sorting
                  //if call_number_type == 1 it should be dewey
                  if($itemData->call_number_type == 1)
                  {
                    $itemData->call_sort = normalizeDewey($itemData->call_number);
                  }
                  else {
                    $itemData->call_sort = normalizeLC($itemData->call_number);
                  }
                }
                //store to array for sorting, keyed by original scan row
                $unsorted[$scan_row] = $itemData;
                $unsorted[$scan_row]->scan_loc = $scan_row;
        }

        //pre($unsorted);
        //This converts arroy of stdClass objects to a mutlidimensional
        //array so we can sort using array sort
        $unsortedArray = json_decode(json_encode($unsorted), true);
        //pre($unsortedArray);
        $first = reset($unsortedArray);
        $last = end($unsortedArray);
        $first_call = $first['call_number'];
        //remove spaces and periods
        $first_call = strtr($first_call, array('.' => '', ' ' => ''));

        $last_call = $last['call_number'];
        $last_call = strtr($last_call, array('.' => '', ' ' => ''));

        //var_dump($first, $last, $first_call, $last_call);

        //Sort array and maintain original scan key order
        //Useful for caluculating difference between proper location and scan location
        $sortednk = $unsortedArray;
        //pre($sortednk);

        if ($_POST['cnType'] == 'dewey') {
          $sortednk_success = usort($sortednk, "SortDeweyObject");
      }
   else {
      $sortednk_success = usort($sortednk, "SortLCObject"); //sort by LC Call Number
      }

        //Sort without maintainin key order.  Just keeping for reference.
        //$sortedkey = $unsortedArray;
        //$sortedkey_success = uasort($sortedkey, "SortLCObject");

        //Start loop of processing records and writing to output array
        $previousCN = 1;
        foreach ($sortednk as $key => $number) {
          //pre($sortednk[$key]);
          $problem = false;

            //Don't flag order issues if only Other problems are requested
            if ($_POST['onlyother'] == 'false') {
                //Next two if statements take care of undefined offset issue
                if (!isset($sortednk[$key - 1]['scan_loc'])) {
                    $sortednk[$key - 1]['scan_loc'] = null;
                }
                if (!isset($sortednk[$key + 1]['scan_loc'])) {
                    $sortednk[$key + 1]['scan_loc'] = null;
                }
                $prevScan_loc = $sortednk[$key - 1]['scan_loc'] + 1;
                $scan_loc = $sortednk[$key]['scan_loc'] + 1;
                $nextScan_loc = $sortednk[$key + 1]['scan_loc'] + 1;
                $nextdiff = $nextScan_loc - $scan_loc;
                $prevdiff = $scan_loc - $prevScan_loc;

                if ($prevdiff != 1 && $nextdiff != 1) {

                    //Next two if statements take care of undefined offset issue
                    if (!isset($unsortedArray[$sortednk[$key]['scan_loc'] - 1])) {
                        $unsortedArray[$sortednk[$key]['scan_loc'] - 1] = null;
                    }
                    if (!isset($unsortedArray[$sortednk[$key]['scan_loc'] + 1])) {
                        $unsortedArray[$sortednk[$key]['scan_loc'] + 1] = null;
                    }

                    $move = $prevScan_loc - $scan_loc;
                    $prevScan_loc = 0;
                    $scan_loc = 0;
                    if ($move <0){
                      $move = 'Move item back '.(abs($move) -1)  . ' spaces';
                    }
                    else {
                      $move = 'Move item forward '.($move)  . ' spaces';
                    }

                    $orderProblem = "**OUT OF ORDER**<BR>Item Currently Between:<BR><em>" . $unsortedArray[$sortednk[$key]['scan_loc'] - 1]['call_number'] . "</em> & <em>" . $unsortedArray[$sortednk[$key]['scan_loc'] + 1]['call_number'] . "</em><BR>" . $move . "<BR>";
                    $orderProblemCount += 1;
                    $problem = true;


                } else {
                    $orderProblem = '';
                }
            }

            //Don't flag other issues if only order problems are requested
            if ($_POST['onlyorder'] == 'false') {
                if ($_POST['cnType'] == 'dewey'){
                $cntype = 1;
              }elseif ($_POST['cnType'] == 'lc') {
                $cntype = 0;
              }
                if ($sortednk[$key]['call_number_type'] != $cntype) {
                    $cnTypeProblem = "**WRONG CN TYPE**<BR>";
                    $cnTypeProblemCount += 1;
                    $problem = true;
                } else {
                    $cnTypeProblem = '';
                }

                if ($sortednk[$key]['status'] != 1) {
                    $nipProblem = "**NIP: " . $sortednk[$key]['process_type'] . "**<BR>";
                    $problem = true;
                } else {
                    $nipProblem = '';
                }

                if ($sortednk[$key]['in_temp_location'] != 'false') {
                    $tempProblem = "**IN TEMP LOC**<BR>";
                    $tempProblemCount += 1;
                    $problem = true;
                } else {
                    $tempProblem = '';
                }

                if ($sortednk[$key]['requested'] != 'false') {
                    $requestProblem = "**ITEM HAS REQUEST**<BR>";
                    $requestProblemCount +=1;
                    $problem = true;
                } else {
                    $requestProblem = '';
                }

                $location = $_POST['location'];
                if ($sortednk[$key]['location'] != $location) {
                    $locationProblem = "**WRONG LOCATION: " . $sortednk[$key]['location'] . "**<BR>";
                    $locationProblemCount += 1;
                    $problem = true;
                } else {
                    $locationProblem = '';
                }
                $library = $_POST['library'];
                if ($sortednk[$key]['library'] != $library) {
                    $libraryProblem = "**WRONG LIBRARY: " . $sortednk[$key]['library'] . "**<BR>";
                    $libraryProblemCount += 1;
                    $problem = true;
                } else {
                    $libraryProblem = '';
                }

                $policy = $_POST['policy'];
                if ($sortednk[$key]['policy'] != $policy) {
                    if ($sortednk[$key]['policy'] != '') {
                        $policyProblem = "**WRONG ITEM POLICY: " . $sortednk[$key]['policy'] . "**<BR>";
                        $policyProblemCount += 1;
                    } else {
                        $policyProblem = "**BLANK I POLICY**<BR>";
                        $policyProblemCount += 1;
                    }
                    $problem = true;
                } else {
                    $policyProblem = '';
                }

                $type = $_POST['itemType'];
                if ($sortednk[$key]['physical_material_type'] != $type) {
                    if ($sortednk[$key]['physical_material_type'] != '') {
                        $typeProblem = "**WRONG TYPE: " . $sortednk[$key]['physical_material_type'] . "**<BR>";
                        $typeProblemCount +=1;
                    } else {
                        $typeProblem = "**BLANK I TYPE**<BR>";
                        $typeProblemCount +=1;
                    }
                    $problem = true;
                } else {
                    $typeProblem = '';
                }
            }

            $scan_loc = $sortednk[$key]['scan_loc'];
            $correct_loc = $key + 1;

            //If row has a problem print in Bold and output problems to an output
            //array that way we can re-sort output array if desired
            $shelflist_obj = new stdClass();
          	$shelflist_obj->correct_location = $correct_loc;
          	$shelflist_obj->call_number = $sortednk[$key]['call_number'];
            $shelflist_obj->norm_call_number = $sortednk[$key]['call_sort'];
            $shelflist_obj->title = mb_convert_encoding(substr($sortednk[$key]['title'], 0, 20) . '...', 'UTF-8', 'ISO-8859-1');
            $shelflist_obj->scanned_location = $scan_loc;
            $shelflist_obj->problem_list = $orderProblem . $cnTypeProblem . $nipProblem . $tempProblem . $libraryProblem . $locationProblem . $policyProblem . $typeProblem;
            $shelflist_obj->barcode = $sortednk[$key]['item_barcode'];
            $shelflist_obj->problem = $problem;
          	//Add this loation to the array of locations using the unique location code as the index value
            //This converts stdClass objects to an
            //array so we can sort using array sort
            $shelflist[trim($key)] = json_decode(json_encode($shelflist_obj), true);
            //pre($shelflist);

            // Calculate the percentation
            //$_SESSION['progress'] = intval($key/$num_rows * 100);

        }
        //write out page header info
        $csv_output_filename = 'ShelfList_' . $_POST['library'] . '_' . $_POST['location'] . '_' . substr($first_call, 0, 4) . '_' . substr($last_call, 0, 4) . '_' . date('Ymd') . '.csv';
        $total_problems = $orderProblemCount + $cnTypeProblemCount + $tempProblemCount + $requestProblemCount + $locationProblemCount + $libraryProblemCount + $policyProblemCount + $typeProblemCount;
        $barcode_count = $num_rows - 1;

        echo '<header class="header">';
        echo '  <h1>📋 Inventory Report';
        echo '    <small>' . htmlspecialchars($_POST['library']) . ':' . htmlspecialchars($_POST['location']) . ' &middot; Range ' . substr($first_call, 0, 4) . '–' . substr($last_call, 0, 4) . ' &middot; ' . date('M j, Y') . '</small>';
        echo '  </h1>';
        echo '</header>';

        echo '<div class="container">';

        // Action bar
        echo '<div class="action-bar">';
        echo '  <div class="barcode-count">Processed <strong>' . $barcode_count . '</strong> barcodes &middot; <strong>' . $total_problems . '</strong> issues found</div>';
        echo '  <div style="display:flex;gap:0.5rem;">';
        echo '    <a href="index.php" class="action-btn action-btn-outline">← Run New File</a>';
        echo '    <a href="cache/output/' . $csv_output_filename . '" class="action-btn action-btn-primary">↓ Download CSV</a>';
        echo '  </div>';
        echo '</div>';

        // Stats grid
        echo '<div class="stats-grid">';
        echo '  <div class="stat-card stat-danger"><div class="stat-value">' . $orderProblemCount . '</div><div class="stat-label">Order Problems</div></div>';
        echo '  <div class="stat-card stat-warning"><div class="stat-value">' . $cnTypeProblemCount . '</div><div class="stat-label">CN Type Issues</div></div>';
        echo '  <div class="stat-card stat-primary"><div class="stat-value">' . $tempProblemCount . '</div><div class="stat-label">Temp Location</div></div>';
        echo '  <div class="stat-card"><div class="stat-value">' . $requestProblemCount . '</div><div class="stat-label">On Request</div></div>';
        echo '  <div class="stat-card stat-danger"><div class="stat-value">' . $locationProblemCount . '</div><div class="stat-label">Wrong Location</div></div>';
        echo '  <div class="stat-card stat-danger"><div class="stat-value">' . $libraryProblemCount . '</div><div class="stat-label">Wrong Library</div></div>';
        echo '  <div class="stat-card stat-warning"><div class="stat-value">' . $policyProblemCount . '</div><div class="stat-label">Policy Issues</div></div>';
        echo '  <div class="stat-card"><div class="stat-value">' . $typeProblemCount . '</div><div class="stat-label">Type Issues</div></div>';
        echo '</div>';

        // Range bar
        echo '<div class="range-bar">';
        echo '  <div class="range-item"><span class="range-label">First CN:</span><span class="range-value">' . htmlspecialchars($first_call) . '</span></div>';
        echo '  <div class="range-item"><span class="range-label">Last CN:</span><span class="range-value">' . htmlspecialchars($last_call) . '</span></div>';
        echo '</div>';

        outputRecords($shelflist);
        echo '</div>'; // close container
    }


} else {
    echo '<header class="header"><h1>📋 Inventory Report</h1></header>';
    echo '<div class="container" style="margin-top:2rem;text-align:center;">';
    echo '<div class="stat-card" style="display:inline-block;padding:2rem;"><div class="stat-value">No data received.</div><div class="stat-label">Please submit a barcode file first.</div></div>';
    echo '</div>';
}

function pre($data) {
    print '<pre>' . print_r($data, true) . '</pre>';
}

function outputRecords($output){
  //Use global to allow use inside of function
  global $csv_output_filename;
  // check if cached barcodeOutput file exists and delete if needed
  if (file_exists("cache/output/" . $csv_output_filename)) {
      unlink("cache/output/" . $csv_output_filename);

      if (isset($_GET['debug'])) {
          print("cache file deleted");
      }
  }

// open the csv file for writing

  $csv_file = fopen('cache/output/' . $csv_output_filename, 'w');

// save the CSV column headers
  fputcsv($csv_file, array('Correct_Position', 'Call_Number', 'norm_call_number','Title', 'Position Scanned', 'Problem', 'Barcode'));

  echo '<div class="table-card">';
  echo '<table id="CNTable" class="results-table tablesorter">';
  echo '<thead>';
  echo '<tr>';
  echo '<th class="col-order">#</th>';
  echo '<th class="col-cn">Call Number</th>';
  echo '<th class="col-title">Title</th>';
  echo '<th class="col-scanned">Scanned</th>';
  echo '<th>Problems</th>';
  echo '<th class="col-barcode">Barcode</th>';
  echo '</tr>';
  echo '</thead>';
  echo '<tbody>';
  foreach ($output as $key => $number) {
    //Don't print non-problems if only problems are requested
    if ($_POST['onlyproblems'] == 'true' && $output[$key]['problem'] != 1) {
      continue;
    }
    $row_class = ($output[$key]['problem'] == 1) ? 'row-problem' : '';
    echo '<tr class="' . $row_class . '">';

    echo '<td class="col-order">' . $output[$key]['correct_location'] . '</td>';
    echo '<td class="col-cn">' . htmlspecialchars($output[$key]['call_number']) . '</td>';
    echo '<td class="col-title">' . $output[$key]['title'] . '</td>';
    echo '<td class="col-scanned">' . $output[$key]['scanned_location'] . '</td>';

    // Format problem badges
    $prob_html = $output[$key]['problem_list'];
    $prob_html = str_replace('**OUT OF ORDER**', '<span class="problem-badge badge-order">OUT OF ORDER</span>', $prob_html);
    $prob_html = preg_replace('/\*\*WRONG CN TYPE\*\*/', '<span class="problem-badge badge-cn-type">WRONG CN TYPE</span>', $prob_html);
    $prob_html = preg_replace('/\*\*NIP: (.*?)\*\*/', '<span class="problem-badge badge-nip">NIP: $1</span>', $prob_html);
    $prob_html = preg_replace('/\*\*IN TEMP LOC\*\*/', '<span class="problem-badge badge-temp">TEMP LOCATION</span>', $prob_html);
    $prob_html = preg_replace('/\*\*WRONG LIBRARY: (.*?)\*\*/', '<span class="problem-badge badge-library">WRONG LIB: $1</span>', $prob_html);
    $prob_html = preg_replace('/\*\*WRONG LOCATION: (.*?)\*\*/', '<span class="problem-badge badge-location">WRONG LOC: $1</span>', $prob_html);
    $prob_html = preg_replace('/\*\*WRONG ITEM POLICY: (.*?)\*\*/', '<span class="problem-badge badge-policy">POLICY: $1</span>', $prob_html);
    $prob_html = preg_replace('/\*\*WRONG TYPE: (.*?)\*\*/', '<span class="problem-badge badge-type">TYPE: $1</span>', $prob_html);
    $prob_html = preg_replace('/\*\*BLANK I POLICY\*\*/', '<span class="problem-badge badge-policy">BLANK POLICY</span>', $prob_html);
    $prob_html = preg_replace('/\*\*BLANK I TYPE\*\*/', '<span class="problem-badge badge-type">BLANK TYPE</span>', $prob_html);
    $prob_html = preg_replace('/\*\*ITEM HAS REQUEST\*\*/', '<span class="problem-badge badge-nip">REQUESTED</span>', $prob_html);
    $prob_html = str_replace('Item Currently Between:', '<span class="problem-detail">Between: ', $prob_html);
    $prob_html = preg_replace('/Move item (back|forward) (\d+) spaces/', '</span><span class="problem-detail">Move $1 $2 spaces</span>', $prob_html);
    echo '<td>' . $prob_html . '</td>';

    echo '<td class="col-barcode">' . $output[$key]['barcode'] . '</td>';
    echo '</tr>';

    //output to csv — strip HTML tags for clean CSV
    $problems = preg_replace('#<[^>]+>#', '', $output[$key]['problem_list']);
    $problems = preg_replace('/\s+/', ' ', trim($problems));
    fputcsv($csv_file, array($output[$key]['correct_location'], $output[$key]['call_number'], $output[$key]['norm_call_number'], $output[$key]['title'], $output[$key]['scanned_location'], $problems, '="' . $output[$key]['barcode'] . '"'));
  }
  echo '</tbody>';
  echo '</table>';
  echo '</div>'; // close table-card
  fclose($csv_file);
}
?>
<footer class="footer">Alma Inventory Scanner &middot; Powered by Alma API</footer>
</body>
</html>

