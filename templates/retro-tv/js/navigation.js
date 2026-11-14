var hideTimer = null;
var userTappedInput = false;
var hintAutoHideTimer = null;
var touchRemoteHideTimer = null;
var isCoarsePointer = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
var remoteNavBusy = false;
var remoteNavResetTimer = null;

function unlockRemoteNav() {
  remoteNavBusy = false;
  if (remoteNavResetTimer !== null) {
    clearTimeout(remoteNavResetTimer);
    remoteNavResetTimer = null;
  }
}

function offsetCalculate() {
  var row0 = document.getElementById('row0');
  var elem = document.getElementById('ttxNumPadInput');
  if (!row0 || !elem) return;

  var rowBox = row0.getBoundingClientRect();
  var span = row0.querySelector('span');
  var textBox = span ? span.getBoundingClientRect() : rowBox;

  var charWidth = rowBox.width / 40;
  var de = document.documentElement;
  var top = textBox.top + window.pageYOffset - de.clientTop;
  var left = rowBox.left + window.pageXOffset - de.clientLeft + charWidth;

  elem.style.top = top + 'px';
  elem.style.left = left + 'px';
  elem.style.width = (charWidth * 3.1 * 2) + 'px';
  elem.style.height = (textBox.height * 2) + 'px';
  elem.style.lineHeight = (textBox.height * 2) + 'px';
}

function syncTouchRemote() {
  var pageEl = document.getElementById('ttxPageNum');
  var remotePageEl = document.getElementById('remotePageNum');
  if (pageEl && remotePageEl) {
    remotePageEl.textContent = pageEl.textContent.trim();
  }

  var subEl = document.getElementById('ttxSubpageNum');
  var numSubEl = document.getElementById('ttxNumSubpages');
  var sub = subEl ? parseInt(subEl.textContent, 10) : 1;
  var numSub = numSubEl ? parseInt(numSubEl.textContent, 10) : 1;

  var isTurn = (typeof turn !== 'undefined')
    ? Boolean(turn)
    : (document.getElementById('ttxTurn') && document.getElementById('ttxTurn').textContent.trim() === '1');
  var isSeqn0 = (typeof seqn0 !== 'undefined')
    ? Boolean(seqn0)
    : (document.getElementById('ttxSeqn0') && document.getElementById('ttxSeqn0').textContent.trim() === '1');

  var remoteSubEl = document.getElementById('remoteSubpageNum');
  if (remoteSubEl) {
    if (isTurn && isSeqn0) {
      remoteSubEl.textContent = 'auto';
    } else {
      var subStr = (sub < 10 ? '0' : '') + sub;
      var numSubStr = (numSub < 10 ? '0' : '') + numSub;
      remoteSubEl.textContent = subStr + '/' + numSubStr;
    }
  }

  var subButtons = document.querySelectorAll('#touchRemote .remote-sub');
  subButtons.forEach(function(btn) {
    btn.disabled = (numSub <= 1);
  });
}

function showTouchRemote() {
  if (!isCoarsePointer) return;
  var remote = document.getElementById('touchRemote');
  if (!remote) return;

  if (touchRemoteHideTimer !== null) clearTimeout(touchRemoteHideTimer);
  remote.classList.add('is-visible');
  touchRemoteHideTimer = setTimeout(hideTouchRemote, 5000);
}

function hideTouchRemote() {
  var remote = document.getElementById('touchRemote');
  if (!remote) return;
  if (touchRemoteHideTimer !== null) {
    clearTimeout(touchRemoteHideTimer);
    touchRemoteHideTimer = null;
  }
  remote.classList.remove('is-visible');
}

function showInput() {
  var elem = document.getElementById('ttxNumPadInput');
  if (elem) {
    elem.classList.add('is-active');
  }
}

function hideInput() {
  var elem = document.getElementById('ttxNumPadInput');
  if (elem) {
    elem.classList.remove('is-active');
    var pageEl = document.getElementById('ttxPageNum');
    if (pageEl) {
      elem.value = pageEl.textContent.trim();
    }
  }
}

function showNavHint() {
  if (isCoarsePointer) return;
  var hint = document.getElementById('navhint');
  if (!hint) return;
  hint.innerHTML = '<strong>Ziffern</strong>: Seitenzahl eingeben &nbsp;&middot;&nbsp; <strong>&#8593;/&#8595;</strong>: Seite blättern &nbsp;&middot;&nbsp; <strong>&#8592;/&#8594;</strong>: Unterseite blättern';

  hint.classList.add('is-visible');
  if (hintAutoHideTimer !== null) clearTimeout(hintAutoHideTimer);
  hintAutoHideTimer = setTimeout(hideNavHint, 4500);
}

function hideNavHint() {
  var hint = document.getElementById('navhint');
  if (!hint) return;
  hint.classList.remove('is-visible');
}

