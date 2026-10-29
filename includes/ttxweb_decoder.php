<?php

// ttxweb.php teletext document renderer
// version: 1.7.2.772 (2026-07-17)
// (c) 2023-2026 Fabian Schneider and Contributors - @fabianswebworld

const EP1_HEADER_LENGTH = 6;

function renderTeletextFile($teletextFile, $level15, $reveal) {

    // render teletext file, select format/parser
    // depending on file extension, this might be refined
    // and extended to accommodate more formats/parsers

    switch (strtolower(pathinfo($teletextFile, PATHINFO_EXTENSION))) {

        case 'ep1':
            parseEp1File($teletextFile, $level15, $level1Data, $x26Data);
            break;
        case 'ast':
            parseAstFile($teletextFile, $level15, $level1Data, $x26Data);
            break;
        case 'tti':
            parseTtiFile($teletextFile, $level15, $level1Data, $x26Data);
            break;

    }

    decodeAndRenderTeletextData($level1Data, $x26Data, $level15, $reveal);

}


function parseEp1File($ep1Filename, $level15, &$level1Data, &$x26Data) {

    // read and parse EP1 file, write unpacked output to
    // by-reference variables &$level1Data and &$x26Data

    global $errorPageClassString;

    // handling of non-present or too small / 0-byte EP1 file
    if (!file_exists($ep1Filename) || (filesize($ep1Filename) < 512)) {
        $level1Data = str_pad(NO_PAGE_STRING, 600, ' ', STR_PAD_BOTH);
        $x26Present = false;
        $errorPageClassString = ' class="errorPage"';
    }
    else {

        $ep1Handle = fopen($ep1Filename, 'rb');
        $ep1Contents = fread($ep1Handle, filesize($ep1Filename));
        fclose($ep1Handle);

        // parse EP1 header:
        // get offset to raw level 1.0 teletext data

        $level1Offset = unpack('v', substr($ep1Contents, 4, 2))[1] + EP1_HEADER_LENGTH;

        // check if X/26 (level 1.5) data is present

        $x26Indicator = ord(substr($ep1Contents, 3, 1));
        if ($x26Indicator != 0x00) {
            $x26Present = true;
        }
        else {
            $x26Present = false;
        }

        // Softel Cyclone TAP X/26 EP1 file format:
        // for some reason, the offset is 4 bytes too low
        // in comparison to Softel Flair X/26 EP1 files

        if ($x26Indicator == 0xc2) {
            $level1Offset += 4;
        }

        // the raw level 1.0 teletext data is in $level1Data

        $level1Data = substr($ep1Contents, $level1Offset, 960);
    }

    // read X/26 data if present and enabled

    if ($x26Present && EP1_DECODE_X26 && $level15) {
        $x26Length = unpack('v', substr($ep1Contents, 8, 2))[1];
        $x26Offset = 0x0a;
        $x26Data = substr($ep1Contents, $x26Offset, $x26Length);
    }

}


function parseAstFile($astFilename, $level15, &$level1Data, &$x26Data) {

    // read and parse ASTET file, write unpacked output to
    // by-reference variables &$level1Data and &$x26Data

    global $errorPageClassString;

    // handling of non-present or too small / 0-byte AST file
    if (!file_exists($astFilename) || (filesize($astFilename) < 96)) {
        $level1Data = str_pad(NO_PAGE_STRING, 600, ' ', STR_PAD_BOTH);
        $x26Present = false;
        $errorPageClassString = ' class="errorPage"';
    }
    else {

        $astHandle = fopen($astFilename, 'rb');
        $astFileLength = filesize($astFilename);
        $astContents = fread($astHandle, $astFileLength);
        fclose($astHandle);

        // create and sanitize level 1.0 row array

        for ($row = 0; $row < 25; $row++) {
            $astRowBuffer[$row] = str_repeat(' ', 40);
        }

        // parse ASTET file: populate row array's corresponding row keys
        // as rows are encountered in the ASTET data, last row with a given
        // row number overwrites any previous data with the same row number
        // (as in a real decoder), except for row 26 which will just be
        // concatenated in the array's row 26 key, accumulating all packet
        // X/26 data for later decoding

        $rowCount = floor($astFileLength / 42);

        for ($rowPtr = 0; $rowPtr < $rowCount; $rowPtr++) {

            $rowNumber = ord(substr($astContents, 42 * $rowPtr + 1, 1));
            $rowBytes = substr($astContents, 42 * $rowPtr + 2, 40);

            if ($rowNumber != 26) {
                $astRowBuffer[$rowNumber] = $rowBytes & str_repeat(chr(127), 40);
            }
            else {
                if (isset($astRowBuffer[26])) {
                    $astRowBuffer[$rowNumber] .= $rowBytes;
                }
                else {
                    $astRowBuffer[$rowNumber] = $rowBytes;
                }
            }
        }

        ksort($astRowBuffer);

        // check if X/26 (level 1.5) data is present

        $x26Present = isset($astRowBuffer[26]);

        // the raw level 1.0 teletext data is in $level1Data

        $level1Data = implode(array_slice($astRowBuffer, 0, 24));

    }

    // Hamming-decode and read X/26 data if present and enabled

    if ($x26Present && EP1_DECODE_X26 && $level15) {
        $x26Data = decodeHamming2418($astRowBuffer[26]);
    }

}


