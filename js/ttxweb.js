function ttxInitialize() {
  if (document.getElementById('ttxRow0Header').innerHTML != 1) {
    renderRow0();
  }
  if (document.getElementById('ttxRefresh').innerHTML != 0) {
    setTimeout(xhrRefresh, document.getElementById('ttxRefresh').innerHTML * 1000);
  }
  setNumPadFocus();
}

function xhrRefresh() {

  var myXhr = new XMLHttpRequest();
  var myXhrUrl = window.location.pathname + '?page=' + document.getElementById('ttxPageNum').innerHTML + '&sub=' + document.getElementById('ttxSubpageNum').innerHTML + '&xhr=1&reveal=' + revealState;

  myXhr.open('GET', myXhrUrl);
  
  myXhr.onload = function () {
    if (myXhr.readyState == 4 && myXhr.status == 200) {
      if (new DOMParser().parseFromString(myXhr.responseText, 'text/html').querySelectorAll('.errorPage').length == 0) {
        console.log('ttxweb: Successfully updated teletext page.');
        document.getElementById('ttxContainer').outerHTML = myXhr.responseText;
        if (document.getElementById('ttxRow0Header').innerHTML != 1) renderRow0();
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

  setTimeout(xhrRefresh, document.getElementById('ttxRefresh').innerHTML * 1000);
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

function reveal() {

  if (revealState == 1) {
    revealState = 0;
  }
  else {
    revealState = 1;
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

ttxInitialize();

