<div id="nav">
  <form id="numPadForm" action="" method="get">
    <input class="inputtext" id="ttxNumPadInput" value="<?php echo $pageNum; ?>" name="page" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="3" autocomplete="off" autocorrect="off" spellcheck="false" enterkeyhint="go" aria-label="Dreistellige Seitennummer eingeben" title="Bitte dreistellige Seitennummer eingeben." />
    <?php if (!empty($_GET['template'])) echo '<input id="ttxNumPadEnv" value="' . $templateName . '" name="template" type="hidden" />'; ?>
    <?php if (isset($streamName) && trim($streamName) != '') echo '<input type="hidden" name="stream" value="' . $streamName . '" />'; ?>
    <noscript><div class="noScript"><input name="view" type="submit" value="Los" class="inputbutton" /></div></noscript>
  </form>
</div>

<div id="touchRemote" aria-label="Teletext-Seitenauswahl">
  <button type="button" id="prevPageButton" class="remote-btn" title="Vorherige Seite">&#9664;</button>
  <button type="button" id="prevSubpageButton" class="remote-btn remote-sub" title="Vorherige Unterseite">&#171;</button>
  <button type="button" id="openNumPadButton" class="remote-btn remote-page" title="Seitennummer eingeben"><span id="remotePageNum"><?php echo $pageNum; ?></span><span id="remoteSubpageNum"><?php echo ($seqn0 && $turn) ? 'auto' : sprintf('%02d/%02d', intval($subpageNum), intval($numSubpages)); ?></span></button>
  <button type="button" id="nextSubpageButton" class="remote-btn remote-sub" title="Nächste Unterseite">&#187;</button>
  <button type="button" id="nextPageButton" class="remote-btn" title="Nächste Seite">&#9654;</button>
</div>

<div id="navhint" role="status" aria-live="polite"></div>

<script type="text/javascript" src="<?php echo $templateFolder; ?>/js/navigation.js" defer></script>