function parseTtiFile($ttiFilename, $level15, &$level1Data, &$x26Data) {

    // Read and parse a TTI teletext file.
    // A TTI file contains one or more subpages separated by 'PN,' records.
    // Each subpage uses text-based 'OL,' records for row data, where
    // teletext control codes are encoded as ESC + (0x40 + controlCode).
    //
    // This function picks the subpage that matches the global $subpageNum
    // (1-based) and converts it to the 960-byte level1Data format that
    // decodeAndRenderTeletextData() expects, identical to what parseEp1File()
    // and parseAstFile() produce.

    global $errorPageClassString, $subpageNum;

    // --- sanity check ---
    if (!file_exists($ttiFilename) || (filesize($ttiFilename) < 10)) {
        $level1Data = str_pad(NO_PAGE_STRING, 960, ' ');
        $errorPageClassString = ' class="errorPage"';
        return;
    }

    // read file in binary mode so that raw mosaic/graphic bytes
    // (e.g. 0x7F) are never mangled by text-mode CR/LF conversion.
    $handle = fopen($ttiFilename, 'rb');
    $raw    = fread($handle, filesize($ttiFilename));
    fclose($handle);

    // Split on CRLF first (standard TTI), then fall back to bare LF or CR.
    // We do NOT do a blanket str_replace of single 0x0D/0x0A bytes because
    // those bytes can theoretically appear inside OL data as ESC-encoded
    // control codes and we want clean line splitting without touching data.
    if (strpos($raw, "\r\n") !== false) {
        $lines = explode("\r\n", $raw);
    }
    elseif (strpos($raw, "\r") !== false) {
        $lines = explode("\r", $raw);
    }
    else {
        $lines = explode("\n", $raw);
    }

    // --- split file into subpage blocks ---
    // each block starts with a PN record and ends just before the next one

    $subpages = [];   // array of arrays-of-lines, indexed from 0
    $current  = null;

    foreach ($lines as $line) {
        if (strncmp($line, 'PN,', 3) === 0) {
            if ($current !== null) {
                $subpages[] = $current;
            }
            $current = [$line];
        }
        elseif ($current !== null) {
            $current[] = $line;
        }
    }
    if ($current !== null) {
        $subpages[] = $current;
    }

    if (empty($subpages)) {
        $level1Data = str_pad(NO_PAGE_STRING, 960, ' ');
        $errorPageClassString = ' class="errorPage"';
        return;
    }

    // $subpageNum is 1-based (from the URL), pick the right block
    $subpageIdx = max(0, intval($subpageNum) - 1);
    if ($subpageIdx >= count($subpages)) {
        $subpageIdx = 0;
    }

    $blockLines = $subpages[$subpageIdx];

    // --- build row array (rows 0-23, each 40 bytes) ---
    // initialize all rows to spaces
    $rows = [];
    for ($r = 0; $r <= 23; $r++) {
        $rows[$r] = str_repeat(' ', 40);
    }

    $x26Raw = '';   // accumulates raw packet-26 bytes if present

    foreach ($blockLines as $line) {

        // only OL lines carry row content
        if (strncmp($line, 'OL,', 3) !== 0) {
            continue;
        }

        // OL,<row>,<data>
        // row and data are separated by the first two commas
        $firstComma  = strpos($line, ',');
        $secondComma = strpos($line, ',', $firstComma + 1);
        if ($firstComma === false || $secondComma === false) {
            continue;
        }

        $rowNum  = intval(substr($line, $firstComma + 1, $secondComma - $firstComma - 1));
        $rowData = substr($line, $secondComma + 1);

        // rows 0-23 go into level1Data; row 26 is X/26 data
        if ($rowNum >= 0 && $rowNum <= 23) {
            $rows[$rowNum] = ttiDecodeOlRow($rowData);
        }
        elseif ($rowNum == 26 && EP1_DECODE_X26 && $level15) {
            // accumulate X/26 rows (there may be more than one per subpage),
            // each decoded row is exactly 40 bytes of non-Hamming data, coded
            // using a special coding scheme (see below)
            $x26Raw .= ttiDecodeOlRow($rowData);
        }
    }

    // --- assemble 960-byte level1Data string (rows 0-23) ---
    $level1Data = '';
    for ($r = 0; $r <= 23; $r++) {
        $level1Data .= $rows[$r];
    }

    // --- pass X/26 data upstream if present ---
    // TTI X/26 rows are not Hamming 24/18 encoded, but the 13 triplets
    // are stuffed into an OL row using a special coding scheme (in the
    // vbit2 code, this is referred to as CODING_13_TRIPLETS); we decode
    // this through a special function, decode13Triplets()

    if (!empty($x26Raw) && EP1_DECODE_X26 && $level15) {
        $x26Data = decode13Triplets($x26Raw);
    }

}


function ttiDecodeOlRow($rowData) {

    // Convert a TTI OL row string to a 40-byte raw teletext row.
    //
    // In TTI files, teletext control bytes (0x00-0x1F) are encoded as
    // ESC (0x1B) followed by a character in the range 0x40-0x5F,
    // where the control byte = followingChar - 0x40.
    //
    // All other bytes are literal teletext G0/G1 characters and are
    // passed through unchanged (already in the 0x20-0x7F range).
    // Bytes outside that range (e.g. 0x7F = DEL / block mosaic in G1)
    // are also passed through; the main decoder handles them.
    //
    // The result is padded with spaces (0x20) to exactly 40 bytes,
    // or truncated if longer.

    $out = '';
    $len = strlen($rowData);
    $i   = 0;

    while ($i < $len && strlen($out) < 40) {
        $byte = ord($rowData[$i]);

        if ($byte === 0x1B && ($i + 1) < $len) {
            // ESC sequence: next char encodes a control byte
            $i++;
            $followByte = ord($rowData[$i]);
            if ($followByte >= 0x40 && $followByte <= 0x5F) {
                $out .= chr($followByte - 0x40);
            }
            else {
                // unexpected ESC sequence - treat follow char literally
                $out .= chr($followByte);
            }
        }
        else {
            $out .= chr($byte);
        }

        $i++;
    }

    // pad / truncate to exactly 40 bytes
    if (strlen($out) < 40) {
        $out = str_pad($out, 40, ' ');
    }
    else {
        $out = substr($out, 0, 40);
    }

    return $out;

}


