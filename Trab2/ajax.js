/*
 * Rnews, server-side feed aggregator.
 * http://rnews.sourceforge.net/
 *
 * Copyright: Adrian Rollet, Anthony Wood
 * License: GPL
 *
 */
var rnews = {
  status      : null,
  feeds       : [], // [feedid, refreshReq, refreshResp, timedOut]
  xmlobj      : [], // xmlhttprequest object cache
  refreshing  : 0,  // num actively refreshing
  maxParallel : 2,
  refTimeout  : 2000, // ms
  snip        : false,
  hideEmpty   : false,
  hideMarked  : false,
  blockWide   : false,
  currWidth   : 0,    // detect resize
  yelHiMap    : [ '#ffffff', '#fdffee', '#fbffdd', '#f9ffcc', '#f7ffbb', '#f5ffaa', '#f3ff99', '#f1ff88', '#efff77' ],  // W -> Y, fadergb.pl
  grnHiMap    : [ '#ffffff', '#f7fbf0', '#f0f7e1', '#e8f4d3', '#e1f0c5', '#daecb8', '#d3e9ab', '#cce59e', '#c6e292' ],
  widthMap    : [ '49%', '61%', '71%', '79%', '85%', '90%', '93%', '95.5%', '97%', '98%', '98.5%', '98.75%', '99%' ],
  doneFunc    : null
  //nullFunc    : function() { }
};
var nullFunc = function() { };

// @public
// opts: JSON obj w/ msg(s),async(b),max(i),timeout(i),snip(b),hideempty(b)
function rnewsInit (opts) {
  rnews.status = $('status');

  if (def(opts.msg))
    rnews.doneFunc = function() { fade(rnews.status,opts.msg,2); }
  if (def(opts.max))
    rnews.maxParallel = opts.max;
  if (def(opts.timeout))
    rnews.refTimeout = opts.timeout;
  if (def(opts.snip))
    rnews.snip = opts.snip;
  if (def(opts.hideEmpty))
    rnews.hideEmpty = opts.hideEmpty;

  if (getElementsByClass('feed',$('content'),'div').length == 0)
    rnews.blockWide = true;

  if (def(opts.async) && opts.async) {
    if (rnews.maxParallel <= 0)
      rnews.maxParallel = 1;
    refreshFeeds();
    if (rnews.maxParallel > 1)
      setTimeout(refreshFeeds, rnews.refTimeout / 4);
  } else {
    if (rnews.doneFunc)
      rnews.doneFunc();
    rnews.doneFunc = null;
  }
}

// @public
function initFeed(fid,req) {
  rnews.feeds.push({ id:fid, req:req, resp:false });
}

// @public
// find feed, remove all dts & msgs, add loading dt, empty more dt, issue refresh
function update(id) {
  var feed = $('feed'+id);
  var dl = $$(feed,'dl')[0];
  var ee = $$(dl,'dd');
  for (var i = ee.length-1; i >= 0; i--) { //live array
    removeAllImageSwaps(ee[i]);
    dl.removeChild(ee[i]);
  }
  ee = $$(dl,'dt');
  for (var i = ee.length-1; i >= 0; i--) {
    removeAllClicks(ee[i]);
    dl.removeChild(ee[i]);
  }
  ee = getElementsByClass ('error',feed.parentNode,'P');
  for (var i = ee.length-1; i >= 0; i--)
    ee[i].parentNode.removeChild(ee[i]);
  ee = getElementsByClass ('warn',feed.parentNode,'P');
  for (var i = ee.length-1; i >= 0; i--)
    ee[i].parentNode.removeChild(ee[i]);

  var e = make('dt');
  e.id = 'none'+id;
  setClass(e,'loading');
  e.innerHTML = '&mdash; Loading articles...';
  dl.appendChild(e);

  e = make('dt');
  e.id = 'more'+id;
  setClass(e,'more');
  e.innerHTML = '';
  dl.appendChild(e);

  fade (rnews.status, 'Updating feed', 2);
  sendRequest('ajax.php?op=update&id='+id, handleResponse);
}

// @public
function markFeed(id) {
  sendRequest('ajax.php', handleResponse, 'op=markfeed&id='+id);
  var feed = $('feed'+id);
  /* change class of items from new to seen */
  var dts = $$(feed,"dt");
  for (var j = 0; j < dts.length; j++) {
    var links = $$(dts[j],"a");
    for (var i = 0; i < links.length; i++)
      chgClass(links[i],'new','seen');
    if (hasClass(dts[j],'feedlink'))
      collapse(dts[j].id);
  }

  highlight ($$(feed,'dl')[0], rnews.grnHiMap);

  if (rnews.hideMarked) {
    fade (feed.parentNode, null, 0.75, function() {
        removeEmptyFeed(feed.parentNode);
      });
  }
}

