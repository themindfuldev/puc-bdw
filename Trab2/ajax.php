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

require_once('./inc/config.php');
require_once('./inc/security.php');
require_once('./inc/functions.php');
require_once('./inc/cl_db.php');
require_once('./inc/cl_user.php');
require_once('./inc/cl_feed.php');
require_once('./inc/cl_feedlink.php');

$DEBUG = 0;
//error_reporting(E_ALL);

$restricted = TRUE;
if (($UID = getUidFromCookie()) != NULL)
  $restricted = FALSE;
else
  $UID = getDefaultUid();

// Only allow authorized/guest access to this script
if ($UID != NULL && isset($_REQUEST['op']))
{
  $db = new DB();
  $db->open() or die ("Unable to connect to database.");
  $user = new user ($db, $UID);
  if (!$user->valid || $user->disabled) die ("Bad User.");
  $user->restricted = $restricted;
  if (($user->prefs = getFieldFromCookie('prefs')) == NULL)
    $user->prefs = array();


  switch ($_REQUEST['op']) {

    // Change state of a single link to starred|seen
    case 'marklink':
      if (isset($_POST['id']) && isset($_POST['st'])) {

        noCache();

        $id = substr($_POST['id'],1);
        if (is_numeric($id))
        {
          switch ($_POST['st']) {
            case 'starred': $st = feedlink::STATE_STARRED(); break;
            case 'new':     $st = feedlink::STATE_NEW(); break;
            case 'deleted': $st = feedlink::STATE_DELETED(); break;
            default:        $st = feedlink::STATE_SEEN(); break;
          }
          if (!$user->restricted)
            feedlink::markOne ($db, $user->userid, $id, $st);

          if ($st == feedlink::STATE_DELETED()) {
            echo 'ack|Article deleted.';
          } else {
            $link = new feedlink ($db, $id, $user->userid);
            if ($link->valid)
              echo "marklink|L$id|". ajaxItemBarHTML($user,$link) ."|Article marked.";
          }
        }
      }
      break;

    // Mark all, mark a whole feed, or mark all in feed older than an article
    case 'markfeed':
      if (isset($_POST['id']) && is_numeric($_POST['id'])) {

        noCache();

        $lid = NULL;
        if (isset($_POST['lid']) && is_numeric($_POST['lid']))
          $lid = $_POST['lid'];
        if (!$user->restricted)
          feedlink::mark ($db, $user->userid, $_POST['id'], $lid);
        echo "ack|Feed marked read.";
      }
      break;

    // Get description for an article
    case 'expand':
      if (isset($_REQUEST['id']) && isset($_REQUEST['fid'])) {

        $desc = '';
        $id = intval(substr($_REQUEST['id'],1));

        if ($id > 0)
        {
          $link = new feedlink ($db, $id, $user->userid);
          if ($link->valid)
            $desc = str_replace ('|', '&#124;', ajaxExpandHTML ($user, $link));
          else
            $desc = 'Sorry, no information available.';
        }
        echo "expand|L$id|$desc";

        // Client returns feedid -1 after an expand + collapse + expand 
        // operation, so we count only first expansion (per page load)
        $feedid = intval($_REQUEST['fid']);
        if (!$user->restricted && $user->keep_stats && $feedid > 0)
          feed::inc_stat ($db, $user->userid, $feedid, 'expand');
      }
      break;

    // Get next group of articles
    case 'more':
      if (isset($_REQUEST['id']) && isset($_REQUEST['n'])) {

        noCache();

        $id = intval($_REQUEST['id']); // feed id
        $n = intval($_REQUEST['n']);   // index of next article
        $feed = new feed ($db, $id, $user->userid);

        if ($feed->valid) {

          list ($str, $strnum, $more, $moreseen) =
            ajaxLinkStr ($db, $user, $id, $n, $feed->headlines, FALSE);

          echo "more|$id|";
          if ($more)
            echo morelinkHTML ($id, $n + $strnum, $moreseen);
          echo "$str";
          }
      }
      break;

    // Load articles for feed
    case 'update':
    case 'refresh':
      if (isset($_REQUEST['id'])) {

        noCache();

        $id = intval($_REQUEST['id']);
        $feed = new feed ($db, $id, $user->userid);
        if ($feed->valid) {

          require_once('./inc/rss.php');
          require_once('./magpierss/rss_fetch.inc');
          require_once('./magpierss/rss_utils.inc');

          if (!$user->restricted && $_REQUEST['op'] == 'update')
            forceUpdate ($db, $user->userid, $feed);

          list($numnew, $err,$warn) = readRssFeed ($db, $user, $feed);  // Load new articles if needed
          list ($str, $strnum, $more, $moreseen) =
            ajaxLinkStr ($db, $user, $id, 0, $feed->headlines, TRUE);

          if ($str == '')
            $str = '|none'.$feed->id.'|<span class="seen">&mdash; No new articles available.</span>';

          echo "refresh|$id|$err|$warn|". feedinfoHTML ($feed, $user->keep_stats) ."|$id|";
          if ($more)
            echo morelinkHTML ($id, $strnum, $moreseen);
          echo "$str";
        }
      }
      break;

    default:
      echo 'loser';
      break;
  }
}
?>