function decodeAndRenderTeletextData($level1Data, $x26Data, $level15, $reveal) {

    global $queryString;
    global $errorPageClassString;
    global $showHeader;
    global $pageNum, $subpageNum, $prevPageNum, $nextPageNum, $prevSubpageNum, $nextSubpageNum, $numSubpages, $origNextSubpageNum;

    echo "<div id=\"ttxContainer\">\n<div id=\"ttxPage\"" . $errorPageClassString . ">\n";

    // read level 1.0 data into 2-dimensional array
    // for better handling, especially on decoding X/26 data

    for ($row = 0; $row <= 23; $row++) {
        for ($col = 0; $col <= 39; $col++) {
            $pageBuffer['level1'][$row][$col] = substr($level1Data, 40 * $row + $col, 1);
        }
    }

    // if present and enabled, decode X/26 data to page buffer

    if (!empty($x26Data) && $level15) {
        decodeX26Chars($x26Data, $pageBuffer);
    }

    // main level 1.0 decoding loop

    for ($row = 0; $row <= 23; $row++) {

        $htmlBuffer = '';
        $attributesChanged = false;

        echo '<pre class="ttxRow" id="row' . $row . '"><span class="bg0"><span class="bg0 fg7">';

        // write custom header and skip decoding for row 0,
        // preventing EP1 row 0 data from being visible
        // if JavaScript is turned off
        if ($row == 0 && !$showHeader) {
            $htmlBuffer = renderRow0();
            echo $htmlBuffer . "</span></span></pre>\n";
            continue;
        }

        // detect if a row has double height/size in order to
        // be able to copy color attributes to the next row
        $currRow = join($pageBuffer['level1'][$row]);
        if ((strpos($currRow, chr(0x0d)) !== false) || (strpos($currRow, chr(0x0f)) !== false)) {
            $doubleHeightRow = true;
        }
        else {
            $doubleHeightRow = false;
        }

        for ($col = 0; $col <= 39; $col++) {

            // row decoding loop - decode each teletext character on a row

            $isLevel15 = false;
            $currChar = $pageBuffer['level1'][$row][$col];

            if (!empty($pageBuffer['level15'][$row][$col])) {
                $currChar = $pageBuffer['level15'][$row][$col];
                $isLevel15 = true;
            }

            if ($col == 0) {
                // set attributes to start-of-line defaults
                $ttxAttributes['fgColor'] = 7;
                $ttxAttributes['bgColor'] = 0;
                $ttxAttributes['charSet'] = 'g0';
                $ttxAttributes['g1Mode'] = 'c';
                $ttxAttributes['g1Hold'] = false;
                $ttxAttributes['g1HoldChar'] = ' ';
                $ttxAttributes['g1HoldMode'] = 'c';
                $ttxAttributes['flash'] = '';
                $ttxAttributes['conceal'] = '';
                $ttxAttributes['size'] = '';
            }

            if ($col == 39) {
                // disallow double width on last column
                if ($ttxAttributes['size'] == 'dw') {
                    $ttxAttributes['size'] = '';
                    $attributesChanged = true; 
                }
                if ($ttxAttributes['size'] == 'ds') {
                    $ttxAttributes['size'] = 'dh';
                    $attributesChanged = true; 
                }
            }

            if ($row == 23) {
                // disallow double height on last row
                if ($ttxAttributes['size'] == 'dh') {
                    $ttxAttributes['size'] = '';
                }
                if ($ttxAttributes['size'] == 'ds') {
                    $ttxAttributes['size'] = 'dw';
                }
            }

            $htmlOutChar = ' ';

            // check for set-at attributes

            switch (true) {

                case ord($currChar) == 0x09:

                    // flash off
                    $ttxAttributes['flash'] = '';
                    $htmlOutChar = ' ';
                    $attributesChanged = true;
                    break;

                case ord($currChar) == 0x0c:

                    // normal size
                    $ttxAttributes['size'] = '';
                    $htmlOutChar = ' ';
                    $attributesChanged = true;
                    break;

                case ord($currChar) == 0x18:

                    // conceal
                    if ($reveal || EP1_ALWAYS_REVEAL) {
                        $ttxAttributes['conceal'] = 're';
                    }
                    else {
                        $ttxAttributes['conceal'] = 'co';
                    }      
                    $htmlOutChar = ' ';
                    $attributesChanged = true;
                    break;

                case ord($currChar) == 0x19:

                    // contiguous mosaics
                    $ttxAttributes['g1Mode'] = 'c';
                    $htmlOutChar = ' ';
                    break;

                case ord($currChar) == 0x1a:

                    // separated mosaics
                    $ttxAttributes['g1Mode'] = 's';
                    $htmlOutChar = ' ';
                    break;

                case ord($currChar) == 0x1c:

                    // black background
                    $ttxAttributes['bgColor'] = 0;
                    $htmlOutChar = ' ';
                    $attributesChanged = true;
                    if ($doubleHeightRow) {
                        $pageBuffer['level1'][$row + 1][$col] = $currChar;
                    }
                    break;

                case ord($currChar) == 0x1d:

                    // new background
                    $ttxAttributes['bgColor'] = $ttxAttributes['fgColor'];
                    $htmlOutChar = ' ';
                    $attributesChanged = true;
                    if ($doubleHeightRow) {
                        $pageBuffer['level1'][$row + 1][$col] = $currChar;
                    }
                    break;

                case ord($currChar) == 0x1e:

                    // hold graphics
                    $ttxAttributes['g1Hold'] = true;
                    $htmlOutChar = ' ';
                    break;

                case ($ttxAttributes['charSet'] == 'g1') && isG1Char($currChar):

                    // G1 character
                    $ttxAttributes['g1HoldChar'] = $currChar;
                    $ttxAttributes['g1HoldMode'] = $ttxAttributes['g1Mode'];
                    $htmlOutChar = ' ';
                    break;

                default:

                    // G0 character
                    if (!$isLevel15) {
                        $htmlOutChar = filter_var(g0ToHtml($currChar, EP1_LANGUAGE), FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);
                    }
                    else {
                        $htmlOutChar = filter_var($currChar, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);
                    }
                    if ($htmlOutChar == '') {
                        $htmlOutChar = ' ';
                    }
            }

            // char-by-char span mode for double-width / double-size
            $doubleWidthMode = ($ttxAttributes['size'] == 'ds' || $ttxAttributes['size'] == 'dw');

            // output the attribute change if attributes changed,
            // either by a set-at attribute from this iteration
            // or by a set-after attribute from the previous iteration
            if ($attributesChanged || $doubleWidthMode) {

                if ($currChar == ' ') {
                    $zIndex = 0;
                }
                else {
                    $zIndex = 100;
                }

                $newFgAttributes =
                 'bg' . $ttxAttributes['bgColor'] . ' ' .
                 'fg' . $ttxAttributes['fgColor'] . ' ' .
                 $ttxAttributes['size'] . ' ' . $ttxAttributes['flash'] . ' ' . $ttxAttributes['conceal'];

                $newBgAttributes =
                 'bg' . $ttxAttributes['bgColor'] . ' ' .
                 'z' . $zIndex;
 
                $newAttributesSpan = '</span><span class="' . trim($newBgAttributes) . '"><span class="' . trim($newFgAttributes) . '">';

                if (!$doubleWidthMode) {
                    $htmlBuffer .= '</span>';
                }

                $htmlBuffer .= $newAttributesSpan;
            
            }

            // output normal G0 character
            if ($ttxAttributes['charSet'] == 'g0') {

                $htmlBuffer .= $htmlOutChar;
            }

            // output G1 character
            if ($ttxAttributes['charSet'] == 'g1') {
                if (isAlphaBlastChar($currChar)) {
                    // alpha blast-through
                    $htmlBuffer .= $htmlOutChar;
                }
                else {
                    if ($ttxAttributes['g1Hold'] && !isG1Char($currChar)) {
                        // output the G1 hold character if hold mosaics is on
                        $htmlBuffer .= renderG1Char($ttxAttributes['g1HoldChar'], $ttxAttributes['fgColor'], $ttxAttributes['g1HoldMode']);
                    }
                    elseif (isG1Char($currChar)) {
                        // output the current (decoded) G1 character
                        $htmlBuffer .= renderG1Char($currChar, $ttxAttributes['fgColor'], $ttxAttributes['g1Mode']);
                    }
                    else {
                        $htmlBuffer .= $htmlOutChar;
                    }
                }
            }

            if ($doubleWidthMode) {
                $htmlBuffer .= '</span>';
            }

            $attributesChanged = false;

            // check for set-after attributes

            switch (true) {
                case ord($currChar) >= 0x00 && ord($currChar) <= 0x07:

                    // alpha color
                    $ttxAttributes['fgColor'] = ord($currChar);
                    if ($ttxAttributes['charSet'] != 'g0') {
                        $ttxAttributes['g1HoldChar'] = ' ';
                    }
                    $ttxAttributes['charSet'] = 'g0';
                    $ttxAttributes['conceal'] = '';
                    $htmlOutChar = ' ';
                    if ($doubleHeightRow) {
                        $pageBuffer['level1'][$row + 1][$col] = $currChar;
                    }
                    $attributesChanged = true;
                    break;

                case ord($currChar) >= 0x10 && ord($currChar) <= 0x17:

                    // graphics color
                    $ttxAttributes['fgColor'] = ord($currChar) - 0x10;
                    if ($ttxAttributes['charSet'] != 'g1') {
                        $ttxAttributes['g1HoldChar'] = ' ';
                    }
                    $ttxAttributes['charSet'] = 'g1';
                    $ttxAttributes['conceal'] = '';
                    $htmlOutChar = ' ';
                    if ($doubleHeightRow) {
                        $pageBuffer['level1'][$row + 1][$col] = $currChar;
                    }
                    $attributesChanged = true;
                    break;

                case ord($currChar) == 0x08:

                    // flash on
                    $ttxAttributes['flash'] = 'flash';
                    $htmlOutChar = ' ';
                    $attributesChanged = true;
                    break;

                case ord($currChar) == 0x0d:

                    // double height
                    $ttxAttributes['size'] = 'dh';
                    $htmlOutChar = ' ';
                    $attributesChanged = true;
                    break;

                case ord($currChar) == 0x0e:

                    // double width
                    $ttxAttributes['size'] = 'dw';
                    $htmlOutChar = ' ';
                    $attributesChanged = true;
                    break;

                case ord($currChar) == 0x0f:

                    // double size
                    $ttxAttributes['size'] = 'ds';
                    $htmlOutChar = ' ';
                    $attributesChanged = true;
                    break;

                case ord($currChar) == 0x1f:

                    // release graphics
                    $ttxAttributes['g1Hold'] = false;
                    $htmlOutChar = ' ';
                    break;
            }
        }

        // create page links (only for pages that exist)
        $htmlBuffer = preg_replace_callback('/(?<!&#)\b((?!155)[1-8]\d{2})\b/', 'createPageLinkIfExists', $htmlBuffer);

        // create web links
        $htmlBuffer = preg_replace('/((?:(?<!(?:\w|@|\)))(?=\w)|(?<=\w)(?!\w))((?:[\w-]+\.)+(?:de|com|org)(\/(?:[\/\w?&=#.\-]+)?)*)\b)|(\bhttps*:\/\/((?:[\w-]+\.)+(?:[\w-]+)(\/(?:[\/\w?&=#.\-]+)?)*)\b)/', '<a href="http://$1$4" target="_blank" title="http://$1$4">$0</a>', $htmlBuffer);
        $htmlBuffer = preg_replace('/http:\/\/(https*):\/\//', '$1://', $htmlBuffer);

        // create e-mail links
        $htmlBuffer = preg_replace('/\b([\w\-\.]+)(?:(?:\(at{0,1}\))|@|\*)((?:[\w-]+\.)+[\w-]{2,8})/', '<a href="mailto:$1@$2" target="_blank" title="$1@$2">$0</a>', $htmlBuffer);

        // create next subpage/page links
        if ($subpageNum < $numSubpages)  {
            $htmlBuffer = preg_replace('/(-&gt;|(?<!SWR )&gt;&gt;)/', '<a href="?page=' . $pageNum . '&amp;sub=' . $origNextSubpageNum . $queryString . '" title="' . TO_SUBPAGE_STRING . ' ' . $origNextSubpageNum . '">$1</a>', $htmlBuffer);
        }
        elseif ($numSubpages == 1) {
            $htmlBuffer = preg_replace('/(-&gt;|(?<!SWR )&gt;&gt;)/', '<a href="?page=' . $nextPageNum . $queryString . '" title="' . TO_PAGE_STRING . ' ' . $nextPageNum . '">$1</a>', $htmlBuffer);
        }
        else {
            $htmlBuffer = preg_replace('/(-&gt;|(?<!SWR )&gt;&gt;)/', '<a href="?page=' . $pageNum . '&amp;sub=1' . $queryString . '" title="' . TO_SUBPAGE_STRING . ' 1">$1</a>', $htmlBuffer);
        }

        // end of row
        echo $htmlBuffer . "</span></span></pre>\n";

    }

    echo "</div>\n</div>\n";

}