// @public
// Args: feed num, link num
function markOlder(fid, lid) {
  sendRequest('ajax.php', handleResponse, 'op=markfeed&id='+fid+'&lid='+lid);
  var feed = $('feed'+fid);
  var dts = $$(feed,'dt');
  var found = false;
  for (var j = 0; j < dts.length; j++) {
    var dtsid = dts[j].id.substr(1);  // id = L1234
    if (lid == dtsid)
      found = true;
    if (found) {
      var links = $$(dts[j],"a");
      for (var i = 0; i < links.length; i++)
        chgClass(links[i],'new','seen');
      if (hasClass(dts[j],'feedlink') && lid != dtsid)  // dont collapse this one
        collapse(dts[j].id);
    }
  }
}

// @public
/* Mark the DT of the given id as visited. */
function markVis(id) {
  var theDT = $(id);
  var anc = $$(theDT,"a");
  for (var i = 0; i < anc.length; i++) {
    chgClass(anc[i],'(new|seen)','visited');
  }
  return true;
}
// @public
/* Mark the DT of the given id as seen|starred|deleted. */
function markLink(id,st) {
  sendRequest('ajax.php', handleResponse, 'op=marklink&st='+st+'&id='+id);
  var theDT = $(id);
  if (st == 'deleted') {
    collapse(id);
    removeAllClicks(theDT);
    theDT.parentNode.removeChild(theDT);
  }
  else {
    var anc = $$(theDT,"a");
    for (var i = 0; i < anc.length; i++)
      chgClass(anc[i],'(new|seen|visited|starred)',st);
  }
}

// @public
function fontChg(id,up,me) {
  var sib = me.parentNode.parentNode.parentNode.nextSibling;   // img->group->icons->itemBar->descr
  var sz = getStyle(sib,'fontSize') || getStyle(sib,'font-size');
  if (sz) {
    sz = parseInt(sz.substr(0,sz.length-2));
    sz += up ? 4 : -3;
    if (sz < 6) sz = 6;
    sib.style.fontSize = sz+'px';
  }
}

// @public
function expand(fid, id) {
  var theAnchor = $(id).firstChild;
  theAnchor.firstChild.nodeValue = "* ";
  sendRequest('ajax.php?op=expand&fid='+fid+'&id='+id, handleResponse);
}

// @public
/* Remove the first sibling of the DT with the given id.  Change the DT's
 * first anchor text and link to expand.
 */
function collapse(id) {
  var dt = $(id);
  var dd = dt.nextSibling;
  while (dd && dd.nodeType == "3") // skip text
    dd = dd.nextSibling;
  if (dd && dd.nodeName == 'DD') {
    removeAllImageSwaps(dd);
    removeAllClicks(dd);
    dt.parentNode.removeChild(dd);  // Gone!
  }
  var theAnchor = dt.firstChild;
  dt = dd = null;
  theAnchor.onclick = function() { expand('-1',id); return false; }
  theAnchor.title = "expand";
  theAnchor.firstChild.nodeValue = "+ ";
}

function sfunc(f,c) {
  return function() { setClass(f,c) };
}
// @public
function goWide(id) {
  var e = $('feed'+id);
  var fp = e.parentNode.parentNode;  // fid->feed->feedpair
  var buts = getElementsByClass('wide',fp,'div');
  for (var i=buts.length-1; i>=0; i--) {
    removeAllImageSwaps(buts[i]);
    buts[i].parentNode.removeChild(buts[i]);  // kill gowide links
  }

  var feeds = getElementsByClass('feed',fp,'div');
  for (i=0; i<feeds.length; i++) {
    var cf = make("div");
    setClass(cf,'feedpair clearfix');
    fp.parentNode.insertBefore(cf, fp);
    cf.appendChild(feeds[i]);
    stretch (feeds[i], 0, sfunc(feeds[i],'feedwide'), rnews.widthMap);
  }

  fp.parentNode.removeChild(fp);
}

// call on page load, after all feeds have been queued
function refreshFeeds() {
  if (rnews.refreshing >= rnews.maxParallel) return;
  var i;
  for (i=0; i<rnews.feeds.length; i++) {
    var f = rnews.feeds[i];
    if (f.req == false) {
      f.req = true;
      rnews.refreshing++;
      return refresh(f);
    }
  }
  if (rnews.refreshing == 0 && rnews.doneFunc) {
    rnews.doneFunc();
    rnews.doneFunc = null;
  }
}

