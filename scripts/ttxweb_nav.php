<?php

function renderNavigation($pageNum, $subpageNum, $prevPageNum, $nextPageNum, $prevSubpageNum, $nextSubpageNum, $numSubpages) {

    echo '
        <div id="nav">
          <div id="ttxNavigation">
            <div class="row">
              <div class="buttonarrowleft"><a href="?page='.$prevPageNum.'" class="arrowleft">&lt;</a></div>
              <div class="currpage">Seite<div class="pagenum"><form id="numPadForm" action="" method="get"><input class="inputtext" id="numPadInput" value="'.$pageNum.'" name="page" type="text" maxlength="3" onchange="checkNumPadInput();" onkeyup="checkNumPadInput();" title="Bitte dreistellige Seitennummer eingeben." />
              <noscript><div class="noScript"><input name="view" type="submit" value="Aufrufen" class="inputbutton"/></div></noscript></form></div></div>
              <div class="buttonarrowright"><a href="?page='.$nextPageNum.'" class="arrowright" title="zur n&auml;chsten Tafel - 160">&gt;</a></div>
              <div style="clear:both"></div>
            </div>
           <div class="row">
              <div class="buttonarrowleft"><a href="?page='.$pageNum.'&amp;sub='.$prevSubpageNum.'" class="arrowleft">&lt;&lt;</a></div>
              <div class="currpage">Unterseite<div class="pagenum">'.$subpageNum.' / ' . sprintf("%02d", $numSubpages).'</div></div>
              <div class="buttonarrowright"><a href="?page='.$pageNum.'&amp;sub='.$nextSubpageNum.'" class="arrowright">&gt;&gt;</a></div>
              <div style="clear:both"></div>
           </div>

           <div id="ttxNumPad">
            <div class="numPadRow">
              <div class="left"><a href="#" class="number" onclick="return numberButtonPressed(\'1\');">1</a></div><div class="middle"><a href="#" class="number" onclick="return numberButtonPressed(\'2\');">2</a></div><div class="right"><a href="#" class="number" onclick="return numberButtonPressed(\'3\');">3</a></div>
              <div style="clear:left"></div>
            </div>
            <div class="numPadRow">
              <div class="left"><a href="#" class="number" onclick="return numberButtonPressed(\'4\');">4</a></div><div class="middle"><a href="#" class="number" onclick="return numberButtonPressed(\'5\');">5</a></div><div class="right"><a href="#" class="number" onclick="return numberButtonPressed(\'6\');">6</a></div>
              <div style="clear:left"></div>
            </div>
            <div class="numPadRow">
              <div class="left"><a href="#" class="number" onclick="return numberButtonPressed(\'7\');">7</a></div><div class="middle"><a href="#" class="number" onclick="return numberButtonPressed(\'8\');">8</a></div><div class="right"><a href="#" class="number" onclick="return numberButtonPressed(\'9\');">9</a></div>
              <div style="clear:left"></div>
            </div>
            <div class="numPadRow">
              <div class="center"><a href="#" class="number" onclick="return numberButtonPressed(\'0\');">0</a></div>
              <div style="clear:left"></div>
            </div>
          </div>

          <div id="ttxQuickLinks">
             <ul>
                <li>&rtrif; <a href="?page=100">Seite 100</a></li>
                <li>&rtrif; <a href="?page=112">Nachrichten</a></li>
                <li>&rtrif; <a href="?page=170">Wetter</a></li>
                <li>&rtrif; <a href="?page=200">Sport</a></li>
             </ul>
           </div>
 
        </div>
       </div>';

}

?>
