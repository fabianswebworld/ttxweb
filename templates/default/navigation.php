
        <div id="nav">
          <div id="ttxNavigation">
            <div class="row">
              <div class="buttonarrowleft"><a href="?page=<?php echo $prevPageNum . $queryString; ?>" class="arrowleft" title="Vorherige Seite">&lt;</a></div>
              <div class="currpage">Seite<div class="pagenum"><form id="numPadForm" action="" method="get"><input class="inputtext" id="ttxNumPadInput" value="<?php echo $pageNum; ?>" name="page" type="text" maxlength="3" onchange="checkNumPadInput();" onkeyup="checkNumPadInput(event);" title="Bitte dreistellige Seitennummer eingeben." /><?php if (!empty($_GET['template'])) echo '<input id="ttxNumPadEnv" value="' . $templateName . '" name="template" type="hidden" />'; ?>
              <noscript><div class="noScript"><input name="view" type="submit" value="Los" class="inputbutton"/></div></noscript></form></div></div>
              <div class="buttonarrowright"><a href="?page=<?php echo $nextPageNum . $queryString; ?>" class="arrowright" title="N&auml;chste Seite">&gt;</a></div>
              <div style="clear:both"></div>
            </div>
           <div class="row">
              <div class="buttonarrowleft"><a href="?page=<?php echo $pageNum; ?>&amp;sub=<?php echo $prevSubpageNum . $queryString; ?>" class="arrowleft" title="Vorherige Unterseite">&lt;&lt;</a></div>
              <div class="currpage">Unterseite<div class="pagenum"><?php echo $subpageNum . ' / ' . sprintf('%02d', $numSubpages); ?></div></div>
              <div class="buttonarrowright"><a href="?page=<?php echo $pageNum; ?>&amp;sub=<?php echo $nextSubpageNum . $queryString; ?>" class="arrowright" title="N&auml;chste Unterseite">&gt;&gt;</a></div>
              <div style="clear:both"></div>
           </div>

           <div id="ttxNumPad">
            <div class="numPadRow">
              <div class="left"><a href="#" class="number" onclick="return numberButtonPressed('1');">1</a></div><div class="middle"><a href="#" class="number" onclick="return numberButtonPressed('2');">2</a></div><div class="right"><a href="#" class="number" onclick="return numberButtonPressed('3');">3</a></div>
              <div style="clear:left"></div>
            </div>
            <div class="numPadRow">
              <div class="left"><a href="#" class="number" onclick="return numberButtonPressed('4');">4</a></div><div class="middle"><a href="#" class="number" onclick="return numberButtonPressed('5');">5</a></div><div class="right"><a href="#" class="number" onclick="return numberButtonPressed('6');">6</a></div>
              <div style="clear:left"></div>
            </div>
            <div class="numPadRow">
              <div class="left"><a href="#" class="number" onclick="return numberButtonPressed('7');">7</a></div><div class="middle"><a href="#" class="number" onclick="return numberButtonPressed('8');">8</a></div><div class="right"><a href="#" class="number" onclick="return numberButtonPressed('9');">9</a></div>
              <div style="clear:left"></div>
            </div>
            <div class="numPadRow">
              <div class="left"><a href="?page=100<?php echo $queryString; ?>" class="number" title="Zur &Uuml;bersicht">&#8801;</a></div><div class="middle"><a href="#" class="number" onclick="return numberButtonPressed('0');">0</a></div><div class="right"><a href="?page=<?php echo $pageNum; ?>&amp;sub=<?php echo $subpageNum . preg_replace('/&amp;reveal=[0-9]/', '', $queryString); ?>&amp;reveal=1" class="number" onclick="return reveal();" title="Antwortfreigabe">?</a></div>
              <div style="clear:left"></div>
            </div>
          </div>

          <div id="ttxQuickLinks">
             <ul>
                <li>&rtrif; <a href="?page=100<?php echo $queryString; ?>">Seite 100</a></li>
                <li>&rtrif; <a href="?page=896<?php echo $queryString; ?>">Testseite</a></li>
                <li><strong>Demo functions:</strong></li>
                <li>&rtrif; <a href="?page=<?php echo $pageNum; ?>&amp;sub=<?php echo $subpageNum . preg_replace('/&amp;level15=[0-9]/', '', $queryString); ?>&amp;level15=0">Show fallback characters (Level 1.0)</a></li>
                <li>&rtrif; <a href="?page=<?php echo $pageNum; ?>&amp;sub=<?php echo $subpageNum . preg_replace('/&amp;level15=[0-9]/', '', $queryString); ?>&amp;level15=1">Show extended characters (Level 1.5)</a></li>
                <li>&rtrif; <a href="?page=<?php echo $pageNum; ?>&amp;sub=<?php echo $subpageNum . preg_replace('/&amp;header=[0-9]/', '', $queryString) ?>&amp;header=1">Show Row 0 from EP1 file</a></li>
                <li>&rtrif; <a href="?page=<?php echo $pageNum; ?>&amp;sub=<?php echo $subpageNum . preg_replace('/&amp;header=[0-9]/', '', $queryString) ?>&amp;header=0">Generate Row 0 locally</a></li>
                <li>&rtrif; <a href="?page=<?php echo $pageNum; ?>&amp;sub=<?php echo $subpageNum . preg_replace('/&amp;template=[\w]+\b/', '', $queryString) ?>&amp;template=fwwtext">Switch to website template</a></li>
             </ul>
           </div>
 
        </div>
       </div>

