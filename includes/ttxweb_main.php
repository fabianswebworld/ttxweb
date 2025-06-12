<?php

// ttxweb.php teletext document renderer
// version: 1.6.1.704 (2025-06-12)
// (c) 2023, 2024, 2025 Fabian Schneider - @fabianswebworld

// GLOBAL DEFINITIONS

const TTXWEB_VERSION = '1.6.1.704 (2025-06-12)';       // version string

// for user and template configuration see ttxweb_config.php

// valid URL GET parameters:
// level15   - 0 (decode only level 1.0 characters) | 1 (decode level 1.5 characters, default)
// header    - 0 (show locally generated header, default) | 1 (show actual row 0 from EP1 file)
// page      - 100 (default) .. 899 - the page number to be displayed
// sub       - 1 (default) .. 99 - the subpage number to be displayed
// reveal    - 0 (hide concealed text, default) | 1 (reveal concealed text on load)
// refresh   - seconds for auto refresh via XHR, 0 = disabled (default: set by TTXWEB_REFRESH in ttxweb_config.php)
// template  - temporary template name (default: set by TTXWEB_TEMPLATE in ttxweb_config.php)
// turn      - 1 = turn subpage on XHR refresh (default: turn according to TTXWEB_TURN_RATES in ttxweb_config.php)
// seqn0     - 1 = always display subpage 00 in custom header (default: only if page is in TTXWEB_TURN_RATES)
// xhr       - 1 = output ttxStage only (only used internally for XMLHttpRefresh via ttxweb.js)
// stream    - alternate teletext stream by loading a different config file ttxweb_config-<stream>.php


// FUNCTION DEFINITONS

function getPageNumbers() {

    // get page number and infer EP1 filename
    // also get existing pages and prev and next page

    global $pageNum, $subpageNum, $prevPageNum, $nextPageNum, $prevSubpageNum, $nextSubpageNum, $numSubpages;

    $pageNum = isset($_GET['page']) ? $_GET['page']:'100';
    $pageNum = sprintf('%03d', $pageNum);
    if ($pageNum < 100) $pageNum = 100;
    if ($pageNum > 899) $pageNum = 899;

    if (isset($_GET['sub']) && empty($_GET['sub'])) unset($_GET['sub']);
    $subpageNum = isset($_GET['sub']) ? $_GET['sub']:'01';
    if ($subpageNum < 1) $subpageNum = 1;
    if ($subpageNum > 99) $subpageNum = 99;
    $subpageNum = sprintf('%02d', $subpageNum);

    // get current page filename
    $currEp1Filename = getEp1Filename($pageNum, $subpageNum);

    // get all existing pages
    $cwd = getcwd();
    chdir(EP1_PATH);
    $filePattern = str_replace(array('%ppp%', '%ss%'), array('[1-8][0-9][0-9]', '01'), EP1_PATTERN);
    $ep1FileList = glob($filePattern);
    chdir($cwd);

    // get current page's index in page list
    $currentIdx = array_search(str_replace(array('%ppp%', '%ss%'), array($pageNum, '01'), EP1_PATTERN), $ep1FileList);

    // get next and previous page index
    if ($currentIdx === false) {
        // if the current page is not in index, look up the next existing one
        // and calculate from there
        for ($i = $pageNum; (($i <= 899) && ($currentIdx === false)); $i++) {
            $currentIdx = array_search(str_replace(array('%ppp%', '%ss%'), array(sprintf('%03d', $i), '01'), EP1_PATTERN), $ep1FileList);
        }
        if ($currentIdx === false) {
            $nextIdx = 0;
            $prevIdx = sizeof($ep1FileList) - 1;
        }
        else {
            $nextIdx = $currentIdx;
            $prevIdx = $currentIdx - 1;
        }
    }
    else {
        // current page is in the file list, normal behavior
        $nextIdx = $currentIdx + 1;
        $prevIdx = $currentIdx - 1;
        if ($nextIdx == sizeof($ep1FileList)) $nextIdx = 0;
        if ($prevIdx == -1) $prevIdx = sizeof($ep1FileList) - 1;
    }

    // jump over 0-byte files (needed for some Sophora installations) or corrupt (too small) files
    for ( ; (($nextIdx < count($ep1FileList)) && (filesize(EP1_PATH . $ep1FileList[$nextIdx]) < 96)); $nextIdx++);
    for ( ; (($prevIdx >= 0) && (filesize(EP1_PATH . $ep1FileList[$prevIdx]) < 96)); $prevIdx--);

    // extract page numbers from filenames in list
    if (isset($ep1FileList[$nextIdx])) {
        $nextPageNum = substr($ep1FileList[$nextIdx], strpos(EP1_PATTERN, '%ppp%'), 3);
    }

    if (isset($ep1FileList[$prevIdx])) {
        $prevPageNum = substr($ep1FileList[$prevIdx], strpos(EP1_PATTERN, '%ppp%'), 3);
    }

    // get number of subpages
    $ep1SubpageFileList = glob(EP1_PATH . str_replace(array('%ppp%', '%ss%'), array($pageNum, '[0-9][0-9]'), EP1_PATTERN));
    for ($subpageIdx = 0; $subpageIdx < count($ep1SubpageFileList); $subpageIdx++) {
        if (filesize($ep1SubpageFileList[$subpageIdx]) == 0) unset($ep1SubpageFileList[$subpageIdx]);
    }
    $numSubpages = count($ep1SubpageFileList);
    if ($subpageNum > $numSubpages) $subpageNum = sprintf('%02d', $numSubpages);

    // clamp result values
    if ($prevPageNum < 100) $prevPageNum = 100;
    if ($nextPageNum > 899) $nextPageNum = 899;

    $prevSubpageNum = $subpageNum - 1;
    if ($prevSubpageNum < 1) $prevSubpageNum = 1;

    $nextSubpageNum = $subpageNum + 1;
    if ($nextSubpageNum > $numSubpages) $nextSubpageNum = $numSubpages;

}


