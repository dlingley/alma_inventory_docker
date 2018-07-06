<?php

/**
 * Quick and easy progress script
 * The script will slow iterate through an array and display progress as it goes.
 */

#First progress
$array1  = array(2, 4, 56, 3, 3);
$current = 0;

foreach ($array1 as $element) {
    $current++;
    outputProgress($current, count($array1));
}
echo "<br>";

#Second progress
$array2  = array(2, 4, 66, 54);
$current = 0;

foreach ($array2 as $element) {
    $current++;
    outputProgress($current, count($array2));
}

/**
 * Output span with progress.
 *
 * @param $current integer Current progress out of total
 * @param $total   integer Total steps required to complete
 */
function outputProgress($current, $total) {
    echo "<span style='position: absolute;z-index:$current;background:#FFF;'>" . round($current / $total * 100) . "% </span>";
    myFlush();
    sleep(1);
}

/**
 * Flush output buffer
 */
function myFlush() {
    echo(str_repeat(' ', 256));
    if (@ob_get_contents()) {
        @ob_end_flush();
    }
    flush();
}

?>
