#  ![Logo](/doc/ttxweb.png "ttxweb Logo") ttxweb - Teletext-to-HTML generator

## What is ttxweb?

ttxweb is a web application that brings the "old-fashioned" teletext (Videotext) to the web in a simple way. This is mostly interesting for broadcasters. All Level 1.0 teletext attributes such as flash and double height are supported; additionally, some Level 2.5 attributes (double width and double size) are supported. Decoding of enhanced Level 1.5 characters via packet X/26 is supported as well if they are carried inside the input file. On the generated HTML pages, page numbers (only references to existing pages) are automatically rendered as clickable hyperlinks. Similarly, "next" indicators such as ">>" and "->" are rendered as hyperlinks to the next subpage or page. Additionally, web links and email links are rendered as hyperlinks as well.

Another nice feature is that the pages can update automatically at a configurable refresh interval. The update happens smoothly via XMLHttpRequest (no full page reload), so that just the teletext portion of the page is reloaded.

To use ttxweb, all you have to do is install the PHP scripts including the additional files (CSS, JS etc.) on a web server and ensure that the teletext pages are synchronized as EP1 or AST files in a configurable folder that can be accessed from the PHP script (e.g. via FTP).

## Live Demo

See the live demo at https://www.fabianswebworld.de/ttxweb.

See it in production use at:

- https://www.hr-text.hr-fernsehen.de (Hessischer Rundfunk, hr-text)
- https://www.swrfernsehen.de/videotext/ (Südwestrundfunk, SWR-Text)

## Screenshots

![Screenshot - Page 100](/doc/demo_p100.png "Page 100")

![Screenshot - Test page](/doc/demo_p896.png "Test page")

![Screenshot - Output of 'retro-tv' template](/doc/demo_retro_tv.png "Output of 'retro-tv' template")

## Installation

### Preparation

#### System requirements

A web server with PHP >= 5.6 is required. The PHP intl extension is recommended, but not required (English date formats are used as a fallback). There are no other requirements (like mySQL etc.).

#### Input files

The teletext pages to be displayed must be in EP1 format (Softel) or AST format (ASTET). These file formats can be processed or exported by all common teletext systems (Softel, Broadstream etc.). If required, an adaptation to other formats (TTI, TTX, ETT, etc.) is conceivable with little effort. The ttxweb EP1 parser will automatically detect the flavor of EP1 file: plain Level 1.0 files without X/26 data as well as both flavors of Softel EP1 files with X/26 data (Flair and TAP). Note: To enable X/26 export in the TAP process, you'll have to set the (undocumented) environment variable TRA_TAP_X26 to 1 on the Transmission machine which hosts TAP.EXE. For AST files, X/26 data is detected and decoded automatically as well, if it is included as a regular packet 26 inside the file (any packet/row order is possible).

The input files must be suitably synchronized to a folder accessible to the PHP script in the filename format PxxxSyy.EP1 by default (where xxx = page number, yy = subpage number). The file name format can be adjusted in the script if required.

Files with a size of 0 bytes (e.g. used as "deletion files" in some Sophora installations) are considered non-existent; corresponding pages are treated as non-existent pages, i.e. skipped.

##### Important note

Historically, only the EP1 file format was supported by ttxweb, so the term "EP1 file" is used synonymously with "input file" throughout this documentation and also throughout the code. This explains function names such as "getEp1Filename()" which is used to get the input filename. The same applies to some configuration parameters named "EP1_*", which apply to all input file formats as well.

### Deployment

The PHP script and all auxiliary files must be copied to the web server and configured according to the section below. The **g1.zip** file in the **g1** folder needs to be unzipped on the server (faster deployment than transferring 1024 separate files). The transfer of the EP1/AST/etc. input files must be set up. In order to prevent users from directly accessing the input files via the web server, the commented lines of the supplied .htaccess file in the ttxweb folder should be uncommented (as long as the server supports Rewrite Rules).

## Configuration and customizations

The following configuration options are available to configure the behavior of ttxweb and adapt the output to your own website design:

