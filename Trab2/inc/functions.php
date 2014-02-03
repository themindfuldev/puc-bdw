<?php

// This runs NOW (on inclusion) if enabled
if (defined('PROFILING_ENABLED') && PROFILING_ENABLED) {
  list($usec, $sec) = explode(' ', microtime());
  $startTime = ((float)$usec + (float)$sec);
}

// -------------------------------------------------------------------------------
// Valid arguments:
//  seen:  what to mark as seen
//  category:  select category
//  more:  feed to show more of
//  q, contents:  for searching
//
function argsToUrl ($args) {
  $url = '';
  $first = TRUE;
  foreach ($args as $k => $v) {
    $url .= ($first ? '?' : '&amp;') . "$k=" . urlencode($v);
    $first = FALSE;
  }
  return $url;
}

// -------------------------------------------------------------------------------
function argsToForm ($id, $args) {
  $s = '<form id="'.$id.'" action="'.$_SERVER['PHP_SELF'].'" method="post" class="hidden">';
  foreach ($args as $k => $v)
    $s .= '<input type="hidden" name="'.$k.'" value="'.$v.'" />';
  $s .= '</form>';

  return $s;
}

// -------------------------------------------------------------------------------
// Mark all links in an entire category as read.  Joins links and feeds tables.
//
function markCat (&$db, $userid, $catid) {
  $linkTable = DB_PREFIX.$userid.'_links';
  $feedTable = DB_PREFIX.$userid.'_feeds';

  $sql = "UPDATE $linkTable as L, $feedTable as F SET L.state='". feedlink::STATE_SEEN()
    . "' WHERE L.feed_id=F.id AND F.cat_id='$catid' AND L.state='"
    . feedlink::STATE_NEW() ."'";

  return $db->query($sql);
}

// -------------------------------------------------------------------------------
function forceUpdate (&$db, $userid, &$feed) {
  if (MAGPIE_CACHE_ON)
    purge_rss($feed->rss_link);   // clear out cache
  $feed->last_update = '0000-00-00 00:00:00';
  return $feed->update ($db, $userid);
}

// -------------------------------------------------------------------------------
function feedlinkHTML ($feedid, &$link, $closed, $newWindow, $snip, $prefix = NULL) {
  $s = '<a href="#" onclick="';
  if ($closed)
    $s .= "expand('{$feedid}','L{$link->id}'); return false;\" title=\"expand\"";
  else
    $s .= "collapse('L{$link->id}'); return false;\" title=\"hide\"";
  $s .= ' class="'. feedlink::stateStr($link->state) .'">'. ($closed?'+':'&mdash;')
    . ' </a><a href="redirect.php?artid='. $link->id
    . '&amp;link='. urlencode($link->link) .'" class="'
    . feedlink::stateStr($link->state) .'"' //.' onclick="markVis(\'L'. $link->id .'\')"'
    . ' onmouseup="markVis(\'L'. $link->id .'\')"'
    . ($newWindow ? ' target="_blank"' : '')
    . ' title="'. str_replace('"','&quot;',$link->title) .'">'
    . ($prefix ? (htmlspecialchars($prefix, ENT_NOQUOTES, 'UTF-8').':&nbsp; ') : ''). $link->title .'</a>';
  if ($snip) {
    //$d = preg_replace('/ +/',' ', strip_tags_xss(XSS_TAGS_NONE, $link->description));
    $d = strip_tags($link->description);
    $dlen = mb_strlen($d);
    if ($dlen > 0) {
      $s .= '<span class="snip">&mdash;'. mb_substr($d,0,$snip)
        . ($dlen > $snip ? '...' : '') .'</span>';
    }
  }
  return $s;
}

// -------------------------------------------------------------------------------
function morelinkHTML ($feedid, $n, $seen) {
  return '<a href="#" onclick="more(\''. $feedid .'\',\''. $n
    .'\');return false" class="'. ($seen ? 'seen' : 'new')
    .'" title="show older articles"><img src="img/more'
    .($seen ? '_nm' : '_hv').'.png" alt="M" title="show older articles"/> show more ...</a>';
}

