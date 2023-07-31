function ttxInitialize() {
  if (document.getElementById('ttxRow0Header').innerHTML != 1) {
    renderRow0();
  }
  if (refreshState) {
    refreshTimeoutId = setTimeout(xhrRefresh, refreshTimer);
  }
  setNumPadFocus();
}

function xhrRefresh() {

  var myXhr = new XMLHttpRequest();
  var myXhrUrl = window.location.pathname + '?xhr=1&' + window.location.search.substring(1);

  var myCurrentContents = document.createElement('div');
  myCurrentContents.innerHTML = document.getElementById('ttxContainer').outerHTML.trim();
  myCurrentRow0 = myCurrentContents.querySelector('#row0');
  myCurrentRow0.parentNode.removeChild(myCurrentRow0);

  myXhr.open('GET', myXhrUrl);

  myXhr.onload = function() {
    if (myXhr.readyState == 4 && myXhr.status == 200) {
      if (new DOMParser().parseFromString(myXhr.responseText, 'text/html').querySelectorAll('.errorPage').length == 0) {
        var myNewContents = document.createElement('div');
        myNewContents.innerHTML = myXhr.responseText.trim();
        myNewRow0 = myNewContents.querySelector('#row0');
        myNewRow0.parentNode.removeChild(myNewRow0);
        if (myNewContents.innerHTML.trim() != myCurrentContents.innerHTML.trim()) {
          document.getElementById('ttxContainer').outerHTML = myXhr.responseText;
          console.log('ttxweb: Successfully updated teletext page.');
          if (document.getElementById('ttxRow0Header').innerHTML != 1) renderRow0();
        }
        else {
          console.log('ttxweb: Teletext page has not changed since last update, refresh deferred.');
        }
      }
      else {
        console.log('ttxweb: Teletext page has gone, refresh deferred.');
      }
    }
    else {
      console.log('ttxweb: Error fetching teletext page for XHR refresh.');
    }
  };

  myXhr.onerror = function() {
    console.log('ttxweb: XHR error');
  };

  console.log('ttxweb: Fetching teletext page for XHR refresh from: ' + myXhrUrl);

  myXhr.send();

  if (refreshState) {
    refreshTimeoutId = setTimeout(xhrRefresh, refreshTimer);
  }
}

function renderRow0() {
  var myDate = new Date();
  var myPageNum = document.getElementById('ttxPageNum').innerHTML;
  var mySubpageNum = document.getElementById('ttxSubpageNum').innerHTML;

  var myWeekDay = myDate.toLocaleString(document.getElementById('ttxLanguage').innerHTML, {weekday: 'long'}).substr(0, 2);
  var myMonth = zeroPad(myDate.getMonth() + 1);
  var myDay = zeroPad(myDate.getDate());
  var myYear = zeroPad(myDate.getFullYear().toString().substr(-2));
  var myHours = zeroPad(myDate.getHours());
  var myMinutes = zeroPad(myDate.getMinutes());
  var mySeconds = zeroPad(myDate.getSeconds());

  var myRow0 = document.getElementById('ttxRow0Template').innerHTML;
  myRow0 = myRow0.replace("%page%", myPageNum).replace("%sub%", mySubpageNum).replace("%weekday%", myWeekDay).replace("%month%", myMonth).replace("%day%", myDay).replace("%year%", myYear).replace("%hh%", myHours).replace("%mm%", myMinutes).replace("%ss%", mySeconds);

  document.getElementById('row0').innerHTML = myRow0;

  setTimeout(renderRow0, 1000);
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
  if (i < 10) {i = "0" + i};
  return i;
}

function numberButtonPressed(number) {
  var elem;
  var oldValue;

  if (document.getElementById("numPadInput")) {
    elem = document.getElementById("numPadInput");
  } else {
    elem = document.forms[0].page;
  }

  if (elem && elem.value) {
    oldValue = elem.value;
  }

  if (oldValue && oldValue.length < 1) {
    elem.value = number;
  } else
  if (oldValue && oldValue.length < 3) {
    elem.value = oldValue + "" + number;
  } else {
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

  if (document.getElementById("numPadInput")) {
    elem = document.getElementById("numPadInput");
  } else {
    elem = document.forms[0].page;
  }

  if (elem && elem.value) {
    oldValue = elem.value;
  }

  if (oldValue && oldValue.match(/[^0-9]/gi)) {
    alert("Bitte geben Sie in dieses Feld nur Zahlen ein.");
    elem.value = "";
  }

  if ((elem.value.length > 2) && !isNaN(event.key)) {
    gotoPage();
  }
}

function gotoPage() {
  if (document.getElementById("numPadForm")) {
    document.getElementById("numPadForm").submit();
  } else {
    document.forms[0].submit();
  }
}

function setNumPadFocus() {
  var elem;
  if (document.getElementById("numPadInput")) {
    elem = document.getElementById("numPadInput");
  } else {
    elem = document.forms[0].page;
  }
  elem.focus();
  elem.select();
}

var revealState = document.getElementById('ttxReveal').innerHTML;
var refreshTimer = document.getElementById('ttxRefresh').innerHTML * 1000;
var refreshState = (refreshTimer != 0);
var refreshTimeoutId;

ttxInitialize();
