// ttxweb client-side scripts
// version: 1.6.4.724 (2025-09-09)
// documentation: https://github.com/fabianswebworld/ttxweb

function ttxReadEnv(fromXhrRefresh) {

  if (!fromXhrRefresh) {
    turn = (document.getElementById('ttxTurn').innerHTML == 1);
    seqn0 = (document.getElementById('ttxSeqn0').innerHTML == 1);
    refreshTimer = document.getElementById('ttxRefresh').innerHTML * 1000;
    refreshState = (refreshTimer != 0);
  }

  revealState = document.getElementById('ttxReveal').innerHTML;

  pageNum = document.getElementById('ttxPageNum').innerHTML;
  prevPageNum = document.getElementById('ttxPrevPageNum').innerHTML;
  nextPageNum = document.getElementById('ttxNextPageNum').innerHTML;

  subpageNum = parseInt(document.getElementById('ttxSubpageNum').innerHTML, 10);
  numSubpages = parseInt(document.getElementById('ttxNumSubpages').innerHTML, 10);

  prevSubpageNum = subpageNum - 1;
  nextSubpageNum = subpageNum + 1;
  if (prevSubpageNum <= 0) prevSubpageNum = 0;
  if (nextSubpageNum > numSubpages) nextSubpageNum = 0;

  if (turn) {
    prevSubpageNum = numSubpages;
    nextSubpageNum = 1;
  }

  subpageIndicator = document.getElementById('subpagenum');
  if (subpageIndicator !== null) {
    if (turn) {
      subpageIndicator.innerHTML = 'auto';
    }
    else {
      subpageIndicator.innerHTML = zeroPad(subpageNum) + ' / ' + zeroPad(numSubpages);
    }
  }

  pageIndicator = document.getElementById('ttxNumPadInput');
  if ((pageIndicator !== null) && (!fromXhrRefresh)) {
    pageIndicator.value = pageNum;
  }

  prevPageButton = document.getElementById('prevPageButton');
  nextPageButton = document.getElementById('nextPageButton');
  prevSubpageButton = document.getElementById('prevSubpageButton');
  nextSubpageButton = document.getElementById('nextSubpageButton');

  var prevPageUrl, nextPageUrl, prevSubpageUrl, nextSubpageUrl;
  prevPageUrl = new URL(window.location.href);
  nextPageUrl = new URL(window.location.href);
  prevSubpageUrl = new URL(window.location.href);
  nextSubpageUrl = new URL(window.location.href);

  prevPageUrl.searchParams.set('page', prevPageNum);
  prevPageUrl.searchParams.delete('sub');
  nextPageUrl.searchParams.set('page', nextPageNum);
  nextPageUrl.searchParams.delete('sub');

  prevSubpageUrl.searchParams.delete('sub');
  if (prevSubpageNum > 0) prevSubpageUrl.searchParams.set('sub', prevSubpageNum);
  prevSubpageUrl.searchParams.set('page', pageNum);
  nextSubpageUrl.searchParams.set('sub', nextSubpageNum);
  nextSubpageUrl.searchParams.set('page', pageNum);

  if (prevPageButton != null) {
    prevPageButton.onclick = function() { xhrLoadPage(prevPageNum, 0, 'add', false, true); return false; };
    prevPageButton.href = prevPageUrl.toString();
  }

  if (nextPageButton != null) {
    nextPageButton.onclick = function() { xhrLoadPage(nextPageNum, 0, 'add', false, true); return false; };
    nextPageButton.href = nextPageUrl.toString();
  }

  if (prevSubpageButton != null) {
    prevSubpageButton.onclick = function() { xhrLoadPage(pageNum, prevSubpageNum, 'add', false, true); return false; };
    prevSubpageButton.href = prevSubpageUrl.toString();
  }

  if (nextSubpageButton != null) {
    nextSubpageButton.onclick = function() { xhrLoadPage(pageNum, nextSubpageNum, 'add', false, true); return false; };
    nextSubpageButton.href = nextSubpageUrl.toString();
  }

  if (document.getElementById('ttxRow0Header').innerHTML != 1) {
    renderRow0();
  }

}