// -------------------------------------------------------------------------------
function updatePrefs (&$user) {
  $updateCookie = FALSE;
  if (isset($_REQUEST['view']) &&
    ($_REQUEST['view'] == 'B' || $_REQUEST['view'] == 'W' || $_REQUEST['view'] == 'L'))
  {
    if (!isset($user->prefs['V']) || $user->prefs['V'] != $_REQUEST['view']) {
      $user->prefs['V'] = $_REQUEST['view'];
      if (!isset($_REQUEST['nopref']))
        $updateCookie = TRUE;
    }
  }

  if (isset($_REQUEST['sort']) && ($_REQUEST['sort'] == 'S' || $_REQUEST['sort'] == 'N'))
  {
    if (!isset($user->prefs['S']) || $user->prefs['S'] != $_REQUEST['sort']) {
      $user->prefs['S'] = $_REQUEST['sort'];
      if (!isset($_REQUEST['nopref']))
        $updateCookie = TRUE;
    }
  }

  if (isset($_REQUEST['filter']) && ($_REQUEST['filter'] == 'A' || $_REQUEST['filter'] == 'N'))
  {
    if (!isset($user->prefs['F']) || $user->prefs['F'] != $_REQUEST['filter']) {
      $user->prefs['F'] = $_REQUEST['filter'];
      if (!isset($_REQUEST['nopref']))
        $updateCookie = TRUE;
    }
  }

  if (isset($_REQUEST['async']))
  {
    if (!isset($user->prefs['A']) || $user->prefs['A'] != intval($_REQUEST['async'])) {
      $user->prefs['A'] = intval($_REQUEST['async']);
      if (!isset($_REQUEST['nopref']))
        $updateCookie = TRUE;
    }
  }

  return $updateCookie;
}

// -------------------------------------------------------------------------------
function viewlinkHTML ($prefs, $args) {
  $s = '<span class="group">view:';

  if (!isset($prefs['V']) || $prefs['V']=='B')
    $s .= '<img src="img/b1-block.png" title="block view" alt="[B]" />';
  else
  {
    $args['view'] = 'B';
    $s .= '<a href="'. $_SERVER['PHP_SELF'] . argsToUrl($args)
      .'" title="go to block view"><img src="img/b1-block_nm.png" alt="[B!]"/></a>';
  }

  if (isset($prefs['V']) && $prefs['V']=='W')
    $s .= '<img src="img/b1-blockw.png" title="wide block view" alt="[W]" />';
  else
  {
    $args['view'] = 'W';
    $s .= '<a href="'. $_SERVER['PHP_SELF'] . argsToUrl($args)
      .'" title="go to wide block view"><img src="img/b1-blockw_nm.png" alt="[W!]"/></a>';
  }

  if (isset($prefs['V']) && $prefs['V']=='L')
    $s .= '<img src="img/b1-list.png" title="list view" alt="[L]" />';
  else
  {
    $args['view'] = 'L';
    $s .= '<a href="'. $_SERVER['PHP_SELF'] . argsToUrl($args)
      .'" title="go to list view"><img src="img/b1-list_nm.png" alt="[L!]"/></a>';
  }

  return $s . '</span>';
}

// -------------------------------------------------------------------------------
// Sort by: score (default, if enabled), name
//
function sortlinkHTML ($stats, $prefs, $args) {
  if ($stats)
  {
    $s = '<span class="group">sort:';

    if ($stats && (!isset($prefs['S']) || $prefs['S']=='S'))
    {
      $s .= '<img src="img/b1-sscore.png" title="sorted by score" alt="[S]" />';
    }
    else
    {
      $args['sort'] = 'S';
      $s .= '<a href="'. $_SERVER['PHP_SELF'] . argsToUrl($args)
        .'" title="sort by score"><img src="img/b1-sscore_nm.png" alt="[S!]"/></a>';
    }

    if (isset($prefs['S']) && $prefs['S']=='N')
    {
      $s .= '<img src="img/b1-sname.png" title="sorted by name" alt="[N]" />';
    }
    else
    {
      $args['sort'] = 'N';
      $s .= '<a href="'. $_SERVER['PHP_SELF'] . argsToUrl($args)
        .'" title="sort by name"><img src="img/b1-sname_nm.png" alt="[N!]"/></a>';
    }

    return $s . '</span>';
  }
  else
  {
    return '';
  }
}