function getEp1Filename($pageNum, $subpageNum) {

    // generate path to EP1 file
    $ep1Filename = EP1_PATH . str_replace(array('%ppp%', '%ss%'), array($pageNum, sprintf('%02d', $subpageNum)), EP1_PATTERN);
    return $ep1Filename;

}


function pageExists($pageNum, $subpageNum) {

    // return whether a given page physically exists
    $pageFilename = getEp1Filename($pageNum, $subpageNum);
    return (file_exists($pageFilename) && (filesize($pageFilename) > 0));

}


// MAIN CODE STARTS HERE

// determine configuration file
if (!empty($_GET['stream'])) {
    $streamName = str_replace('/', '', filter_var(strip_tags($_GET['stream']), FILTER_SANITIZE_SPECIAL_CHARS));
    $configFileName = 'ttxweb_config-' . $streamName . '.php';
    if (!file_exists('includes/' . $configFileName)) {
        $configFileName = 'ttxweb_config.php';
    }
}
else {
    $configFileName = 'ttxweb_config.php';
}

// include configuration
if (file_exists('includes/' . $configFileName)) {
    include($configFileName);
}
else {
    die('Cannot access configuration file. ttxweb cannot continue.');
}

// initialize some default values
$ttxLanguage = 'en-US';
if (defined('EP1_LANGUAGE')) {
    if (EP1_LANGUAGE != '') $ttxLanguage = EP1_LANGUAGE;
}
$refresh = 0;
if (defined('TTXWEB_REFRESH')) {
    if (is_numeric($refresh)) $refresh = TTXWEB_REFRESH;
}
$ttxTurnRates = array();
if (defined('TTXWEB_TURN_RATES')) {
    if (TTXWEB_TURN_RATES != '') $ttxTurnRates = TTXWEB_TURN_RATES;
}

// read and sanitize URL parameters
$level15 = true;
if (isset($_GET['level15'])) {
    if ($_GET['level15'] == '0') $level15 = false;
}
$showHeader = false;
if (isset($_GET['header'])) {
    if ($_GET['header'] == '1') $showHeader = true;
}
$reveal = false;
if (isset($_GET['reveal'])) {
    if ($_GET['reveal'] == '1') $reveal = true;
}
if (isset($_GET['refresh'])) {
    if ($_GET['refresh'] != '') $refresh = abs(filter_var($_GET['refresh'], FILTER_SANITIZE_NUMBER_INT));
}
$xhr = false;
if (isset($_GET['xhr'])) {
    if ($_GET['xhr'] == '1') $xhr = true;
}
if (!empty($_GET['template'])) {
    $templateName = str_replace('/', '', filter_var(strip_tags($_GET['template']), FILTER_SANITIZE_SPECIAL_CHARS));
}
else {
    $templateName = TTXWEB_TEMPLATE;
}
if (empty($templateName)) { $templateName = 'default'; }

// build query string to pass to the next query
// with some more sanitizations
$queryArray = $_GET;
unset($queryArray['page']);
unset($queryArray['sub']);
unset($queryArray['xhr']);
unset($queryArray['seqn0']);
if (isset($queryArray['level15'])) {
    if ($queryArray['level15'] == '1') {
        unset($queryArray['level15']);
    }
}
if (isset($queryArray['header'])) {
    if ($queryArray['header'] == '0') {
        unset($queryArray['header']);
    }
}
if (isset($queryArray['reveal'])) {
    if ($queryArray['reveal'] == '0') {
        unset($queryArray['reveal']);
    }
}
if (isset($queryArray['refresh'])) {
    $queryArray['refresh'] = abs(filter_var($queryArray['refresh'], FILTER_SANITIZE_NUMBER_INT));
}
if (isset($queryArray['turn'])) {
    if (!(($queryArray['turn'] == '1') || ($queryArray['turn'] == '0'))) {
        unset($queryArray['turn']);
    }
}
$queryString = htmlspecialchars(http_build_query($queryArray));
if (!empty($queryString)) {
    $queryString = '&amp;' . $queryString;
}

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