function ttxInitialize() {

  ttxReadEnv();
  updateLocation(true, false);
  refreshPageTitle();

  if (refreshState) {
    refreshTimeoutId = setTimeout(xhrRefresh, refreshTimer);
  }

}


function xhrRefresh() {

  var myPage = pageNum;

  if (!turn) {
    var mySubpage = subpageNum;
  }
  else {
    var mySubpage = subpageNum + 1;
    if (mySubpage > numSubpages) mySubpage = 1;
  }

  xhrLoadPage(myPage, mySubpage, 'none', false, false);

}


function xhrLoadPageFromUrl(xhrUrl, historyMode, focusNumPad, rereadSeqn0) {

  var myXhr = new XMLHttpRequest();
  var myXhrUrl = new URL(xhrUrl);

  var subpageSet;
  subpageSet = myXhrUrl.searchParams.has('sub');

  var myCurrentContents = document.createElement('div');
  myCurrentContents.innerHTML = document.getElementById('ttxStage').outerHTML.trim();
  myCurrentRow0 = myCurrentContents.querySelector('#row0');
  myCurrentRow0.parentNode.removeChild(myCurrentRow0);

  myXhr.open('GET', xhrUrl);

  myXhr.onload = function() {
    if (myXhr.readyState == 4 && myXhr.status == 200) {
      if ((new DOMParser().parseFromString(myXhr.responseText, 'text/html').querySelectorAll('.errorPage').length == 0) || (historyMode == 'add') || (historyMode == 'replace')) {
        var myNewContents = document.createElement('div');
        myNewContents.innerHTML = myXhr.responseText.trim();
        myNewRow0 = myNewContents.querySelector('#row0');
        myNewRow0.parentNode.removeChild(myNewRow0);
        if (myNewContents.innerHTML.trim() != myCurrentContents.innerHTML.trim()) {

          document.getElementById('ttxStage').outerHTML = myXhr.responseText;

          console.log('ttxweb: Successfully updated teletext page.');
        }
        else {
          console.log('ttxweb: Teletext page has not changed since last update, refresh deferred.');
        }
      }
      else {
        console.log('ttxweb: Teletext page has gone, refresh deferred.');
      }

      ttxReadEnv(!(historyMode == 'add' || historyMode == 'replace' || focusNumPad || rereadSeqn0));

      if (historyMode == 'add' || historyMode == 'replace') {
        updateLocation((historyMode == 'replace'), subpageSet);
      }
      if (historyMode == 'pop' || focusNumPad || rereadSeqn0) {
        refreshPageTitle();
      }
      if (focusNumPad) {
        setNumPadFocus();
      }
      if (refreshState) {
        refreshTimeoutId = setTimeout(xhrRefresh, refreshTimer);
      }

    }
    else {
      console.log('ttxweb: Error fetching teletext page for XHR refresh.');
    }
  };

  myXhr.onerror = function() {
    console.log('ttxweb: XHR error');
  };

  console.log('ttxweb: Fetching teletext page for XHR refresh from: ' + xhrUrl);

  myXhr.send();

}


function xhrLoadPage(page, subpage, historyMode, focusNumPad, rereadSeqn0) {

  clearTimeout(refreshTimeoutId);

  var currentUrl = new URL(window.location.href);
  currentUrl.searchParams.set('xhr', 1);
  currentUrl.searchParams.set('page', page);
  currentUrl.searchParams.delete('sub');
  if (subpage > 0) currentUrl.searchParams.set('sub', subpage);
  var myXhrUrl = currentUrl.toString();          

  xhrLoadPageFromUrl(myXhrUrl, historyMode, focusNumPad, rereadSeqn0);

}