// -------------------------------------------------------------------------------
// Feed filter: none (default), new
//
function filterlinkHTML ($prefs, $args) {
  $s = '<span class="group">filter:';

  if (!isset($prefs['F']) || $prefs['F']=='A')
  {
    $s .= '<img src="img/b1-filt-all.png" title="showing all feeds" alt="[A]" />';
  }
  else
  {
    $args['filter'] = 'A';
    $s .= '<a href="'. $_SERVER['PHP_SELF'] . argsToUrl($args)
      .'" title="show all feeds"><img src="img/b1-filt-all_nm.png" alt="[A!]"/></a>';
  }

  if (isset($prefs['F']) && $prefs['F']=='N')
  {
    $s .= '<img src="img/b1-filt-new.png" title="showing only feeds with new articles" alt="[N]" />';
  }
  else
  {
    $args['filter'] = 'N';
    $s .= '<a href="'. $_SERVER['PHP_SELF'] . argsToUrl($args)
      .'" title="show only feeds with new articles"><img src="img/b1-filt-new_nm.png" alt="[N!]"/></a>';
  }

  //$s .= '<img src="img/b1-live_nm.png" alt="[L!]" />';
  return $s . '</span>';
}

// -------------------------------------------------------------------------------
function helplink ($key, $text = '&lt;help&gt;') {
  $link = otherUrl("help.html#$key");
  return "<span><a class=\"help\" href=\"$link\" onclick=\"javascript:popHelp('$link');return false\">$text</a></span>";
}

// -------------------------------------------------------------------------------
// Return FQ URL to other file in same directory as the current one.
// Note: this breaks completely if you use URLs like:  /foo/a.php/bar
//
function otherUrl ($file, $secure = FALSE) {
  return 'http'. ($secure ? 's' : '') .'://'. strip_tags($_SERVER['SERVER_NAME']) .
    rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/$file";
} 

// -------------------------------------------------------------------------------
function defaultUrl ($user = NULL) {
  return otherUrl('index.php' .
    ($user && $user->default_cat ? '?category='.$user->default_cat : ''));
}

// -------------------------------------------------------------------------------
// JS is from bookmarklet.php.  This works for both FF and IE.
function bookmarkletUrl ($cat = NULL) {
  $url = otherUrl('book.php'. ($cat ? "?category=$cat->id" : ''));
  $js = 'javascript:e%3Ddocument.createElement%28%27script%27%29%3Be.setAttribute%28%27language%27%2C%27javascript%27%29%3Be.setAttribute%28%27src%27%2C%27XYZZY%27%29%3Bvoid%28document.body.appendChild%28e%29%29%3B';
  return str_replace('XYZZY',$url,$js);
}
function rssRegisterUrl() {
  return htmlspecialchars("javascript:window.navigator.registerContentHandler('application/vnd.mozilla.maybe.feed', '". otherUrl('add.php?new=1&url=%s') ."', 'Rnews')");
}

// -------------------------------------------------------------------------------
function feedInfoHTML (&$feed, $stats) {
  $checked = date(DATE_FORMAT, strtotime($feed->last_update));
  $added = (strtotime($feed->last_add) > 0)
    ? date(DATE_FORMAT, strtotime($feed->last_add)) : 'never';
  $str = "As of $checked. Last new $added.";
  if ($stats)
    $str .= '&nbsp; <a href="prefs.php?viewstats=1" title="'
      . "expands:{$feed->stat_expand} clicks:{$feed->stat_click} "
      . "articles:{$feed->stat_total}\">Score: ". $feed->getScore() .'</a>';
  return $str;
}

// -------------------------------------------------------------------------------
// Takes list of next articles, max+1 to show, and whether to show only new ones
//
function filterArtGroup ($links, $n, $onlyNew)
{
  $nunread = count($links);
  if ($onlyNew) {
    while ($nunread > 0) {
      $link = $links[$nunread - 1];
      if ($link->state == feedlink::STATE_NEW() || $link->state == feedlink::STATE_STARRED())
        break;
      $nunread--;
    }
  }
  $ntoshow = min($nunread, $n);

  // More articles if: hit sql LIMIT, or not showing all that were returned
  //
  $moreexist = (count($links) > $n || $ntoshow < count($links));

  // State of "more" link is based on next article's state.
  //
  $moreseen = $moreexist && ($links[$ntoshow]->state != feedlink::STATE_NEW());

  return array ($ntoshow, $moreexist, $moreseen);
}