function refresh(feed) {
//  var d = new Date();
//  log(d.getTime()+' requesting '+feed.id+' ('+rnews.refreshing+')');
  sendRequest('ajax.php?op=refresh&id='+feed.id, handleResponse);
  setTimeout (function() {
      if (feed.resp == false){ //ignore timeout if already refreshed
//log('timeout feed '+feed.id);
        refreshFeeds(); }
    }, rnews.refTimeout);
}

/* For op='expand', data='id|description HTML ...'.
 * Find the DT with given id, create a DD sibling containing the description.
 * Change the DT's first anchor text and link to collapse.
 */
function handleResponseExpand(parts, i) {
  var id = parts[i++];
  var theDD = make("dd");
  theDD.innerHTML = parts[i++];
  /* Add the new node after the DT node */
  var theDT = $(id);
  var theDD = theDT.parentNode.insertBefore(theDD, theDT.nextSibling);
  /* Change the DT's first anchor to '-' and change its link to collapse */
  var theAnchor = theDT.firstChild;
  theAnchor.onclick = function() { collapse(id); return false; }
  theAnchor.title = "hide";
  theAnchor.innerHTML = "&mdash; "; // firstChild.nodeValue="-- ";
  prepareImageSwap(theDD,true,true);  // rollovers
}

/* For op='more', data='feedid|morelink|linkid|title HTML|linkid|title HTML| ...'.
 * Append a new DT with each new link received.
 */
function handleResponseMore(parts,i) {
  var feedid = parts[i++];
  var theDT = $('none'+feedid);
  if (theDT) { removeAllClicks(theDT); theDT.parentNode.removeChild(theDT); }
  var moreDT = $('more'+feedid);
  var theDL = moreDT.parentNode;
  theDL.removeChild(moreDT);

  var morelink = parts[i++];

  var wasNone = false;
  while (i < parts.length) {
    var id = parts[i++];
    var link = parts[i++];

    var newDT = make("dt");
    newDT.id = id;
    if (rnews.snip)
      setClass(newDT,'feedlink ell');
    else
      setClass(newDT,'feedlink');
    newDT.innerHTML = link;
    theDL.appendChild(newDT);

    if (id.substring(0,4) == 'none') wasNone = true;
  }

  /* add the more link back at the end, if it exists */
  if (morelink) {
    moreDT.innerHTML = morelink;
    theDL.appendChild(moreDT);
    prepareImageSwap(moreDT,true,true);
  }

  return !wasNone;
}

// @public
function more(id, n) {
  sendRequest('ajax.php?op=more&id='+id+'&n='+n, handleResponse);
}

function showFeedMsg (feedid, cls, html) {
  var fc = $('feed'+feedid);
  var p = make('p');
  setClass(p,cls);
  p.innerHTML = html;
  fc.parentNode.insertBefore(p,fc);
}

// Handle: block, wide block; list does not use async; handles all cats
//
function removeEmptyFeed(rem) {
  var cat = rem.parentNode.parentNode;   // walk up to category
  var fp = getElementsByClass('feedpair', cat, 'div');
  if (fp.length > 0) {
    if (rnews.blockWide)
      removeEmptyFeedBlockWide(rem,fp);
    else
      removeEmptyFeedBlock(rem,fp);

    // Update the count of feeds not shown
    var cnt = $('numSkipped');
    if (cnt) {
      cnt.innerHTML = parseInt(cnt.innerHTML) + 1;
    } else {
      var pat = new RegExp("&amp;filter=.");
      var url = document.location.href.replace(pat,'') + '&amp;filter=A';
      var d = make('div');
      d.className = 'category';
      d.innerHTML = '<div class="clearfix"><div class="feedwide"><div class="feedcontent clearfix"><dl><dt class="seen">&mdash; Filter: not showing <span id="numSkipped">1</span> feeds, which have no new articles.  <a href="'+ url +'">View all feeds</a>.</dt></dl></div></div></div>';
      cat.parentNode.appendChild(d);
    }
  }
}

// Percolate feeds upward within pairs
//
function removeEmptyFeedBlock(rem,fp) {
  var found = false;
  for (var i=0; i<fp.length; i++) {
    if (!found) {
      if (fp[i] === rem.parentNode) {
        found = true;
        removeAllImageSwaps(rem);
        rem.parentNode.removeChild(rem);    // bye feed
      }
    }
    if (found) {
      if (i < fp.length - 1) {   // shift
        var e = firstDescendant(fp[i+1]);
        if (e) {
          e.parentNode.removeChild(e);
          if (hasClass(e,'feedwide'))
            chgClass(e,'feedwide','feed');
          fp[i].appendChild(e);
        }
      } else {
        var ch = getChildrenByTagName(fp[i], 'div');
        if (ch.length == 0)
          fp[i].parentNode.removeChild(fp[i]);  // bye empty feedwide
      }
    }
  }
}

