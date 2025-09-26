<?php

// ttxweb.php teletext document renderer
// version: 1.6.6.726 (2025-09-25)
// (c) 2023, 2024, 2025 Fabian Schneider - @fabianswebworld

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
        $htmlBuffer = preg_replace_callback('/\b((?!155)[1-8]\d{2})\b/', 'createPageLinkIfExists', $htmlBuffer);

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
    $g2SupplementaryChars = array(0x50 => '&#8212;', 0x52 => '&#174;', 0x53 => '&#x00a9;', 0x54 => '&#8482;', 0x55 => '&#9834;', 0x56 => '&#8364;', 0x69 => '&Oslash;', 0x75 => '&#x00131;', 0x79 => '&oslash;');

    // the G2 diacritical marks according to ETSI EN 300 706 clause 15.6.3
    $g2DiacriticalMarks = array(1 => 'grave', 2 => 'acute', 3 => 'circ', 4 => 'tilde', 6 => 'breve', 8 => 'uml', 10 => 'ring', 11 => 'cedil', 15 => 'caron');

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
                if (ord($currX26Char) == 0x2a) {

                    // special handling if @ character is encoded as 0x2a
                    // according to ETSI EN 300 706 clause 12.3.4 
                    $currX26Char = '@';

                }
                // place the level 1.5 character through htmlspecialchars
                $pageBuffer['level15'][$currRow][$currCol] = htmlspecialchars($currX26Char);
                break;

            case (($currX26Function >= 0x10) && ($currX26Function <= 0x1f)):

                // X/26 place G0 character with diacritical mark from G2
                $currCol = ord(substr($x26Triplet, 0, 1));
                $currX26Char = substr($x26Triplet, 2, 1);
                if (isset($g2DiacriticalMarks[$currX26Function - 0x10])) {
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


function g0ToHtml($ttxString, $ttxLanguage) {

    // replace national G0 characters by HTML entities

    switch ($ttxLanguage) {
        case 'de-DE':
            return str_replace(
                ['&', chr(0x24), '@', '[', '\\', ']', '`', '{', '|', '}', '~', chr(0x7f), '<', '>', '"', '\''],
                ['&amp;', '$', '&sect;', '&Auml;', '&Ouml;', '&Uuml;', '&deg;', '&auml;', '&ouml;', '&uuml;', '&szlig;', '&#9632;', '&lt;', '&gt;', '&quot;', '&#39;'],
                $ttxString
            );
            break;
        case 'en-GB':
            return str_replace(
                ['&', chr(0x23), chr(0x24), '@', '[', '\\', ']', chr(0x5e), chr(0x5f), '`', '{', '|', '}', '~', chr(0x7f), '<', '>', '"', '\''],
                ['&amp;', '&pound;', '$', '@', '&#8592;', '&frac12;', '&#8594;', '&#8593;', '#', '&#8212;', '&frac14;', '&#9553;', '&frac34;', '&divide;', '&#9632;', '&lt;', '&gt;', '&quot;', '&#39;'],
                $ttxString
            );
        default:
            return str_replace(
                ['&', chr(0x7f), '<', '>', '"', '\''],
                ['&amp;', '&#9632;', '&lt;', '&gt;', '&quot;', '&#39;'],
                $ttxString
            );
    }
        
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
