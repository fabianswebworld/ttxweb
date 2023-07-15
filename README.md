#  ![Logo](/ttxweb.png "ttxweb Logo") ttxweb - Teletext to HTML generator

## What is ttxweb?

ttxweb is a web application that brings the "old-fashioned" teletext (video text) to the web in a simple way. This is mostly interesting for broadcasters. All Level 1.0 teletext attributes such as flash and double height are supported; additionally, some Level 2.5 attributes (double width and double size) are supported. Decoding of enhanced Level 1.5 characters via packet X/26 is supported as well if they are carried inside the EP1 file. On the generated HTML pages, page numbers / references are automatically rendered as clickable hyperlinks. Similarly, "next" indicators such as ">>" and "->" are rendered as hyperlinks to the next subpage or page. Additionally, web links are rendered as hyperlinks as well.

To use ttxweb, all you have to do is install the PHP scripts including the additional files (CSS, JS etc.) on a web server and ensure that the teletext pages are synchronized as EP1 files in a configurable folder that can be accessed from the PHP script (e.g. via FTP).

## Live Demo

See the live demo at http://www.fabianswebworld.de/ttxweb.

## Screenshots

![Screenshot - Page 100](/demo_p100.png "Page 100")

![Screenshot - Test page](/demo_p896.png "Test page")

## Installation

### Preparation

#### System requirements

A web server with PHP >= 5.6 is required. There are no other requirements (mySQL etc.).

#### Input files

The teletext pages to be displayed must be in EP1 format (Softel). This file format can be processed or exported by all common teletext systems (Softel, Broadstream etc.). If required, an adaptation to other formats (TTI, TTX, ETT, etc.) is conceivable with little effort.

The files must be suitably synchronized in the filename format PxxxSyy.EP1 (where xxx = page number, yy = subpage number) to a folder accessible to the PHP script. The file name format can be adjusted in the script if required.

### Deployment

The PHP script and all auxiliary files must be copied to the web server and configured according to the section below. The **g1.zip** file in the **g1** folder needs to be unzipped on the server (faster deployment than transferring 1024 separate files). The transfer of the EP1 files must be set up.

## Configuration and customizations

The following configuration options are available to configure the behavior of ttxweb and adapt the output to your own website design:

- **scripts/ttxweb_main.php:**
   - const **TTXWEB_VERSION** // version string
   - const **EP1_PATH** // Path to the EP1 files (default: 'ep1/')
   - const **EP1_LANGUAGE** // Teletext language (default: 'en-US', possible values: 'de-DE' | 'en-GB' | 'en-US')
   - const **EP1_DECODE_X26** // Decode packet X/26 (level 1.5 characters) (default: true)
   - const **NO_PAGE_STRING** // String for 'Page not found' (default: empty)
   - const **ROW_0_CUSTOMHEADER** // Template for row 0 (page header). If not set or empty, row 0 from the EP1 file is displayed.
     - Format for **ROW_0_CUSTOMHEADER**:
       `<span>` elements can be used with the classes from **ttxweb_main.css** (fg*n*, bg*n*, dh etc.) to format colors etc. Furthermore, the following tokens will be replaced:
        - **%page%** - Current page number
        - **%sub%** - Current subpage
        - **%weekday%** - Current day of the week short
        - **%day%** - Current day (2 digits)
        - **%month%** - current month (2 digits)
        - **%year%** - Current year (2 digits)
        - **%hh%** - Current hour (2 digits)
        - **%mm%** - current minute (2 digits)
        - **%ss%** - current second (2 digits)
     - Other formats would have to be added to ttxweb.js.  
       Example:
      
       `const ROW_0_CUSTOMHEADER = '<span class="bg0 fg7"><span class="fg7"> %page%.%sub% </span><span class="fg6">ttxweb  </span><span class="fg7">%weekday% %day%.%month%.%year% </span><span class="fg6">%hh%:%mm%:%ss%</span></span >';`

- **scripts/ttxweb_nav.php:**
   - The "quick links" to the individual teletext pages can be adjusted directly in the HTML code or, if necessary, removed entirely.

- **templates/headersphp:**
   - HTML template which is output before the actual teletext output. The following variables can be used in this template (in the form `<?php echo $variable; ?>`):
     - **$pageNum:** Current page number
     - **$nextPageNum:** Next available page
     - **$prevPageNum:** Previous available page
     - **$subpageNum:** Current subpage
     - **$nextSubpageNum:** Next subpage
     - **$prevSubpageNum:** Previous subpage
     - **$numSubpages:** Number of subpages

- **templates/trailer.php:**
   - HTML template, which is output after the actual teletext output. The same variables apply as in **header.php**.

- **css/ttxweb_main.css:**
   - Customize styles of the navigation box if necessary. Avoid changing the main Teletext style classes, although you *can* carefully modify text sizes there.

## GET parameters at runtime

The following URL parameters are supported:

- **level15** - 0 (decode only level 1.0 characters) | 1 (also decode level 1.5 characters, **default**)
- **header** - 0 (Show locally generated header, **default**) | 1 (Show Row 0 from EP1 file)
- **page** - 100 (**default**) .. 899 - Page number to be displayed
- **sub** - 1 (**default**) .. 99 - Subpage to be displayed


# Contact the author

Questions, suggestions, requests please send via the contact form or the other available contact means at www.fabianswebworld.de. Please also note the license terms in the LICENSE.md file (available in German only).
