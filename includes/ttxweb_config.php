<?php

// ttxweb.php EP1 teletext document renderer

// configuration

const TTXWEB_TEMPLATE    = 'default';                         // folder to use for HTML templates, default: 'default'
const EP1_PATH           = 'ep1/';                            // relative path to EP1 files
const EP1_LANGUAGE       = 'de-DE';                           // teletext language (currently only 'de-DE'; 'en-GB')
const EP1_DECODE_X26     = true;                              // decode packet X/26 (level 1.5 characters)
const EP1_ALWAYS_REVEAL  = false;                             // always reveal concealed text on load
const NO_PAGE_STRING     = 'Seite nicht vorhanden';           // string for 'Page not found'

// configuration of custom header line (row 0) moved to template_config.php in template folder!

?>