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
  setTimeout(showNavHint, 50);
  setTimeout(hideNavHint, 3000);
});

document.body.addEventListener("click", function(e) {
  if(e.target && e.target.nodeName == "A") {
    if (e.target.getAttribute('target') != '_blank') {
      xhrLoadPageFromUrl(e.target.href + '&xhr=1', 'add', false, false);
      e.preventDefault();
      return false;
    }
  }
});

window.addEventListener('resize', (event) => {
  offsetCalculate();
});

function showNavHint() {
  var elem = document.getElementById('navhint');
  elem.style.transition = 'opacity 0.1s ease-in';
  elem.style.visibility = 'visible';
  elem.style.opacity = 1;
}

function hideNavHint() {
  var elem = document.getElementById('navhint');
  elem.style.transition = 'visibility 0s 2s, opacity 2s ease-out';
  elem.style.visibility = 'hidden';
  elem.style.opacity = 0;
}

function clearSelection() {
  var elem = document.getElementById('ttxNumPadInput');
  elem.selectionStart = elem.value.length;
  elem.selectionEnd = elem.value.length;
}
function hideInput() {
  var elem = document.getElementById('ttxNumPadInput');
  elem.style.backgroundColor = 'transparent';
  elem.style.color = 'transparent';
  var elem = document.getElementById('navhint');
  elem.style.display = 'none';
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

document.getElementById('navhint').addEventListener('click', (event) => {
  var elem = document.getElementById('navhint');
  elem.style.opacity = 0;
  elem.style.visibility = 'hidden';
  elem.style.display = 'none';
});
