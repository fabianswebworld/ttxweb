<?php

// ttxweb.php EP1 teletext document renderer
// version: 1.1.1.556 (2023-07-14)
// (c) 2023 Fabian Schneider - @fabianswebworld

// configuration

const TTXWEB_VERSION     = '1.1.1.556 (2023-07-14)';       // version string
const EP1_PATH           = 'ep1/';                            // path to EP1 files
const EP1_LANGUAGE       = 'de-DE';                           // teletext language (currently only 'de-DE'; 'en-GB')
const EP1_DECODE_X26     = true;                              // decode packet X/26 (level 1.5 characters)
const NO_PAGE_STRING     = 'Seite nicht vorhanden';           // string for 'Page not found'

// configuration of custom header line (row 0)

// if ROW_0_CUSTOMHEADER is set and not empty, row 0 is generated locally from this
// template string instead of displaying row 0 from the EP1 file.

const ROW_0_CUSTOMHEADER = '<span class="bg0 fg7"><span class="fg7"> %page%.%sub%     </span><span class="fg6">ttxweb  </span><span class="fg7">%weekday% %day%.%month%.%year% </span><span class="fg6">%hh%:%mm%:%ss%</span></span>';

// you can use classes from ttxweb_main.css (fg<n>, bg<n> etc.) for formatting
// and the following tokens will be replaced:

// %page%    - the current page number
// %sub%     - the current subpage number
// %weekday% - the current short weekday in the configured language
// %day%     - the current day (2 digits)
// %month%   - the current month (2 digits)
// %year%    - the current year (2 digits)
// %hh%      - the current hour (2 digits), 24-hour format
// %mm%      - the current minute (2 digits)
// %ss%      - the current seconds (2 digits)

// in case you need other time/date formats please edit or extend
// js/ttxweb.js to fit your needs.

// get URL parameters

// level15   - 0 (decode only level 1.0 characters) | 1 (decode level 1.5 characters, default)
// header    - 0 (show locally generated header, default) | 1 (show actual row 0 from EP1 file)
// page      - 100 (default) .. 899 - the page number to be displayed
// sub       - 1 (default) .. 99 - the subpage number to be displayed

if ($_GET['level15'] == '0') { $level15 = false; } else { $level15 = true; }
if ($_GET['header']  == '1') { $showHeader = 1;  } else { $showHeader = 0; }

// initialize HTML output
header('Content-type: text/html; charset=utf-8');
ini_set('default_charset', 'utf-8');


function getPageNumbers() {

    // get page number and infer EP1 filename
    // also get existing pages and prev and next page

    global $pageNum, $subpageNum, $prevPageNum, $nextPageNum, $prevSubpageNum, $nextSubpageNum, $numSubpages, $ep1FileList;

    $pageNum = isset($_GET['page']) ? $_GET['page']:'100';
    $pageNum = sprintf("%03d", $pageNum);
    if ($pageNum < 100) $pageNum = 100;
    if ($pageNum > 899) $pageNum = 899;

    $subpageNum = isset($_GET['sub']) ? $_GET['sub']:'01';
    if ($subpageNum < 1) $subpageNum = 1;
    if ($subpageNum > 99) $subpageNum = 99;
    $subpageNum = sprintf("%02d", $subpageNum);

    // get current page filename
    $currEp1Filename = getEp1Filename($pageNum, $subpageNum);

    // get all existing pages
    $cwd = getcwd();
    chdir(EP1_PATH);
    $ep1FileList = glob('P[1-8][0-9][0-9]S01.EP1');
    chdir($cwd);

    // get next and previous page
    $currentIdx = array_search('P' . $pageNum . 'S01.EP1', $ep1FileList);

    if ($currentIdx === false) {
        for ($i = $pageNum; (($i <= 899) && ($currentIdx === false)); $i++) {
            $currentIdx = array_search('P' . sprintf('%03d', $i) . 'S01.EP1', $ep1FileList);
        }
        $nextIdx = $currentIdx;
        $prevIdx = $currentIdx - 1;
    }
    else {
        $nextIdx = $currentIdx + 1;
        $prevIdx = $currentIdx - 1;
    }


    if (isset($ep1FileList[$nextIdx])) {
        $nextPageNum = substr($ep1FileList[$nextIdx], 1, 3);
    }

    if (isset($ep1FileList[$prevIdx])) {
        $prevPageNum = substr($ep1FileList[$prevIdx], 1, 3);
    }

    // get number of subpages
    $ep1SubpageFileList = glob(EP1_PATH . substr(pathinfo($currEp1Filename, PATHINFO_FILENAME), 0, 4) . 'S[0-9][0-9].EP1');
    $numSubpages = count($ep1SubpageFileList);

    // calculate result values
    if ($prevPageNum < 100) $prevPageNum = 100;
    if ($nextPageNum > 899) $nextPageNum = 899;

    $prevSubpageNum = $subpageNum - 1;
    if ($prevSubpageNum < 1) $prevSubpageNum = 1;

    $nextSubpageNum = $subpageNum + 1;
    if ($nextSubpageNum > $numSubpages) $nextSubpageNum = $numSubpages;

}


function getEp1Filename($pageNum, $subpageNum) {

    // generate path to EP1 file

    $ep1Filename = EP1_PATH . 'P' . $pageNum . 'S' . $subpageNum . '.EP1';
    return $ep1Filename;

}


getPageNumbers();

// include header template
include('templates/header.php');

// write environment variables for ttxweb.js scripts into html
echo '<div id="ttxEnv">
<pre id="ttxRow0Header">' . $showHeader . '</pre>
<pre id="ttxRow0Template">' . ROW_0_CUSTOMHEADER . '</pre>
<pre id="ttxLanguage">' . EP1_LANGUAGE . '</pre>
<pre id="ttxPageNum">' . $pageNum . '</pre>
<pre id="ttxSubpageNum">' . $subpageNum . '</pre>
</div>

';

// include navigation functions
include('ttxweb_nav.php');

// include teletext decoder functions
include('ttxweb_decoder.php');

// now, render the given EP1 file
renderEp1File(getEp1Filename($pageNum, $subpageNum));

// render navigation
renderNavigation($pageNum, $subpageNum, $prevPageNum, $nextPageNum, $prevSubpageNum, $nextSubpageNum, $numSubpages);

// include footer template
include('templates/trailer.php');

?>