function isAlphaBlastChar($g1Char) {

    // check if a character is from the alpha blast-through range of characters
    return (ord($g1Char) >= 0x40 && ord($g1Char) <= 0x5f);

}


function isG1Char($g1Char) {

    // check if a character is from the G1 range of characters
    return (ord($g1Char) >= 0x20 && ord($g1Char) <= 0x3f) || (ord($g1Char) >= 0x60 && ord($g1Char) <= 0x7f);

}


function renderG1Char($g1Char, $fgColor, $g1Mode) {

    // render a G1 character via CSS class
    return '<span class="g1' . $g1Mode . $fgColor . dechex(ord($g1Char)) . '">&nbsp;</span>';

}


function decodeX26Chars($x26Data, &$pageBuffer) {

    // decode level 1.5 characters from packet X/26,
    // place the decoded HTML characters into the page buffer

    $x26Packets = str_split($x26Data, 40);

    $x26Triplets = [];
    $currRow = 0;

    // relevant characters from the latin G2 supplementary set
    $g2SupplementaryChars = array(0x21 => '&#161;', 0x22 => '&#162;', 0x23 => '&#163;', 0x25 => '&#165;', 0x27 => '&#167;', 0x28 => '&#164;', 0x29 => '&#8216;', 0x2a => '&#8220;', 0x2b => '&#171;', 0x2c => '&#8592;', 0x2d => '&#8593;', 0x2e => '&#8594;', 0x2f => '&#8595;', 0x30 => '&#176;', 0x31 => '&#177;', 0x32 => '&#178;', 0x33 => '&#179;', 0x34 => '&#215;', 0x35 => '&#181;', 0x36 => '&#182;', 0x37 => '&#183;', 0x38 => '&#247;', 0x39 => '&#8217;', 0x3a => '&#8221;', 0x3b => '&#187;', 0x3c => '&#188;', 0x3d => '&#189;', 0x3e => '&#190;', 0x3f => '&#191;', 0x50 => '&#8212;', 0x51 => '&#185;', 0x52 => '&#174;', 0x53 => '&#169;', 0x54 => '&#8482;', 0x55 => '&#9834;', 0x56 => '&#8364;', 0x57 => '&#8240;', 0x5c => '&#8539;', 0x5d => '&#8540;', 0x5e => '&#8541;', 0x5f => '&#8542;', 0x60 => '&#8486;', 0x61 => '&#198;', 0x62 => '&#272;', 0x63 => '&#170;', 0x64 => '&#294;', 0x66 => '&#306;', 0x67 => '&#319;', 0x68 => '&#321;', 0x69 => '&#216;', 0x6a => '&#338;', 0x6b => '&#186;', 0x6c => '&#222;', 0x6d => '&#358;', 0x6e => '&#330;', 0x6f => '&#329;', 0x70 => '&#312;', 0x71 => '&#230;', 0x72 => '&#273;', 0x73 => '&#240;', 0x74 => '&#295;', 0x75 => '&#305;', 0x76 => '&#307;', 0x77 => '&#320;', 0x78 => '&#322;', 0x79 => '&#248;', 0x7a => '&#339;', 0x7b => '&#223;', 0x7c => '&#254;', 0x7d => '&#359;', 0x7e => '&#331;');

    // the G2 diacritical marks according to ETSI EN 300 706 clause 15.6.3
    $g2DiacriticalMarks = array(1 => 'grave', 2 => 'acute', 3 => 'circ', 4 => 'tilde', 5 => 'macr', 6 => 'breve', 7 => 'dot', 8 => 'uml', 10 => 'ring', 11 => 'cedil', 13 => 'dblac', 14 => 'ogon', 15 => 'caron');

    $isCyrillic = isCyrillicLanguage(EP1_LANGUAGE);
    $isAtSign = false;

    if ($isCyrillic) {
        $g2SupplementaryChars[0x50] = '&#9472;';
        $g2SupplementaryChars[0x59] = '&#321;';
        $g2SupplementaryChars[0x5a] = '&#322;';
        $g2SupplementaryChars[0x5b] = '&#946;';
        foreach (str_split('DEFGIJKLNQRSUVWZ') as $index => $letter) {
            $g2SupplementaryChars[0x60 + $index] = $letter;
            $g2SupplementaryChars[0x70 + $index] = strtolower($letter);
        }
    }

    $g2CombiningMarks = array(1 => 768, 2 => 769, 3 => 770, 4 => 771, 5 => 772, 6 => 774, 7 => 775, 8 => 776, 9 => 803, 10 => 778, 11 => 807, 12 => 818, 13 => 779, 14 => 808, 15 => 780);

    foreach ($x26Packets as $x26Packet) {
        $x26Triplets = array_merge(
            $x26Triplets,
            str_split(substr($x26Packet, 1), 3)
        );
    }

    foreach ($x26Triplets as $x26Triplet) {

        // main X/26 triplet decoding loop
        $currX26Function = ord(substr($x26Triplet, 1, 1));

        switch (true) {
            case ($currX26Function == 0x04):

                // X/26 row select
                $currRow = ord(substr($x26Triplet, 0, 1)) - 40;
                break;

            case ($currX26Function == 0x0f):

                // X/26 place G2 supplementary character
                $currCol = ord(substr($x26Triplet, 0, 1));
                $currX26Char = substr($x26Triplet, 2, 1);
                if (isset($g2SupplementaryChars[ord($currX26Char)])) {
                    $currX26Char = $g2SupplementaryChars[ord($currX26Char)];
                    $pageBuffer['level15'][$currRow][$currCol] = $currX26Char;
                }


                $pageBuffer['level15'][$currRow][$currCol] = $currX26Char;
                break;

            case ($currX26Function == 0x10):

                // X/26 place non-diacritical G0 character
                $currCol = ord(substr($x26Triplet, 0, 1));
                $currX26Char = substr($x26Triplet, 2, 1);
                $isAtSign = false;
                if (ord($currX26Char) == 0x2a) {
                    $isAtSign = true;

                    // special handling if @ character is encoded as 0x2a
                    // according to ETSI EN 300 706 clause 12.3.4 
                    $currX26Char = '@';

                }
                // place the level 1.5 character through htmlspecialchars
                $pageBuffer['level15'][$currRow][$currCol] = ($isCyrillic && !$isAtSign) ? g0ToHtml($currX26Char, EP1_LANGUAGE) : htmlspecialchars($currX26Char);
                break;

            case (($currX26Function >= 0x10) && ($currX26Function <= 0x1f)):

                // X/26 place G0 character with diacritical mark from G2
                $currCol = ord(substr($x26Triplet, 0, 1));
                $currX26Char = substr($x26Triplet, 2, 1);
                if ($isCyrillic) {
                    if (isset($g2CombiningMarks[$currX26Function - 0x10])) {
                        $pageBuffer['level15'][$currRow][$currCol] = g0ToHtml($currX26Char, EP1_LANGUAGE) . '&#' . $g2CombiningMarks[$currX26Function - 0x10] . ';';
                    }
                }
                elseif (isset($g2DiacriticalMarks[$currX26Function - 0x10])) {
                    $currX26Char = '&' . $currX26Char . $g2DiacriticalMarks[$currX26Function - 0x10] . ';';
                    $pageBuffer['level15'][$currRow][$currCol] = $currX26Char;
                }
                break;
        }
    }
}