function renderRow0() {

  var myDate = new Date();

  var myWeekDay = myDate.toLocaleString(document.getElementById('ttxLanguage').innerHTML, {weekday: 'long'}).substring(0, 2);
  var myMonth = zeroPad(myDate.getMonth() + 1);
  var myDay = zeroPad(myDate.getDate());
  var myYear = myDate.getFullYear().toString().substring(2, 4);
  var myHours = zeroPad(myDate.getHours());
  var myMinutes = zeroPad(myDate.getMinutes());
  var mySeconds = zeroPad(myDate.getSeconds());

  var mySubpageNum = 0;

  if (!seqn0) mySubpageNum = subpageNum;

  var myRow0 = document.getElementById('ttxRow0Template').innerHTML;
  myRow0 = myRow0.replace("%page%", pageNum).replace("%sub%", zeroPad(mySubpageNum)).replace("%weekday%", myWeekDay).replace("%month%", myMonth).replace("%day%", myDay).replace("%year%", myYear).replace("%hh%", myHours).replace("%mm%", myMinutes).replace("%ss%", mySeconds);

  document.getElementById('row0').innerHTML = myRow0;

  setTimeout(renderRow0, 1000);

}


function refreshPageTitle() {

  pageTitle = document.getElementById('ttxPageTitle').innerHTML;
  if (pageTitle !== null) {
    document.title = pageTitle.replace("%page%", pageNum).replace("%sub%", zeroPad(subpageNum));
  }

}


function toggleRefresh() {

  refreshState = !refreshState;
  if (refreshState) {
    var refreshButton = document.querySelector('#refreshButton');
    refreshButton.classList.remove('active');
    refreshTimeoutId = setTimeout(xhrRefresh, refreshTimer);
  }
  else {
    var refreshButton = document.querySelector('#refreshButton');
    refreshButton.classList.add('active');
    clearTimeout(refreshTimeoutId);
  }
  return false;

}


function reveal() {

  if (revealState == 1) {
    revealState = 0;
    var revealButton = document.querySelector('#revealButton');
    revealButton.classList.remove('active');
  }
  else {
    revealState = 1;
    var revealButton = document.querySelector('#revealButton');
    revealButton.classList.add('active');
  }

  var concealedElements = document.querySelectorAll('.co');
  for(var i = 0; i < concealedElements.length; i++) {
    concealedElements[i].classList.replace('co', 're');
  }

  if (concealedElements.length > 0) return false;

  var revealedElements = document.querySelectorAll('.re');
  for(var i = 0; i < revealedElements.length; i++) {
    revealedElements[i].classList.replace('re', 'co');
  }
  return false;

}


function zeroPad(i) {

  if (i < 10) {i = '0' + i};
  return i;

}


function getNumPadInput() {

  var elem;
  if (document.getElementById('ttxNumPadInput')) {
    elem = document.getElementById('ttxNumPadInput');
  }
  else {
    elem = document.forms[0].page;
  }
  return elem;

}


function numberButtonPressed(number) {

  var elem;
  var oldValue;

  elem = getNumPadInput();

  if (elem && elem.value) {
    oldValue = elem.value;
  }

  if (oldValue && oldValue.length < 1) {
    elem.value = number;
  }
  else if (oldValue && oldValue.length < 3) {
    elem.value = oldValue + '' + number;
  }
  else {
    elem.value = number;
  }
  if (elem.value.length > 2) {
    gotoPage();
  }
  return false;

}


function checkNumPadInput(event) {

  var elem;
  var oldValue;

  if (event == undefined) {
    event = 'dummy';
    event.key = 'dummy';
  }

  elem = getNumPadInput();

  if (elem && elem.value) {
    oldValue = elem.value;
  }

  if (oldValue && oldValue.match(/[^0-9]/gi)) {
    alert('Bitte geben Sie in dieses Feld nur Zahlen ein.');
    elem.value = "";
  }

  if ((elem.value.length > 2) && !isNaN(event.key)) {
    gotoPage();
  }

}


