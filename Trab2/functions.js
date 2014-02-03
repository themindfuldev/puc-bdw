/* Utility functions */

function $(id) {
  return typeof(id)=='string' ? document.getElementById(id) : id;
}
function $$(n,t) {
  if (typeof(n)=='string')
    n = $(n);
  return n.getElementsByTagName(t);
}

function def(a) { return typeof(a)!='undefined'; }
function make(t) { return document.createElement(t); }

function trim(s) {
  return s.replace(/^\s+|\s+$/g,'');
}

function removeAllClicks(e) {
  var i,ee = e.getElementsByTagName('*');
  for (i = 0; i < ee.length; i++)
    ee[i].onclick = null;
}

function getStyle (e,s) {
  if (e.currentStyle)
    return e.currentStyle[s]; //ie
  else if (window.getComputedStyle)
    return document.defaultView.getComputedStyle(e,null).getPropertyValue(s); //w3c
}

// Based on code from http://www.matts411.com/webdev/width_and_height_getter_functions_for_html_elements
function getWidth(el) {
  var w = 0;
  if (document.defaultView && window.getComputedStyle) { // FF, Safari, Opera and others
    var style = document.defaultView.getComputedStyle(el, null);
    if (style.getPropertyValue("display") === "inline") {
      w = el.offsetWidth;
    } else if (style.getPropertyValue("display") !== "none") {
      w = parseInt(style.getPropertyValue("width"));
      // Opera 9.25 includes the padding and border when reporting the width/height
      if (!window.opera || document.getElementsByClassName) {
        w += parseInt(style.getPropertyValue("padding-left"));
        w += parseInt(style.getPropertyValue("padding-right"));
        w += parseInt(style.getPropertyValue("border-left-width"));
        w += parseInt(style.getPropertyValue("border-right-width"));
      }
    }
  } else if (el.currentStyle) { // IE and others
    if (el.currentStyle["display"] !== "none")
      w = el.offsetWidth; // Currently the width including padding + border
  } else {
    w = el.offsetWidth; // ?
  }
  return w;
}

function getX(e) {
  var x = 0;
  if (e.offsetParent) {
    do {
      x += e.offsetLeft;
    } while (e = e.offsetParent);
  }
  return x;
}

/* Class manipulation */

function hasClass (e,c) {
  var pat = new RegExp("(^|\\s)"+c+"(\\s|$)");
  var rc = pat.test(e.className);
  return rc;
}
function setClass (e,c) {
  e.className = c;
}
function chgClass (e,cOld,cNew) {
  var pat = new RegExp("(^|\\s)"+cOld+"(\\s|$)");
  e.className = e.className.replace(pat,' '+cNew+' ');
}

/* DOM searching */

// Args: classname[, start node, tag filter]
function getElementsByClass (c,node,tag) {
  var ee = [];
  if ( node == null ) node = document;
  var els = $$(node,tag?tag:'*');
  var elsLen = els.length;
  var pat = new RegExp("(^|\\s)"+c+"(\\s|$)");
  for (i = 0; i < elsLen; i++)
    if (pat.test(els[i].className)) ee.push(els[i]);
  return ee;
}

function getChildrenByTagName (node, tag) {
  var out = [];
  if (node) {
    var c = node.childNodes;
    tag = tag.toUpperCase();
    for (var i = 0; i < c.length; i++)
      if (c[i].nodeName.toUpperCase() == tag)
        out.push(c[i]);
  }
  return out;
}

function firstDescendant(e) {
  e = e.firstChild;
  while (e && e.nodeType != 1) e = e.nextSibling; // skip text
  return e;
}
function nextSiblingNode(e) {
  e = e.nextSibling
  while (e && e.nodeType != 1) e = e.nextSibling; // skip text
  return e;
}

// Fade: from full opacity to none
//   Args:  element, [message], delay(s), [callback]
//
function fade (e, msg, delay, cb) {
  if (typeof msg == 'string')
    e.innerHTML = msg;
  setOpacity (e, 10);
  if (delay > 0)
    setTimeout (function() { fadeStep(e, 10, cb) }, delay*1000);
  else
    fadeStep(e, 10, cb);
}
function fadeStep(e, s, cb) {
  if (s > 0) {
    setOpacity (e, s-1);
    setTimeout (function() { fadeStep(e,s-1,cb) }, 90);
  } else {
    if (cb)
      cb();
    //e.innerHTML = '';
    //setOpacity (e, 10);
  }
}
function setOpacity(e, value) {  // value=0-10
  if (!e.currentStyle || !e.currentStyle.hasLayout) e.style.zoom = 1;
  if (value == 0) {
    e.style.visibility = 'hidden';
  } else {
    e.style.visibility = 'visible';
    e.style.opacity = value/10.01;  // moz/saf, stop flicker keep < 1
    if (value == 10)
      e.style.filter = ''; //ie
    else
      e.style.filter = 'alpha(opacity=' + value*10 + ')'; //ie
  }
}

// Highlight: from highlighted to white background
// Args: element, colorMap, [startDelay]
//
function highlight (e, hmap, delay) {
  var num = hmap.length - 1;
  e.style.backgroundColor = hmap[num];
  var fn = function() { highlightStep(e, num-1, hmap) };
  if (delay != null && delay > 0)
    setTimeout (fn, delay);
  else
    fn();
}
function highlightStep (e, num, hmap) {
  e.style.backgroundColor = hmap[num];
  if (num > 0)
    setTimeout (function() { highlightStep(e, num-1, hmap) }, 1000/hmap.length);
}

function stretch (e, n, fn, wmap) {
  e.style.width = wmap[n];
  if (++n < wmap.length)
    setTimeout (function() { stretch(e,n,fn,wmap) }, 900/wmap.length);
  else
    fn();
}

function popHelp(url) {
  var w = window.open (url, "helpWin", 'dependent=1,toolbar=0,scrollbars=1,location=0,statusbar=0,menubar=0,resizable=1,width=400,height=400');
  if (w) w.focus();
  return w;
}

/*
function log (message) {
  if (!log.window_ || log.window_.closed) {
    var win = window.open("", null, "width=400,height=200," +
        "scrollbars=yes,resizable=yes,status=no," +
        "location=no,menubar=no,toolbar=no");
    if (!win) return;
    var doc = win.document;
    doc.write("<html><head><title>Debug Log</title></head>" +
        "<body></body></html>");
    doc.close();
    log.window_ = win;
  }
  var logLine = log.window_.document.createElement("div");
  logLine.appendChild(log.window_.document.createTextNode(message));
  log.window_.document.body.appendChild(logLine);
}
*/


