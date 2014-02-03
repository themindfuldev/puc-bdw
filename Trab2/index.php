<?php
/*
 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/*
 * Arguments to this script:
 *  category=<cat id>         - shows only the given category
 *  more=<feed id>            - shows only the given feed
 *  seen={<feed id>|all|cat}  - marks the specified feed, category, or all read
 *  update=<feed id>          - force update of the given feed
 *  view={L|B|W}              - change preferred view to list or block/wide block
 *  sort={S|N}                - change feed sort order to score or name
 */
require_once('./inc/config.php');
require_once('./inc/security.php');
require_once('./inc/functions.php');
require_once('./inc/output.php');
require_once('./inc/cl_db.php');
require_once('./inc/cl_user.php');
require_once('./inc/cl_cat.php');
require_once('./inc/cl_feed.php');
require_once('./inc/cl_feedlink.php');

require_once('./magpierss/rss_fetch.inc');
require_once('./magpierss/rss_utils.inc');

($DEBUG = 0) && error_reporting(E_ALL);
noCache();

// Only for this page do we allow a default user to pass.
$restricted = TRUE;
if (($UID = getUidFromCookie()) != NULL)
  $restricted = FALSE;
else
  $UID = getDefaultUid();


if ((!MAGPIE_CACHE_ON || is_writable(MAGPIE_CACHE_DIR)) &&
    $UID != NULL)
{
  $db = new DB();
  $db->open() or die ('Unable to connect to the database server.');

  $user = new user ($db, $UID);
  if (!$user->valid || $user->disabled) die ('Bad user, sorry.');
  $user->restricted = $restricted;

  $pageTitle = '';
  $getArgs = array();
  if (($user->prefs = getFieldFromCookie('prefs')) == NULL)
    $user->prefs = array();

  // -------------------------------------------------------------------------------
  // Parse some command line arguments
  //
  if ((isset($_REQUEST['category']) && is_numeric($_REQUEST['category'])) ||
      (!isset($_REQUEST['all']) && !isset($_REQUEST['more']) && $user->default_cat > 0))
  {
    // If user really wants to see all feeds, 'all' must be set
    $cid = isset($_REQUEST['category']) ? intval($_REQUEST['category']) : $user->default_cat;
    $category = new category ($db, $cid, $user->userid);
    if ($category->valid) {
      $getArgs['category'] = $category->id;
      $pageTitle .= $category->name;
    } else {
      $category = NULL;
    }
  }

  if (isset($_REQUEST['more']) && is_numeric($_REQUEST['more'])) {
    $feed = new feed ($db, intval($_REQUEST['more']), $user->userid);
    if ($feed->valid) {
      $getArgs['more'] = $feed->id;
      $pageTitle .= $feed->name;
    } else {
      $feed = NULL;
    }
  }

  // -------------------------------------------------------------------------------
  if (updatePrefs($user))
    setAuthCookie ($UID, !$restricted,
      (isset($user->prefs['R']) ? (time()+60*60*24*20) : 0), $user->prefs);

  // -------------------------------------------------------------------------------
  // 1. Mark the given feed, category, or all feeds as read
  //    seen={<feed id>|all|cat}
  // Note: do this before (potentially) reading new ones below.
  //
  if (isset($_REQUEST['seen'])) {
    if (!$user->restricted) {
      if ($_REQUEST['seen'] === 'cat' && $category)
        markCat ($db, $user->userid, $category->id);
      else
        feedlink::mark ($db, $user->userid,
          (is_numeric($_REQUEST['seen']) ? $_REQUEST['seen'] : null));
    }
    $statusNow = $statusDone = 'Feeds marked read.';
  }

  // -------------------------------------------------------------------------------
  // 2. Force an update of the given feed, by changing the last_update timestamp
  //
  if (isset($_REQUEST['update']) && !$user->restricted) {
    $fd = new feed ($db, intval($_REQUEST['update']), $user->userid);
    if ($fd->valid) {
      if (forceUpdate ($db, $user->userid, $fd))
        $statusNow = $statusDone = 'Feed updated.';
      else
        $statusNow = $statusDone = 'Update failed!';
      if (isset($_REQUEST['more']) && ($_REQUEST['update'] == $_REQUEST['more']))
        $feed = $fd;
    }
  }

  // -------------------------------------------------------------------------------
  // 3a. List an individual feed's articles, including descriptions
  // 3b. Output feeds for all/one category
  //
  if (!isset($statusNow)) {
    $statusNow = 'Loading...';
    $statusDone = 'Loading...done.';
  }

  $jsinit = "rnewsInit({'msg':'$statusDone'"
    .",'async':". ((AJAX_LOAD && !isset($feed))?'1':'0')
    .",'max':". AJAX_PARALLEL
    .",'timeout':". AJAX_TIMEOUT
    .",'snip':". $user->snippets
    .",'hideEmpty':". ((isset($user->prefs['F']) && $user->prefs['F'] == 'N') ? '1' : '0')
    ."})";
  outputHeader ($user, $pageTitle, $statusNow, $jsinit, NULL, NULL, $getArgs);
  outputNavigation ($db, $user,
    isset($feed) ? $feed->cat_id : (isset($category) ? $category->id : 0));

  echo '<div id="wrapper"><div id="content">'.checkInsecureFilePerms();

  if (isset($feed))
    outputOneFeed ($db, $user, $feed, $getArgs);
  else
    outputFeeds ($db, $user, $category, $getArgs);

  echo '</div></div>';

  //outputNavigation($db, $user);   // moved to before content

  // here's the continuation of the 'if userid cookie is set' part
} else {

  if (MAGPIE_CACHE_ON && !is_writable(MAGPIE_CACHE_DIR)) {
    outputHeader ($user, 'error');
    echo '<p class="msg">'. MAGPIE_CACHE_DIR .' does not seem to be writable!! Please give the webserver write access to the cache directory.</p>';
  } else {
    redirectToLogin();
  }
}

include('./foot.php');
?>