function gotoPage() {

  if (document.getElementById('ttxNumPadInput')) {
    elem = document.getElementById('ttxNumPadInput');
    xhrLoadPage(elem.value, 0, 'add', true, true);
  }
  else {
    document.forms[0].submit();
  }

}


function setNumPadFocus() {

  var elem;

  elem = getNumPadInput();

  elem.focus();
  elem.select();

}


function updateLocation(replace, subpageSet) {

  var currentUrl = new URL(window.location.href);
  currentUrl.searchParams.set('page', pageNum);
  currentUrl.searchParams.delete('sub');
  if (subpageNum > 1 || subpageSet) currentUrl.searchParams.set('sub', subpageNum);
  var newUrl = currentUrl.toString();          
  if (replace) {
    window.history.replaceState({'ttxEnv':document.getElementById('ttxEnv').innerHTML,'pageTitle':document.title}, null, newUrl);
  }
  else {
    window.history.pushState({'ttxEnv':document.getElementById('ttxEnv').innerHTML,'pageTitle':document.title}, null, newUrl);
  }

}


String.prototype.trim = function() {
  return this.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}


var revealState, refreshTimer, refreshState, refreshTimeoutId, updateLocationTimeoutId;
var turn, seqn0, subpageIndicator, pageTitle, pageNum, subpageNum, numSubpages;
var prevPageNum, nextPageNum, prevSubpageNum, nextSubpageNum;
var prevPageButton, nextPageButton, prevSubpageButton, nextSubpageButton;

window.addEventListener('popstate', (event) => {
  if(event.state) {
    document.getElementById('ttxEnv').innerHTML = event.state.ttxEnv;
    ttxReadEnv(false);
    xhrLoadPage(pageNum, subpageNum, 'pop', false, false);
  }
});

document.addEventListener('keydown', (event) => {
  if (updateLocationTimeoutId != null) clearTimeout(updateLocationTimeoutId);
  const key = event.key;
  if (['ArrowLeft','ArrowRight','ArrowUp','ArrowDown','+','-','PageUp','PageDown'].includes(event.key) && !event.ctrlKey) {
    switch (event.key) {
      case 'ArrowLeft': case 'PageUp':
        if (event.target.matches('[id="ttxNumPadInput"]')) {
          if (!((event.target.selectionStart == event.target.selectionEnd) && (event.target.selectionStart == 0) && (event.target.value.length == 3))) {
            return;
          }
        }
        xhrLoadPage(pageNum, prevSubpageNum, 'none', false, true);
        break;
      case 'ArrowRight': case 'PageDown':
        if (event.target.matches('[id="ttxNumPadInput"]')) {
          if (!(event.target.selectionStart == 3)) {
            return;
          }
        }
        xhrLoadPage(pageNum, nextSubpageNum, 'none', false, true);
        break;
      case 'ArrowUp': case '+':
        xhrLoadPage(nextPageNum, 0, 'none', false, true);
        break;
      case 'ArrowDown': case '-':
        xhrLoadPage(prevPageNum, 0, 'none', false, true);
        break;
    }
    updateLocationTimeoutId = setTimeout(function() { updateLocation(false, false); }, 1000);
    event.preventDefault();
  }
  if (event.key >= 0 && event.key <= 9 && event.key != ' ') {
    if (event.target.matches('[id="ttxNumPadInput"]')) {
      var selLength;
      selLength = event.target.selectionEnd - event.target.selectionStart;
      if ((event.target.value.length == 3) && (!(selLength > 0 && selLength < 3)) && !isNaN(event.key)) {
        event.target.value = '';
        return false;
      }
    }
    else {
      setNumPadFocus();
    }
  }
  return false;
});

var elem;
elem = getNumPadInput();

elem.addEventListener("focus", (event) => {
  elem.select();
});

ttxInitialize();
