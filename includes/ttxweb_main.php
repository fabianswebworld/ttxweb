<?php

// ttxweb.php EP1 teletext document renderer
// version: 1.2.0.608 (2023-07-18)
// (c) 2023 Fabian Schneider - @fabianswebworld

// global definitions
const TTXWEB_VERSION = '1.2.0.608 (2023-07-18)';       // version string

// for user and template configuration see ttxweb_config.php

// valid get URL parameters:
// level15   - 0 (decode only level 1.0 characters) | 1 (decode level 1.5 characters, default)
// header    - 0 (show locally generated header, default) | 1 (show actual row 0 from EP1 file)
// page      - 100 (default) .. 899 - the page number to be displayed
// sub       - 1 (default) .. 99 - the subpage number to be displayed
// reveal    - 0 (hide concealed text, default) | 1 (reveal concealed text on load)
// template  - temporary template name (default: set by TTXWEB_TEMPLATE in ttxweb_config.php)


function getPageNumbers() {

    // get page number and infer EP1 filename
    // also get existing pages and prev and next page

    global $pageNum, $subpageNum, $prevPageNum, $nextPageNum, $prevSubpageNum, $nextSubpageNum, $numSubpages;

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


// include configuration
include('ttxweb_config.php');

// read URL parameters
if ($_GET['level15'] == '0') { $level15 = false; } else { $level15 = true; }
if ($_GET['header']  == '1') { $showHeader = 1;  } else { $showHeader = 0; }
if ($_GET['reveal']  == '1') { $reveal = true;  } else { $reveal = false; }
if (!empty($_GET['template'])) { $templateName = $_GET['template'];  } else { $templateName = TTXWEB_TEMPLATE; }
if (empty($templateName)) { $templateName = 'default'; }

// build query string to pass to the next query
$queryArray = $_GET;
unset($queryArray['page']);
unset($queryArray['sub']);
if ($queryArray['level15'] == '1') { unset($queryArray['level15']); }
if ($queryArray['header'] == '0') { unset($queryArray['header']); }
if ($queryArray['reveal'] == '0') { unset($queryArray['reveal']); }
$queryString = htmlspecialchars(http_build_query($queryArray));
if (!empty($queryString)) { $queryString = '&amp;' . $queryString; }

// build folder name for template files
if ($templateName != '') {
    $templateFolder = 'templates/' . basename($templateName);
}

// include template-specific configuration
$templateConfig = $templateFolder . '/template_config.php';
if (file_exists($templateConfig)) {
    include($templateConfig);
}
else {
    die('Cannot access template configuration. ttxweb cannot continue.');
}

// construct version string
$versionString = TTXWEB_VERSION;
if (!empty(TTXWEB_VERSION_EXT)) { $versionString = explode(' ', $versionString)[0] . '-' . TTXWEB_VERSION_EXT . ' ' . explode(' ', $versionString)[1]; }

// initialize HTML output
header('Content-type: text/html; charset=utf-8');
ini_set('default_charset', 'utf-8');

// get the desired page numbers
getPageNumbers();

// include header template
include($templateFolder . '/header.php');

// write environment variables for ttxweb.js scripts into HTML
echo '<div id="ttxEnv">
<pre id="ttxRow0Header">' . $showHeader . '</pre>
<pre id="ttxRow0Template">' . ROW_0_CUSTOMHEADER . '</pre>
<pre id="ttxLanguage">' . EP1_LANGUAGE . '</pre>
<pre id="ttxPageNum">' . $pageNum . '</pre>
<pre id="ttxSubpageNum">' . $subpageNum . '</pre>
</div>

';

// include teletext decoder functions
include('ttxweb_decoder.php');

// now, render the given EP1 file
renderTeletextFile(getEp1Filename($pageNum, $subpageNum), $level15, $reveal);

// add version string to output
echo '<!-- generated by ttxweb EP1 teletext document renderer version: ' . $versionString . ' -->' . "\n";

// include navigation
include($templateFolder . '/navigation.php');

// include footer template
include($templateFolder . '/trailer.php');

?>
