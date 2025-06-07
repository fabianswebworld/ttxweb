<?php

// ttxweb.php EP1 teletext document renderer

// configuration

const TTXWEB_TEMPLATE    = 'default';                         // folder to use for HTML templates, default: 'default'
const TTXWEB_REFRESH     = 60;                                // seconds for automatic refresh via XHR, default: 0 = disabled
const TTXWEB_TURN_RATES  = [100 => 10, 200 => 8];             // array of pages that should turn automatically, and how fast

const EP1_PATH           = 'ep1/';                            // relative path to EP1 files
const EP1_PATTERN        = 'P%ppp%S%ss%.EP1';                 // pattern for the EP1 filenames (%ppp% = page, %ss% = subpage)
const EP1_LANGUAGE       = 'de-DE';                           // teletext language (currently only 'de-DE'; 'en-GB')
const EP1_DECODE_X26     = true;                              // decode packet X/26 (level 1.5 characters)
const EP1_ALWAYS_REVEAL  = false;                             // always reveal concealed text on load

const NO_PAGE_STRING     = 'Seite nicht vorhanden';           // string for 'Page not found'
const TO_PAGE_STRING     = 'Zu Seite';                        // string for 'Jump to page...'
const TO_SUBPAGE_STRING  = 'Zu Unterseite';                   // string for 'Jump to subpage...'


// the following configuration of custom header line (row 0) and page title may be moved
// to template_config.php in template folder if template-specific configuration is desired!

const ROW_0_CUSTOMHEADER = '<span class="bg0 fg7"><span class="fg7"> %page%.%sub%   </span><span class="fg6">   ttxweb</span> <span class="fg7">%weekday% %day%.%month%.%year% </span><span class="fg6">%hh%:%mm%:%ss%</span></span>';

// if ROW_0_CUSTOMHEADER is set and not empty, row 0 is generated locally from this
// template string instead of displaying row 0 from the EP1 file.

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


const TTXWEB_PAGE_TITLE  = 'Videotext-Seite %page%.%sub% | ttxweb';

// in TTXWEB_PAGE_TITLE you can define a template for the HTML title tag.
// possible placeholders are:

// %page%    - the current page number
// %sub%     - the current subpage number


const TTXWEB_VERSION_EXT = '';     // build or template specific version suffix

?>
