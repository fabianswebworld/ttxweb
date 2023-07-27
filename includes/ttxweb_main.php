<?php

// ttxweb.php EP1 teletext document renderer
// version: 1.3.1.625 (2023-07-27)
// (c) 2023 Fabian Schneider - @fabianswebworld

// global definitions
const TTXWEB_VERSION = '1.3.1.625 (2023-07-27)';       // version string

// for user and template configuration see ttxweb_config.php

// valid get URL parameters:
// level15   - 0 (decode only level 1.0 characters) | 1 (decode level 1.5 characters, default)
// header    - 0 (show locally generated header, default) | 1 (show actual row 0 from EP1 file)
// page      - 100 (default) .. 899 - the page number to be displayed
// sub       - 1 (default) .. 99 - the subpage number to be displayed
// reveal    - 0 (hide concealed text, default) | 1 (reveal concealed text on load)
// refresh   - seconds for auto refresh via XHR, 0 = disabled (default: set by TTXWEB_REFRESH in ttxweb_config.php)
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

    // get current page's index in page list
    $currentIdx = array_search('P' . $pageNum . 'S01.EP1', $ep1FileList);

    // get next and previous page index
    if ($currentIdx === false) {
        // if the current page is not in index, look up the next existing one
        // and calculate from there
        for ($i = $pageNum; (($i <= 899) && ($currentIdx === false)); $i++) {
            $currentIdx = array_search('P' . sprintf('%03d', $i) . 'S01.EP1', $ep1FileList);
        }
        $nextIdx = $currentIdx;
        $prevIdx = $currentIdx - 1;
    }
    else {
        // current page is in the file list, normal behavior
        $nextIdx = $currentIdx + 1;
        $prevIdx = $currentIdx - 1;
    }

    // jump over 0-byte files (needed for some Sophora installations)
    for ( ; (($nextIdx < count($ep1FileList)) && (filesize(EP1_PATH . $ep1FileList[$nextIdx]) == 0)); $nextIdx++);
    for ( ; (($prevIdx >= 0) && (filesize(EP1_PATH . $ep1FileList[$prevIdx]) == 0)); $prevIdx--);

    // extract page numbers from filenames in list
    if (isset($ep1FileList[$nextIdx])) {
        $nextPageNum = substr($ep1FileList[$nextIdx], 1, 3);
    }

    if (isset($ep1FileList[$prevIdx])) {
        $prevPageNum = substr($ep1FileList[$prevIdx], 1, 3);
    }

    // get number of subpages
    $ep1SubpageFileList = glob(EP1_PATH . substr(pathinfo($currEp1Filename, PATHINFO_FILENAME), 0, 4) . 'S[0-9][0-9].EP1');
    $numSubpages = count($ep1SubpageFileList);

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
    $ep1Filename = EP1_PATH . 'P' . $pageNum . 'S' . sprintf("%02d", $subpageNum) . '.EP1';
    return $ep1Filename;

}


function pageExists($pageNum, $subpageNum) {

    // return whether a given page physically exists
    $pageFilename = getEp1Filename($pageNum, $subpageNum);
    return (file_exists($pageFilename) && (filesize($pageFilename) > 0));

}


// include configuration
include('ttxweb_config.php');

// initialize some default values
$ttxLanguage = EP1_LANGUAGE;
if ($ttxLanguage == '') $ttxLanguage = 'en-US';
$refresh = TTXWEB_REFRESH;
if (!is_numeric($refresh)) $refresh = 0;

// read and sanitize URL parameters
if ($_GET['level15'] == '0') { $level15 = false; } else { $level15 = true; }
if ($_GET['header']  == '1') { $showHeader = 1;  } else { $showHeader = 0; }
if ($_GET['reveal']  == '1') { $reveal = true;  } else { $reveal = false; }
if ($_GET['xhr']  == '1') { $xhr = true;  } else { $xhr = false; }
if ($_GET['refresh'] != '') { $refresh = abs(filter_var($_GET['refresh'], FILTER_SANITIZE_NUMBER_INT)); }
if (!empty($_GET['template'])) { $templateName = $_GET['template']; } else { $templateName = TTXWEB_TEMPLATE; }
if (empty($templateName)) { $templateName = 'default'; }

// build query string to pass to the next query
$queryArray = $_GET;
unset($queryArray['page']);
unset($queryArray['sub']);
unset($queryArray['xhr']);
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

// include header template if not requested from XMLHttpRequest
if (!($xhr)) include($templateFolder . '/header.php');

// write environment variables for ttxweb.js scripts into HTML
if (!($xhr)) echo '<div id="ttxEnv">
<pre id="ttxRow0Header">'   . $showHeader        . '</pre>
<pre id="ttxRow0Template">' . ROW_0_CUSTOMHEADER . '</pre>
<pre id="ttxLanguage">'     . $ttxLanguage       . '</pre>
<pre id="ttxPageNum">'      . $pageNum           . '</pre>
<pre id="ttxSubpageNum">'   . $subpageNum        . '</pre>
<pre id="ttxReveal">'       . intval($reveal)    . '</pre>
<pre id="ttxRefresh">'      . $refresh           . '</pre>
</div>

';

// include teletext decoder functions
include('ttxweb_decoder.php');

// now, render the given EP1 file
renderTeletextFile(getEp1Filename($pageNum, $subpageNum), $level15, $reveal);

// add version string to output
if (!($xhr)) echo '<!-- generated by ttxweb EP1 teletext document renderer version: ' . $versionString . ' -->' . "\n";

// include navigation
if (!($xhr)) include($templateFolder . '/navigation.php');

// include footer template
if (!($xhr)) include($templateFolder . '/trailer.php');

?>