function decodeHamming2418($x26Data) {

    // decode Hamming 24/18 parity coding from X/26 triplets
    // and return decoded packet X/26

    $x26Packets = str_split($x26Data, 40);

    $x26Triplets = [];

    foreach ($x26Packets as $x26Packet) {
        $x26Triplets = array_merge(
            $x26Triplets,
            str_split(substr($x26Packet, 1), 3)
        );
    }

    $x26TripletsDecoded = [];

    foreach ($x26Triplets as $x26Triplet) {

        $x26BytesDecoded = [];

        // insane bit-juggling to remove parity bits

        $x26BytesDecoded[0] = chr(((ord(substr($x26Triplet, 0, 1)) & 4) >> 2) |
                                 ((ord(substr($x26Triplet, 0, 1)) & 16) >> 3) |
                                 ((ord(substr($x26Triplet, 0, 1)) & 32) >> 3) |
                                 ((ord(substr($x26Triplet, 0, 1)) & 64) >> 3) |
                                 ((ord(substr($x26Triplet, 1, 1)) & 3) << 4));
        $x26BytesDecoded[1] = chr((ord(substr($x26Triplet, 1, 1)) & 127) >> 2);
        $x26BytesDecoded[2] = chr((ord(substr($x26Triplet, 2, 1)) & 127));

        $x26TripletsDecoded = array_merge(
            $x26TripletsDecoded,
            $x26BytesDecoded
        );

    }

    $x26PacketCount = 1;
    $pos = 0;

    $x26TripletsDecoded = implode($x26TripletsDecoded);

    while ($pos < strlen($x26TripletsDecoded)) {

        $x26TripletsDecoded = substr_replace($x26TripletsDecoded, chr($x26PacketCount), $pos, 0);

        $x26PacketCount += 1;
        $pos += 40;
    }

    $x26DataDecoded = $x26TripletsDecoded;

    return $x26DataDecoded;

}


