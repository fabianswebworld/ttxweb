<div id="nav"><form id="numPadForm" action="" method="get"><input class="inputtext" id="ttxNumPadInput" value="<?php echo $pageNum; ?>" name="page" type="text" maxlength="3" pattern="[0-9]*" onchange="checkNumPadInput(event);" onkeyup="checkNumPadInput(event);" title="Bitte dreistellige Seitennummer eingeben." /><?php if (!empty($_GET['template'])) echo '<input id="ttxNumPadEnv" value="' . $templateName . '" name="template" type="hidden" />'; ?><?php if (isset($streamName) && trim($streamName) != '') echo '<input type="hidden" name="stream" value="' . $streamName . '" />'; ?><noscript><div class="noScript"><input name="view" type="submit" value="Los" class="inputbutton"/></div></noscript></form></div>
<script type="text/javascript">
function getElementOffset(element) {
  var de = document.documentElement;
  var box = element.getBoundingClientRect();
  var top = (box.top + window.pageYOffset - de.clientTop) + 0.025 * box.height;
  var left = (box.left + window.pageXOffset - de.clientLeft) + 0.025 * box.width;
  var width = box.width;
  var height = box.height;
  return { top: top, left: left, width: width, height: height };
}
function offsetCalculate() {
  var parentOffset = getElementOffset(document.getElementById('row0'));
  var elem = document.getElementById('ttxNumPadInput');
  elem.style.top = parentOffset.top + 'px';
  elem.style.left = parentOffset.left + 'px';
  elem.style.height = parentOffset.height + 'px';
}
window.addEventListener('load', (event) => {
  offsetCalculate();
});
window.addEventListener('resize', (event) => {
  offsetCalculate();
});

function clearSelection() {
  var elem = document.getElementById('ttxNumPadInput');
  elem.selectionStart = elem.value.length;
  elem.selectionEnd = elem.value.length;
}
function hideInput() {
  var elem = document.getElementById('ttxNumPadInput');
  elem.style.backgroundColor = 'transparent';
  elem.style.color = 'transparent';
}
document.getElementById('ttxNumPadInput').addEventListener('input', (event) => {
  var hideTimer, clearSelectionTimer
  if (hideTimer != null) clearTimeout(hideTimer);
  if (clearSelectionTimer != null) clearTimeout(clearSelectionTimer);
  event.target.style.backgroundColor = '#000000';
  event.target.style.color = '#FFFFFF';
  if (event.target.value.length == 3) {
    clearSelectionTimer = setTimeout(clearSelection, 125);
    hideTimer = setTimeout(hideInput, 250);
  }
});
</script>