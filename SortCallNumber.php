<?php
/*********************************************************************
 * SortLC Takes in two LC Call #'s, Normalizes, then sorts them
 * Can use usort or uasort to sort arrays based on the call number
 *********************************************************************/
function SortLC($right, $left)
{
    $right = NormalizeLC($right);
    $left = NormalizeLC($left);
    return (strcmp($right, $left));
} // end SortLC
/*********************************************************************
/*********************************************************************
 * SortLCObject Takes in two Obects contaning LC Call # elements
 * defined as call_number, normalizes, then sorts them
 * Can use usort or uasort to sort arrays based on the call number
 *********************************************************************/
function SortLCObject($right, $left)
{
    $right_cn = $right["call_number"] ?? "";
    $left_cn = $left["call_number"] ?? "";
    $right_norm = NormalizeLC($right_cn);
    $left_norm = NormalizeLC($left_cn);
    return (strcmp($right_norm, $left_norm));
} // end SortLC
/*********************************************************************
 *  NormalizeLC
 *  Normalizes LC for sorting
 *********************************************************************/
function NormalizeLC($lc_call_no_orig)
{
    /*
      User defined setting: set problems to top to sort unparsable
      call numbers to the top of the list; false to sort them to the
      bottom.
    */
    $problems_to_top = "true";
    if ($problems_to_top == "true") {
        $unparsable = " ";
    } else {
        $unparsable = "~";
    }
    //Convert all alpha to uppercase
    $lc_call_no = strtoupper($lc_call_no_orig);
    // define special trimmings that indicate integer
    $integer_markers = array("C.", "BD.", "DISC", "DISK", "NO.", "PT.", "T.", "v.", "V.", "VOL.");
    foreach ($integer_markers as $mark) {
        $mark = str_replace(".", "\.", $mark);
        $lc_call_no = preg_replace("/$mark(\d+)/", "$mark$1;", $lc_call_no);
    } // end foreach int marker
    // Protect spaces before standalone 4-digit years (e.g. "BP109 2010" has no cutter).
    // Use a tilde sentinel instead of a dot so the year is NOT parsed as a class decimal.
    // The tilde causes the regex to leave everything from it onward in $the_trimmings.
    $lc_call_no = preg_replace('/(\d)\s+(\d{4}\b)/', '$1~$2', $lc_call_no);
    // Protect ordinal edition markers (1st, 2nd, 3rd, 10th, etc.) that appear between
    // the class number and the cutter (e.g. "UA31 10th .L4197" -> "UA31~10TH .L4197").
    // Without this, whitespace stripping merges them into the class number
    // ("UA3110TH") making the class number read as 3110 instead of 31.
    $lc_call_no = preg_replace('/(\d)\s+(\d+(?:st|nd|rd|th)\b)/i', '$1~$2', $lc_call_no);
    // Remove any remaining whitespace
    $lc_call_no = preg_replace("/\s*/", "", $lc_call_no);

    // Try the standard parse first (class number required: \d+).
    // Fall back to bare-class parse (\d* optional) only for single-letter LC classes
    // like K, Q, Z that have no class number (e.g. "K .C845 R 1970 v. 1").
    // 2- and 3-letter prefixes like DVD, VHS, CD are media labels, not LC classes,
    // and must NOT be caught by the bare-class fallback.
    $pattern_std  = "/^([A-Z]{1,3})\s*(\d+)\s*\.*(\d*)\s*\.*\s*([A-Z]*)(\d*)\s*([A-Z]*)(\d*)\s*(.*)$/";
    $pattern_bare = "/^([A-Z]{1})\s*\.+\s*([A-Z]+)(\d*)\s*([A-Z]*)(\d*)\s*(.*)$/";

    if (preg_match($pattern_std, $lc_call_no, $m)) {
        $initial_letters = $m[1];
        $class_number    = $m[2];
        $decimal_number  = $m[3];
        $cutter_1_letter = $m[4];
        $cutter_1_number = $m[5];
        $cutter_2_letter = $m[6];
        $cutter_2_number = $m[7];
        $the_trimmings   = $m[8];
    } elseif (preg_match($pattern_bare, $lc_call_no, $m)) {
        // Bare single-letter class: K .C845 R 1970 v. 1 → K + no number + cutter C845 + ...
        $initial_letters = $m[1];
        $class_number    = '';
        $decimal_number  = '';
        $cutter_1_letter = $m[2];
        $cutter_1_number = $m[3];
        $cutter_2_letter = $m[4];
        $cutter_2_number = $m[5];
        $the_trimmings   = $m[6];
    } else {
        return ($unparsable);
    } // return extreme answer if not a call number
    if ($cutter_2_letter && !($cutter_2_number)) {
        $the_trimmings = $cutter_2_letter . $the_trimmings;
        $cutter_2_letter = '';
    }
    // Strip the tilde sentinel from the year-only case (e.g. "BP109~2010" ends up in the_trimmings)
    // This ensures the year sorts as a plain suffix (after the class, before any cutter)
    $the_trimmings = ltrim($the_trimmings, '~');
    // Handle ordinal edition markers that absorbed the cutter into trimmings.
    // When the class number is followed by an ordinal (e.g. "UA31 10th .L4197 2003"),
    // the sentinel leaves "10TH.L4197~2003" in trimmings. Extract the cutter from it
    // so it still participates in sorting, and keep the ordinal as a trimming prefix.
    if (!$cutter_1_letter && preg_match('/^(\d+(?:ST|ND|RD|TH))\.([A-Z])(\d*)(.*)/i', $the_trimmings, $om)) {
        $cutter_1_letter = strtoupper($om[2]);
        $cutter_1_number = $om[3];
        $year_suffix     = ltrim($om[4], '~');
        $the_trimmings   = $om[1] . ($year_suffix ? ' ' . $year_suffix : '');
    }
    // Handle second cutter written with a leading dot (e.g. ".G359 2024")
    // The dot is purely a visual separator in LC call numbers and must be stripped for correct sorting
    if (!$cutter_2_letter && preg_match('/^\.([A-Z]+)(\d+)\s*(.*)$/i', ltrim($the_trimmings), $dm)) {
        $cutter_2_letter = strtoupper($dm[1]);
        $cutter_2_number = $dm[2];
        $the_trimmings   = $dm[3];
    }
    /* TESTING NEW SECTION TO HANDLE VOLUME & PART NUMBERS */
    foreach ($integer_markers as $mark) {
        if (preg_match("/(.*)($mark)(\d+)(.*)/", $the_trimmings, $m)) {
            $trim_start = $m[1];
            $int_mark = $m[2];
            $int_no = $m[3];
            $trim_rest = $m[4];
            $int_no = sprintf("%5s", $int_no);
            $the_trimmings = $trim_start . $int_mark . $int_no . $trim_rest;
        } // end if markers in the trimmings
    } // end foreach integer marker
    /* END NEW SECTION */
    if ($class_number) {
        $class_number = sprintf("%5s", $class_number);
    }
    $decimal_number = sprintf("%-12s", $decimal_number);
    if ($cutter_1_number) {
        $cutter_1_number = " $cutter_1_number";
    }
    if ($cutter_2_letter) {
        $cutter_2_letter = "   $cutter_2_letter";
    }
    if ($cutter_2_number) {
        $cutter_2_number = " $cutter_2_number";
    }
    if ($the_trimmings) {
        $the_trimmings = preg_replace("/(\.)(\d)/", "$1 $2", $the_trimmings);
        $the_trimmings = preg_replace("/(\d)\s*-\s*(\d)/", "$1-$2", $the_trimmings);
        //    $the_trimmings =~ s/(\d+)/sprintf("%5s", $1)/ge;
        $the_trimmings = "   $the_trimmings";
    }
    $normalized = "$initial_letters" . "$class_number"
        . "$decimal_number" . "$cutter_1_letter"
        . "$cutter_1_number" . "$cutter_2_letter"
        . "$cutter_2_number" . "$the_trimmings";

    return ("$normalized");
} // end NormalizeLC

