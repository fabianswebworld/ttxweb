function ttxInitialize() {
  if ((document.getElementById('ttxRow0Header').innerHTML != 1) && (document.getElementById('ttxRow0Template').innerHTML != '')) {
    renderRow0();
  }
  setNumPadFocus();
}

function renderRow0() {
  var myDate = new Date();
  var myPageNum = document.getElementById('ttxPageNum').innerHTML;
  var mySubpageNum = document.getElementById('ttxSubpageNum').innerHTML;

  var myWeekDayDe = myDate.toLocaleString(document.getElementById('ttxLanguage').innerHTML, {weekday: 'long'}).substr(0, 2);
  var myMonth = zeroPad(myDate.getMonth() + 1);
  var myDay = zeroPad(myDate.getDate());
  var myYear = zeroPad(myDate.getFullYear().toString().substr(-2));
  var myHours = zeroPad(myDate.getHours());
  var myMinutes = zeroPad(myDate.getMinutes());
  var mySeconds = zeroPad(myDate.getSeconds());

  var myRow0 = document.getElementById('ttxRow0Template').innerHTML;
  myRow0 = myRow0.replace("%page%", myPageNum).replace("%sub%", mySubpageNum).replace("%weekday%", myWeekDayDe).replace("%month%", myMonth).replace("%day%", myDay).replace("%year%", myYear).replace("%hh%", myHours).replace("%mm%", myMinutes).replace("%ss%", mySeconds);

  document.getElementById('row0').innerHTML = myRow0;

  setTimeout(renderRow0, 1000);
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

function checkNumPadInput() {
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

  if (elem.value.length > 2) {
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

ttxInitialize();