function decode13Triplets($x26Data) {

    // decode X/26 data according to the CODING_13_TRIPLETS scheme
    // used by vbit2 when generating TTI files

    $length = strlen($x26Data);

    if ($length === 0 || ($length % 40) !== 0) {
        return '';
    }

    $result = '';

    $packetCount = intdiv($length, 40);

    for ($p = 0; $p < $packetCount; $p++) {

        $packet = substr($x26Data, $p * 40, 40);

        // packet number
        $result .= chr(ord($packet[0]) - 0x40);

        // 13 triplets
        for ($i = 0; $i < 13; $i++) {

            $offset = 1 + ($i * 3);

            // extract 6-bit values
            $c1 = ord($packet[$offset])     & 0x3F;
            $c2 = ord($packet[$offset + 1]) & 0x3F;
            $c3 = ord($packet[$offset + 2]) & 0x3F;

            // assemble new triplet
            $triplet =
                $c1 |
                ($c2 << 6) |
                ($c3 << 12);

            // output triplet
            $result .= chr($triplet         & 0x3F);
            $result .= chr(($triplet >> 6)  & 0x1F);
            $result .= chr(($triplet >> 11) & 0x7F);
        }
    }

    return $result;

}


function getNationalSubset($ttxLanguage) {

    // return the national option code points of the active G0 set
    // as array: G0 position => Unicode code point

    $subsets = array(
        'english'    => [0xa3, 0x24, 0x40, 0x2190, 0xbd, 0x2192, 0x2191, 0x23, 0x2014, 0xbc, 0x2551, 0xbe, 0xf7],
        'german'     => [0x23, 0x24, 0xa7, 0xc4, 0xd6, 0xdc, 0x5e, 0x5f, 0xb0, 0xe4, 0xf6, 0xfc, 0xdf],
        'swedish'    => [0x23, 0xa4, 0xc9, 0xc4, 0xd6, 0xc5, 0xdc, 0x5f, 0xe9, 0xe4, 0xf6, 0xe5, 0xfc],
        'italian'    => [0xa3, 0x24, 0xe9, 0xb0, 0xe7, 0x2192, 0x2191, 0x23, 0xf9, 0xe0, 0xf2, 0xe8, 0xec],
        'french'     => [0xe9, 0xef, 0xe0, 0xeb, 0xea, 0xf9, 0xee, 0x23, 0xe8, 0xe2, 0xf4, 0xfb, 0xe7],
        'iberian'    => [0xe7, 0x24, 0xa1, 0xe1, 0xe9, 0xed, 0xf3, 0xfa, 0xbf, 0xfc, 0xf1, 0xe8, 0xe0],
        'czech'      => [0x23, 0x16f, 0x10d, 0x165, 0x17e, 0xfd, 0xed, 0x159, 0xe9, 0xe1, 0x11b, 0xfa, 0x161],
        'polish'     => [0x23, 0x144, 0x105, 0x17b, 0x15a, 0x141, 0x107, 0xf3, 0x119, 0x17c, 0x15b, 0x142, 0x17a],
        'estonian'   => [0x23, 0xf5, 0x160, 0xc4, 0xd6, 0x17d, 0xdc, 0xd5, 0x161, 0xe4, 0xf6, 0x17e, 0xfc],
        'lithuanian' => [0x23, 0x24, 0x160, 0x117, 0x119, 0x17d, 0x10d, 0x16b, 0x161, 0x105, 0x173, 0x17e, 0x12f],
        'romanian'   => [0x23, 0xa4, 0x162, 0xc2, 0x15e, 0x102, 0xce, 0x131, 0x163, 0xe2, 0x15f, 0x103, 0xee],
        'croatian'   => [0x23, 0xcb, 0x10c, 0x106, 0x17d, 0x110, 0x160, 0xeb, 0x10d, 0x107, 0x17e, 0x111, 0x161]
    );

    $cyrillicSubsets = array(
        'serbian'   => [0x427, 0x410, 0x411, 0x426, 0x414, 0x415, 0x424, 0x413, 0x425, 0x418, 0x408, 0x41a, 0x41b, 0x41c, 0x41d, 0x41e, 0x41f, 0x40c, 0x420, 0x421, 0x422, 0x423, 0x412, 0x403, 0x409, 0x40a, 0x417, 0x40b, 0x416, 0x402, 0x428, 0x40f, 0x447, 0x430, 0x431, 0x446, 0x434, 0x435, 0x444, 0x433, 0x445, 0x438, 0x458, 0x43a, 0x43b, 0x43c, 0x43d, 0x43e, 0x43f, 0x45c, 0x440, 0x441, 0x442, 0x443, 0x432, 0x453, 0x459, 0x45a, 0x437, 0x45b, 0x436, 0x452, 0x448],
        'russian'   => [0x42e, 0x410, 0x411, 0x426, 0x414, 0x415, 0x424, 0x413, 0x425, 0x418, 0x40d, 0x41a, 0x41b, 0x41c, 0x41d, 0x41e, 0x41f, 0x42f, 0x420, 0x421, 0x422, 0x423, 0x416, 0x412, 0x42c, 0x42a, 0x417, 0x428, 0x42d, 0x429, 0x427, 0x42b, 0x44e, 0x430, 0x431, 0x446, 0x434, 0x435, 0x444, 0x433, 0x445, 0x438, 0x45d, 0x43a, 0x43b, 0x43c, 0x43d, 0x43e, 0x43f, 0x44f, 0x440, 0x441, 0x442, 0x443, 0x436, 0x432, 0x44c, 0x44a, 0x437, 0x448, 0x44d, 0x449, 0x447],
        'ukrainian' => [0x42e, 0x410, 0x411, 0x426, 0x414, 0x415, 0x424, 0x413, 0x425, 0x418, 0x40d, 0x41a, 0x41b, 0x41c, 0x41d, 0x41e, 0x41f, 0x42f, 0x420, 0x421, 0x422, 0x423, 0x416, 0x412, 0x42c, 0x406, 0x417, 0x428, 0x404, 0x429, 0x427, 0x407, 0x44e, 0x430, 0x431, 0x446, 0x434, 0x435, 0x444, 0x433, 0x445, 0x438, 0x45d, 0x43a, 0x43b, 0x43c, 0x43d, 0x43e, 0x43f, 0x44f, 0x440, 0x441, 0x442, 0x443, 0x436, 0x432, 0x44c, 0x456, 0x437, 0x448, 0x454, 0x449, 0x447]
    );

    $cyrillicLanguages = array(
        'ru' => 'russian',
        'bg' => 'russian',
        'uk' => 'ukrainian',
        'mk' => 'serbian'
    );

    $languageSubsets = array(
        'de' => 'german',
        'sv' => 'swedish',
        'fi' => 'swedish',
        'hu' => 'swedish',
        'it' => 'italian',
        'fr' => 'french',
        'pt' => 'iberian',
        'es' => 'iberian',
        'cs' => 'czech',
        'sk' => 'czech',
        'pl' => 'polish',
        'et' => 'estonian',
        'lt' => 'lithuanian',
        'lv' => 'lithuanian',
        'ro' => 'romanian',
        'hr' => 'croatian',
        'sr' => 'croatian',
        'sl' => 'croatian'
    );

    $languageCode = strtolower(substr($ttxLanguage, 0, 2));
    $latinPositions = [0x23, 0x24, 0x40, 0x5b, 0x5c, 0x5d, 0x5e, 0x5f, 0x60, 0x7b, 0x7c, 0x7d, 0x7e];

    $cyrillicName = null;
    if (stripos($ttxLanguage, 'sr-Cyrl') === 0) $cyrillicName = 'serbian';
    if (isset($cyrillicLanguages[$languageCode])) $cyrillicName = $cyrillicLanguages[$languageCode];

    if ($cyrillicName !== null) {
        $subset = array_combine(range(0x40, 0x7e), $cyrillicSubsets[$cyrillicName]);
        if ($cyrillicName == 'russian') $subset[0x26] = 0x44b;
        if ($cyrillicName == 'ukrainian') $subset[0x26] = 0x457;
        return $subset;
    }

    if (strtolower($ttxLanguage) == 'en-gb') return array_combine($latinPositions, $subsets['english']);
    if (isset($languageSubsets[$languageCode])) return array_combine($latinPositions, $subsets[$languageSubsets[$languageCode]]);

    return null;

}