// Just remove the feedpair holder
function removeEmptyFeedBlockWide(rem,fp) {
  var p = rem.parentNode;
  removeAllImageSwaps(p);
  p.removeChild(rem);          // bye feed
  p.parentNode.removeChild(p); // bye feedwide
}

// Data is 'linkid|itemBarHtml|msg'.  Update itemBar.
//
function handleResponseMarklink(parts,i) {
  var linkid = parts[i++];

  var theDT = $(linkid);
  if (theDT) {
    var e = theDT.nextSibling;  // get DD sibling
    while (e.nodeType == "3")
      e = theSibling.nextSibling;
    if (e.nodeName != 'DD') return;
    var oldDiv = getElementsByClass ('itemBar',e,'DIV');
    if (oldDiv && oldDiv.length > 0)
    {
      oldDiv = oldDiv[0];

      oldDiv.innerHTML = parts[i++];
      prepareImageSwap (oldDiv,true,true);

      fade (rnews.status, parts[i++], 1);
    }
  }
}

// Data is 'feedid|err|infolink|<as for more>'.  We refresh the next feed and
// highlight the added articles (if any).
//
function handleResponseRefresh(parts,i) {
  var feedid = parts[i++];

  var j;
  for (j=0; j<rnews.feeds.length; j++) {
    var f = rnews.feeds[j];
    if (f.id == feedid) {
      f.resp = true;
      break;
    }
  }
  rnews.refreshing--;
  refreshFeeds();

  var errstr = parts[i++];
  var warnstr = parts[i++];
  var infolink = parts[i++];

  if (errstr) showFeedMsg (feedid, 'error', errstr);
  if (warnstr) showFeedMsg (feedid, 'warn', warnstr);

  var info = $('info'+feedid);
  if (info) info.innerHTML = infolink;

  if (handleResponseMore(parts, i)) {
    var f = $('feed'+feedid);
    highlight ($$(f,'dl')[0], rnews.yelHiMap, 500);
  } else {
    if (!errstr && !warnstr && rnews.hideEmpty) {
      var f = $('feed'+feedid).parentNode;
      fade (f, null, 0.75, function() {
          removeEmptyFeed(f);
        });
    }
  }
}

/* Response is 'op|data'.  We switch on op and pass data to handlers.
 */
function handleResponse(req) {
  var parts = req.responseText.split('|');
  if (parts.length >= 1) {
    var op = parts[0];
    switch (op) {
      case 'expand':   handleResponseExpand(parts,1); break;
      case 'more':     handleResponseMore(parts,1); break;
      case 'refresh':  handleResponseRefresh(parts,1); break;
      case 'marklink': handleResponseMarklink(parts,1); break;
      case 'ack':      fade (rnews.status, parts[1], 1); break;
    }
  }
}

//--------------------------------------------------------------------------
// From quirksmode
//
function sendRequest(url,callback,postData) {
  var req = createXMLHTTPObject();
  if (!req) return;
  postData = def(postData) ? postData : null;
  var method = (postData) ? "POST" : "GET";
  req.open(method,url,true);
//  req.setRequestHeader('User-Agent','XMLHTTP/1.0');
  req.setRequestHeader('X-Requested-With','XMLHttpRequest');
  if (postData)
    req.setRequestHeader('Content-type','application/x-www-form-urlencoded');
  req.onreadystatechange = function () {
    if (req.readyState == 4 &&
        (req.status == 200 || req.status == 304)) {
      if (callback)
        callback(req);
      req.onreadystatechange = nullFunc;
      rnews.xmlobj.push(req);
    }
    return;
  }
  if (req.readyState == 4) return;
  req.send(postData);
}

var XMLHttpFactories = [
  function () {return new XMLHttpRequest()},
  function () {return new ActiveXObject("MSXML2.XMLHTTP.6.0")},
  function () {return new ActiveXObject("MSXML2.XMLHTTP.3.0")},
  function () {return new ActiveXObject("MSXML2.XMLHTTP")},
  function () {return new ActiveXObject("Microsoft.XMLHTTP")}
];

function createXMLHTTPObject() {
  var xmlhttp = false;
  if (xmlhttp = rnews.xmlobj.pop())
    return xmlhttp;
  for (var i=0;i<XMLHttpFactories.length;i++) {
    try { xmlhttp = XMLHttpFactories[i](); return xmlhttp; }
    catch (e) { }
  }
  return null;
}