//an adaptation of Koha's Dewey sort routine
//GPL info goes here
//problem call numbers
/*
709.04 M453
704.94978 S727
759.06 E96
759.1H766
759.1N
*/
//759.06 E96 should display as 759_060000000000000_E96
//$callNum = '759.06 E96';

/*********************************************************************
 * SortDeweyObject  Takes in two Obects contaning Dewey Call # elements
 * defined as call_number, normalizes, then sorts them
 * Can use usort or uasort to sort arrays based on the call number
 *********************************************************************/
/*********************************************************************
 * SortDewey Takes in two Dewey Call #'s, Normalizes, then sorts them
 * Can use usort or uasort to sort arrays based on the call number
 *********************************************************************/
function SortDewey($right, $left)
{
    $right = normalizeDewey($right);
    $left = normalizeDewey($left);
    return (strcmp($right, $left));
} // end SortLC
/*********************************************************************/

function SortDeweyObject($right, $left)
{
    $right_cn = $right["call_number"] ?? "";
    $left_cn = $left["call_number"] ?? "";
    $right_norm = normalizeDewey($right_cn);
    $left_norm = normalizeDewey($left_cn);
    return (strcmp($right_norm, $left_norm));
} // end SortLC

function normalizeDewey($callNum)
{
    //Insert ! when any letter comes after number (case-insensitive)
    $init = preg_replace('/([0-9])(?=[a-zA-Z])/', '$1!', $callNum);
    //make all characters lowercase... sort works better this way for dewey...
    $init = strtolower($init);
    //get rid of leading whitespace
    $init = preg_replace('/^\s+/', '', $init);
    //get rid of extra whitespace at end of string
    $init = preg_replace('/\s+$/', '', $init);
    //get rid of &nbsp; at end of string
    $init = preg_replace('/\&/', '', $init);
    //remove any slashes
    $init = preg_replace('/\//', '', $init);
    //remove any backslashes
    $init = stripslashes($init);
    // replace newline characters
    $init = preg_replace('/\n/', '', $init);

    //set digit group count
    $digit_group_count = 0;
    //declare first digit group index variable
    $first_digit_group_idx = null;

    //split string into tokens by ., :, or space
    $tokens = preg_split('/[\.:\s]+/', $init);

    //loop through the tokens
    for ($i = 0; $i < sizeof($tokens); $i++) {
        //if the token begins and ends with digits
        if (preg_match("/^\d+$/", $tokens[$i])) {
            //increment the number of digit groups
            $digit_group_count++;
            //if it's the first one, store its index in first_digit_group_idx
            if (1 == $digit_group_count) {
                $first_digit_group_idx = $i;
            }
            //if there is a second group of digits, expand it to 15 places, adding 0s
            if (2 == $digit_group_count) {
                if ($i - $first_digit_group_idx == 1) {
                    $tokens[$i] = str_pad($tokens[$i], 15, "0", STR_PAD_RIGHT);
                    //$tokens[$i] =~ tr/ /0/;
                } else {
                    $tokens[$first_digit_group_idx] .= '_000000000000000';
                }
            }
        }

    }

    if (1 == $digit_group_count) {
        $tokens[$first_digit_group_idx] .= '_000000000000000';
    }

    // Pad the numeric portion of cutter tokens (already lowercased, e.g. "n857" -> "n857000000000000")
    // so that cutter numbers are treated as decimal fractions.
    // Without this, "n8576" would sort before "n857" because the underscore
    // separator following "n857" has a higher ASCII value than the digit "6".
    $token_count = count($tokens);
    for ($i = 0; $i < $token_count; $i++) {
        if (preg_match('/^([a-z!]+)(\d+)(.*)$/', $tokens[$i], $m)) {
            $tokens[$i] = $m[1] . str_pad($m[2], 15, "0", STR_PAD_RIGHT) . $m[3];
        }
    }

    // Left-pad remaining purely numeric tokens (e.g. volume numbers: t.1, t.10)
    // so that they sort numerically in string comparisons.
    // Skip the first digit group (the Dewey class number) — it's already handled
    // by the digit_group_count logic above and must not be reformatted.
    $token_count2 = count($tokens);
    for ($i = 0; $i < $token_count2; $i++) {
        if (isset($first_digit_group_idx) && $i === $first_digit_group_idx)
            continue;
        if (preg_match('/^\d+$/', $tokens[$i]) && strlen($tokens[$i]) < 10) {
            $tokens[$i] = str_pad($tokens[$i], 10, "0", STR_PAD_LEFT);
        }
    }

    $key = implode("_", $tokens);
    return $key;
}

?>