function isCyrillicLanguage($ttxLanguage) {

    $subset = getNationalSubset($ttxLanguage);

    return ($subset !== null) && isset($subset[0x41]);

}


function g0ToHtml($ttxString, $ttxLanguage) {

    // replace national G0 characters by HTML entities

    static $replacements = array();

    if (!isset($replacements[$ttxLanguage])) {

        $map = array(
            '&' => '&amp;',
            chr(0x7f) => '&#9632;',
            '<' => '&lt;',
            '>' => '&gt;',
            '"' => '&quot;',
            '\'' => '&#39;'
        );

        $subset = getNationalSubset($ttxLanguage);

        if ($subset !== null) {
            foreach ($subset as $position => $codePoint) {
                $map[chr($position)] = ($codePoint < 0x80) ? chr($codePoint) : '&#' . $codePoint . ';';
            }
        }

        $replacements[$ttxLanguage] = $map;

    }

    return strtr($ttxString, $replacements[$ttxLanguage]);

}


function createPageLinkIfExists($matches) {

    // create hyperlink from a page number only if the page
    // exists physically

    global $queryString;

    if (pageExists($matches[1], 1)) {
        return '<a href="?page=' . $matches[1] . $queryString . '" title="' . TO_PAGE_STRING . ' ' . $matches[1] . '">' . $matches[1] . '</a>';
    }
    else {
        return $matches[1];
    }

}