function pulseNumPadCue() {
  var elem = document.getElementById('ttxNumPadInput');
  if (!elem) return;
  elem.classList.add('numpad-pulse');
  setTimeout(function() {
    elem.classList.remove('numpad-pulse');
  }, 3200);
}

window.addEventListener('load', function() {
  offsetCalculate();
  syncTouchRemote();
  pulseNumPadCue();
  showTouchRemote();
  setTimeout(showNavHint, 200);

  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(offsetCalculate);
  }

  var stage = document.getElementById('ttxStage');
  if (stage && stage.parentNode && typeof MutationObserver !== 'undefined') {
    var observer = new MutationObserver(function() {
      unlockRemoteNav();
      offsetCalculate();
      syncTouchRemote();
    });
    observer.observe(stage.parentNode, { childList: true });
  }
});

window.addEventListener('resize', offsetCalculate);
window.addEventListener('orientationchange', function() {
  setTimeout(offsetCalculate, 150);
});
if (window.visualViewport) {
  window.visualViewport.addEventListener('resize', offsetCalculate);
  window.visualViewport.addEventListener('scroll', offsetCalculate);
}

document.addEventListener('click', function(e) {
  var link = e.target.closest ? e.target.closest('a') : (e.target.nodeName === 'A' ? e.target : null);
  if (link) {
    if (link.getAttribute('target') !== '_blank' && !link.closest('#touchRemote')) {
      xhrLoadPageFromUrl(link.href + '&xhr=1', 'add', false, false);
      e.preventDefault();
    }
    return;
  }

  if (isCoarsePointer) {
    if (e.target.closest && (e.target.closest('#ttxNumPadInput') || e.target.closest('#navhint'))) {
      return;
    }
    showTouchRemote();
  }
});

var numPadInput = document.getElementById('ttxNumPadInput');
if (numPadInput) {
  numPadInput.addEventListener('pointerdown', function() {
    userTappedInput = true;
  });

  numPadInput.addEventListener('focus', function() {
    offsetCalculate();
    if (userTappedInput) {
      userTappedInput = false;
      numPadInput.value = '';
      showInput();
      hideNavHint();
      if (isCoarsePointer) hideTouchRemote();
    }
  });

  numPadInput.addEventListener('blur', function() {
    userTappedInput = false;
    if (hideTimer !== null) clearTimeout(hideTimer);
    hideInput();
  });

  numPadInput.addEventListener('input', function(event) {
    if (hideTimer !== null) clearTimeout(hideTimer);

    var clean = event.target.value.replace(/[^0-9]/g, '');
    if (event.target.value !== clean) {
      event.target.value = clean;
    }

    if (clean.length > 0) {
      showInput();
      hideNavHint();
    }

    if (clean.length === 3) {
      if (isCoarsePointer && typeof xhrLoadPage === 'function') {
        xhrLoadPage(clean, 0, 'add', false, true);
        setTimeout(function() {
          numPadInput.blur();
          hideInput();
        }, 200);
      } else {
        gotoPage();
        hideTimer = setTimeout(hideInput, 250);
      }
    }
  });
}

var openNumPadBtn = document.getElementById('openNumPadButton');
if (openNumPadBtn && numPadInput) {
  openNumPadBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    userTappedInput = true;
    numPadInput.focus();
  });
}

var navHintEl = document.getElementById('navhint');
if (navHintEl) {
  navHintEl.addEventListener('click', function() {
    if (hintAutoHideTimer !== null) clearTimeout(hintAutoHideTimer);
    hideNavHint();
  });
}

var touchRemoteEl = document.getElementById('touchRemote');
if (touchRemoteEl) {
  touchRemoteEl.addEventListener('click', function(e) {
    var btn = e.target.closest ? e.target.closest('button') : null;
    if (!btn || btn.id === 'openNumPadButton') return;

    e.stopImmediatePropagation();
    e.preventDefault();

    if (btn.disabled || remoteNavBusy) return;

    remoteNavBusy = true;
    remoteNavResetTimer = setTimeout(unlockRemoteNav, 800);
    showTouchRemote();

    if (typeof updateLocationTimeoutId !== 'undefined' && updateLocationTimeoutId != null) {
      clearTimeout(updateLocationTimeoutId);
    }

    var isSubpageNav = false;
    if (btn.id === 'prevPageButton') {
      xhrLoadPage(prevPageNum, 0, 'none', false, true);
    } else if (btn.id === 'nextPageButton') {
      xhrLoadPage(nextPageNum, 0, 'none', false, true);
    } else if (btn.id === 'prevSubpageButton') {
      isSubpageNav = true;
      xhrLoadPage(pageNum, prevSubpageNum, 'none', false, true);
    } else if (btn.id === 'nextSubpageButton') {
      isSubpageNav = true;
      xhrLoadPage(pageNum, nextSubpageNum, 'none', false, true);
    }

    updateLocationTimeoutId = setTimeout(function() {
      updateLocation(false, isSubpageNav);
    }, 1000);
  }, true);
}