// -------------------------------------------------------------------------------
// Return ( |L<id>|<feedlink> ..., how many links, whether there is another row,
//   whether next item is seen )
//
function ajaxLinkStr (&$db, &$user, $feedid, $start, $n, $onlyNew)
{
  $links = feedlink::all ($db, $user->userid, "WHERE (feed_id='$feedid' AND state<>'".feedlink::STATE_DELETED()."') ORDER BY pubdate DESC, id DESC LIMIT $start,".($n+1));

  list ($ntoshow, $moreexist, $moreseen) = filterArtGroup ($links, $n, $onlyNew);

  $linkStr = '';
  for ($i = 0; $i < $ntoshow; $i++) {
    $linkStr .= "|L{$links[$i]->id}|"
      . str_replace ('|', '&#124;',
        feedlinkHTML ($feedid, $links[$i], TRUE, $user->new_window, $user->snippets ? SNIPPET_SHORT : 0));
  }

  return array ($linkStr, $ntoshow, $moreexist, $moreseen);
}

// -------------------------------------------------------------------------------
function ajaxExpandHTML (&$user, &$link)
{
  $s = '<div class="itemBar clearfix">'. ajaxItemBarHTML($user, $link) .'</div><div class="feeddescr">';
  if (strlen($link->description) > 0)
    $s .= $link->description;
  else
    $s .= 'No more information available.';
  return $s . '</div>';
}


function ajaxItemBarHTML (&$user, &$link)
{
  $tb = '<div class="icons">';

  $tb .= '<span class="group">';
  if ($link->state == feedlink::STATE_STARRED())
    $tb .= '<img src="img/ib-unstar_nm.png" title="unmark favorite" alt="[*]" onclick="markLink(\'L'.$link->id.'\',\'seen\')" /> ';
  else
    $tb .= '<img src="img/ib-star_nm.png" title="mark favorite" alt="[*]" onclick="markLink(\'L'.$link->id.'\',\'starred\')" /> ';

  if ($link->state == feedlink::STATE_NEW())
    $tb .= '<img src="img/ib-mark_nm.png" title="mark as read" alt="[-]" onclick="markLink(\'L'.$link->id.'\',\'seen\')" /></a> ';
  else
    $tb .= '<img src="img/ib-marked_nm.png" title="mark as new" alt="[+]" onclick="markLink(\'L'.$link->id.'\',\'new\')" /> ';

  if (!isset($user->prefs['V']) || $user->prefs['V'] != 'L')
    $tb .= '<img src="img/ib-markold_nm.png" title="mark older as read" alt="[-]" onclick="markOlder(\''. $link->feed_id .'\',\''. $link->id .'\')" /> ';

  $tb .= '</span><span class="group">';
  $tb .= '<img src="img/ib-delete_nm.png" title="delete article" alt="[x]" onclick="markLink('."'L{$link->id}','deleted'".')" /> ';

  $tb .= '</span><span class="group">';
  $tb .= '<img src="img/ib-fontbg_nm.png" title="larger font" alt="[A+]" onclick="fontChg('."'L{$link->id}',true,this".')" /> ';
  $tb .= '<img src="img/ib-fontsm_nm.png" title="smaller font" alt="[a-]" onclick="fontChg('."'L{$link->id}',false,this".')" /> ';
  $tb .= '</span>&nbsp;</div>';

  $tb .= '<div class="info"><span class="group"><a href="'. htmlspecialchars($link->link, ENT_COMPAT, 'UTF-8') .'"><img src="img/ib-link_nm.png" title="direct link" alt="[l]"/></a></span>';
  $tb .= ' at '. date(DATE_FORMAT, strtotime($link->pubdate)) .'</div>';

  return $tb;
}

// Disable caching of this page
function noCache() {
  header("Cache-Control: no-cache, must-revalidate");  // http/1.1
  header("Expires: Mon, 1 Jan 2008 05:00:00 GMT");  //past
}

// -------------------------------------------------------------------------------
function mutime() {
  list($usec, $sec) = explode(" ", microtime());
  return ((float)$sec + (float)$usec);
}

if (!function_exists('mb_strlen')) {
  function mb_strlen($s) { return strlen($s); }
}
if (!function_exists('mb_substr')) {
  function mb_substr($s,$i,$j) { return substr($s,$i,$j); }
}