function renderRow0() {

    // render header row from pattern set by ROW_0_CUSTOMHEADER
    // for static display if JavaScript is disabled

    global $pageNum, $subpageNum, $seqn0, $ttxLanguage, $ttxRow0Pattern;

    $myDate = new DateTime();

    if (class_exists('IntlDateFormatter')) {
        $formatter = new IntlDateFormatter($ttxLanguage, IntlDateFormatter::FULL, IntlDateFormatter::FULL);
        $formatter->setPattern('EEEEEE');
        $myWeekDay = substr($formatter->format($myDate), 0, 2);
    }
    else {
        $myWeekDay = substr($myDate->format('D'), 0, 2);
    }

    $myMonth = str_pad($myDate->format('m'), 2, '0', STR_PAD_LEFT);
    $myDay = str_pad($myDate->format('d'), 2, '0', STR_PAD_LEFT);
    $myYear = str_pad($myDate->format('y'), 2, '0', STR_PAD_LEFT);
    $myHours = str_pad($myDate->format('H'), 2, '0', STR_PAD_LEFT);
    $myMinutes = str_pad($myDate->format('i'), 2, '0', STR_PAD_LEFT);
    $mySeconds = str_pad($myDate->format('s'), 2, '0', STR_PAD_LEFT);

    $mySubpageNum = 0;
    if (!$seqn0) $mySubpageNum = $subpageNum;

    $myRow0 = $ttxRow0Pattern;
    $myRow0 = str_replace("%page%", $pageNum, $myRow0);
    $myRow0 = str_replace("%sub%", str_pad($mySubpageNum, 2, '0', STR_PAD_LEFT), $myRow0);
    $myRow0 = str_replace("%weekday%", $myWeekDay, $myRow0);
    $myRow0 = str_replace("%month%", $myMonth, $myRow0);
    $myRow0 = str_replace("%day%", $myDay, $myRow0);
    $myRow0 = str_replace("%year%", $myYear, $myRow0);
    $myRow0 = str_replace("%hh%", $myHours, $myRow0);
    $myRow0 = str_replace("%mm%", $myMinutes, $myRow0);
    $myRow0 = str_replace("%ss%", $mySeconds, $myRow0);

    return $myRow0;

}

?>