// read pattern for custom header row, template-specific configuration
$ttxRow0Pattern = '';
if (defined('ROW_0_CUSTOMHEADER')) {
    $ttxRow0Pattern = ROW_0_CUSTOMHEADER;
}
else {
    // if ROW_0_CUSTOMHEADER not set, show header from EP1 file by default
    $showHeader = true;
}

// read pattern for page title
$ttxPageTitle = '';
if (defined('TTXWEB_PAGE_TITLE')) {
    $ttxPageTitle = TTXWEB_PAGE_TITLE;
}

// construct version string
$versionString = TTXWEB_VERSION;
if (!empty(TTXWEB_VERSION_EXT)) { $versionString = explode(' ', $versionString)[0] . '-' . TTXWEB_VERSION_EXT . ' ' . explode(' ', $versionString)[1]; }

// initialize HTML output
header('Content-type: text/html; charset=utf-8');
ini_set('default_charset', 'utf-8');

// get the desired page numbers
getPageNumbers();

// determine turnrates
$turn = false;
$seqn0 = false;
$origRefresh = $refresh;
$origNextSubpageNum = $nextSubpageNum;
if (isset($ttxTurnRates[$pageNum]) && !isset($_GET['sub'])) {
    // page has a turnrate set in TTXWEB_TURN_RATES, so always
    // display 00 as subpage number by default
    $refresh = $ttxTurnRates[$pageNum];
    $turn = true;
    $seqn0 = true;
    $nextSubpageNum = 1;
}
if (isset($_GET['turn'])) {
    if ($_GET['turn'] == '1') {
        $turn = true;
        $nextSubpageNum = 1;
    }
    if ($_GET['turn'] == '0') {
        $turn = false;
        $seqn0 = false;
        $refresh = $origRefresh;
        $nextSubpageNum = $origNextSubpageNum;
    }
}
if (isset($_GET['refresh'])) {
        $refresh = $origRefresh;
}

// overwrite pages with turnrate if seqn0 URL parameter is set
// 1 = always show subpage number 00
if (isset($_GET['seqn0'])) {
    if ($_GET['seqn0'] == '1') {
        $seqn0 = true;
    }
    if ($_GET['seqn0'] == '0') {
        $seqn0 = false;
    }
}

// construct page title for static display (if js is disabled)
$pageTitle = str_replace('%page%', $pageNum, $ttxPageTitle);
$pageTitle = str_replace('%sub%', $subpageNum, $pageTitle);

// include header template if not requested from XMLHttpRequest
if (!($xhr)) include($templateFolder . '/header.php');

// write ttxStage into HTML
echo '<div id="ttxStage">' . "\n\n";

// write environment variables for ttxweb.js scripts into HTML
echo ' <div id="ttxEnv" style="display: none;">
  <pre id="ttxRow0Header">'     . intval($showHeader) . '</pre>
  <pre id="ttxRow0Template">'   . $ttxRow0Pattern     . '</pre>
  <pre id="ttxPageTitle">'      . $ttxPageTitle       . '</pre>
  <pre id="ttxLanguage">'       . $ttxLanguage        . '</pre>
  <pre id="ttxPageNum">'        . $pageNum            . '</pre>
  <pre id="ttxPrevPageNum">'    . $prevPageNum        . '</pre>
  <pre id="ttxNextPageNum">'    . $nextPageNum        . '</pre>
  <pre id="ttxSubpageNum">'     . intval($subpageNum) . '</pre>
  <pre id="ttxNumSubpages">'    . $numSubpages        . '</pre>
  <pre id="ttxReveal">'         . intval($reveal)     . '</pre>
  <pre id="ttxRefresh">'        . $refresh            . '</pre>
  <pre id="ttxTurn">'           . intval($turn)       . '</pre>
  <pre id="ttxSeqn0">'          . intval($seqn0)      . '</pre>
 </div>

';

// include teletext decoder functions
include('ttxweb_decoder.php');

// now, render the given EP1 (or AST etc.) file
renderTeletextFile(getEp1Filename($pageNum, $subpageNum), $level15, $reveal);

// end ttxStage
echo "</div>\n\n";

// add version string to output
if (!($xhr)) echo '<!-- generated by ttxweb teletext document renderer version: ' . $versionString . ' -->' . "\n";

// include navigation
if (!($xhr)) include($templateFolder . '/navigation.php');

// include footer template
if (!($xhr)) include($templateFolder . '/trailer.php');

?>
