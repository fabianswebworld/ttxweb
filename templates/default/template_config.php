<?php

// template-specific configuration file

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


const ROW_0_CUSTOMHEADER = '<span class="bg0 fg7"><span class="fg7"> %page%.%sub%   </span><span class="fg3">  FWWtext</span> <span class="fg7">%weekday% %day%.%month%.%year% </span><span class="fg3">%hh%:%mm%:%ss%</span></span>';
const TTXWEB_VERSION_EXT = '';     // build or template specific version suffix

?>