- **includes/ttxweb_config.php:**
  - const **TTXWEB_TEMPLATE** - template name, i.e. folder to use for HTML templates (must be a subfolder in the **templates/** folder, default: 'default')
  - const **TTXWEB_REFRESH** - seconds for automatic refresh via XHR (default: 0 = disabled)
  - const **TTXWEB_TURN_RATES** - array of pages that should turn automatically, and how fast (example see file or below)  
    **Example:**

    `const TTXWEB_TURN_RATES = [100 => 8, 170 => 3, 198 => 20, 220 => 6, 280 => 6];`

  - const **EP1_PATH** - Path to the input files (default: 'ep1/')
  - const **EP1_PATTERN** - pattern for the input filenames (where %ppp% = page, %ss% = subpage; a value for this *must* be provided, no default value)  
    **Example:**

    `const EP1_PATTERN = 'P%ppp%S%ss%.EP1';`

  - const **EP1_LANGUAGE** - Teletext language (default: 'en-US', possible values: 'de-DE' | 'en-GB' | 'en-US')
  - const **EP1_DECODE_X26** - Decode packet X/26 (level 1.5 characters) (default: true)
  - const **EP1_ALWAYS_REVEAL** - Always reveal concealed text on load (default: false)
  - const **NO_PAGE_STRING** - String for 'Page not found' (default: empty)
  - const **TO_PAGE_STRING** - String for 'Jump to page' (default: empty)
  - const **TO_SUBPAGE_STRING** - String for 'Jump to subpage' (default: empty)

  - const **ROW_0_CUSTOMHEADER** - Template for row 0 (page header). If not set or empty, row 0 from the EP1 file is displayed.
    - Format for **ROW_0_CUSTOMHEADER**:

      `<span>` elements can be used with the classes from **ttxweb_main.css** (fg*n*, bg*n*, dh etc.) to format colors etc. Furthermore, the following tokens will be replaced:
      - **%page%** - Current page number
      - **%sub%** - Current subpage
      - **%weekday%** - Current day of the week (2 characters) in the configured language (see EP1_LANGUAGE)
      - **%day%** - Current day (2 digits)
      - **%month%** - current month (2 digits)
      - **%year%** - Current year (2 digits)
      - **%hh%** - Current hour (2 digits)
      - **%mm%** - current minute (2 digits)
      - **%ss%** - current second (2 digits)
    - Other formats would have to be added to ttxweb.js.  
      **Example:**
      
      `const ROW_0_CUSTOMHEADER = '<span class="bg0 fg7"><span class="fg7"> %page%.%sub% </span><span class="fg6">ttxweb  </span><span class="fg7">%weekday% %day%.%month%.%year% </span><span class="fg6">%hh%:%mm%:%ss%</span></span >';`
 
  - const **TTXWEB_PAGE_TITLE** - Template for page title, i.e. browser window title (a value for this *must* be provided, no default value)
    - Format for **TTXWEB_PAGE_TITLE**:
   
      Arbitrary string. The following tokens will be replaced:
      - **%page%** - Current page number
      - **%sub%** - Current subpage
    - **Example:**
      
      `const TTXWEB_PAGE_TITLE  = 'Teletext Page %page%.%sub% | ttxweb';`

- **templates/\<templatename\>/template_config.php:**
  - Template configuration file. Any configuration definitions (const) from ttxweb_config.php may be moved to this file if template-specific configuration is desired. Note that no configuration definition may exist in both configuration files at the same time.

- **templates/\<templatename\>/navigation.php:**  
  - The "quick links" to the individual teletext pages can be adjusted directly in the HTML code or, if necessary, removed entirely. Also, the behavior and look of the navigation itself may be altered if necessary.

- **templates/\<templatename\>/header.php:**
  - HTML template which is output before the actual teletext output. The following variables can be used in this template (in the form `<?php echo $variable; ?>`):
    - **$pageNum** - Current page number
    - **$nextPageNum** - Next available page
    - **$prevPageNum** - Previous available page
    - **$subpageNum** - Current subpage
    - **$nextSubpageNum** - Next subpage
    - **$prevSubpageNum** - Previous subpage
    - **$numSubpages** - Number of subpages
    - **$pageTitle** - Page title as defined via TTXWEB_PAGE_TITLE
    - **$fileTimestamp** - Timestamp (modification time) of the EP1/AST file currently rendered

- **templates/\<templatename\>/trailer.php:**
  - HTML template, which is output after the actual teletext output. The same variables apply as in **header.php**.

- **templates/\<templatename\>/css/template_style.css:**
  - Customize styles of the navigation box if necessary. Avoid changing the main Teletext style classes, although you *can* carefully modify text sizes there. Refer to **css/ttxweb_ttx.css** for details.

## GET parameters at runtime

The following URL parameters are supported (if provided, they override the values configured in ttxweb_config.php where applicable):

- **level15** - 0 (decode only level 1.0 characters) | 1 (also decode level 1.5 characters, **default**)
- **header** - 0 (Show locally generated header, **default**) | 1 (Show Row 0 from EP1 file)
- **page** - 100 (**default**) .. 899 - Page number to be displayed
- **sub** - 1 (**default**) .. 99 - Subpage to be displayed
- **reveal** - 0 (hide concealed text) | 1 (reveal concealed text on page load) (default: set by EP1_ALWAYS_REVEAL in ttxweb_config.php)
- **refresh** - seconds for auto refresh via XHR, 0 = disabled (default: set by TTXWEB_REFRESH in ttxweb_config.php)
- **template** - override configured template name (default: set by TTXWEB_TEMPLATE in ttxweb_config.php)
- **turn** - 0 (do not automatically turn subpages) | 1 (turn subpage on every XHR refresh) | *not set* (turn according to TTXWEB_TURN_RATES in ttxweb_config.php, **default**)
- **seqn0** - 0 (display actual subpage number in header) | 1 (display subpage number in header as *00*; useful for "animated" pages that otherwise have no multi-page content and would be transmitted with 0000 instead of SEQN in linear transmission) | *not set* (show subpage number as *00* only for pages defined in TTXWEB_TURN_RATES, **default**)
- **xhr** - this parameter is used internally to implement the XMLHttpRequest refresh function. By setting it to 1 you can get only the ttxStage part of the page if you want to embed it in your own XMLHttpRequest applications.
- **stream** - present an alternate teletext stream by loading a different config file, *ttxweb_config-**\<stream\>**.php*, possibly pointing to a different input file folder and/or using a different input filename pattern

# Contact the author

Questions, suggestions, requests please send via the contact form or the other available contact means at www.fabianswebworld.de. Please also note the license terms in the LICENSE.md file (available in German only).
