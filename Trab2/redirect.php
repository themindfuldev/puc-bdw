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
require_once('./inc/output.php');
require_once('./inc/cl_db.php');
require_once('./inc/cl_user.php');
require_once('./inc/cl_feed.php');
require_once('./inc/cl_feedlink.php');

($DEBUG = 0) && error_reporting(E_ALL);
noCache();

if (($UID = getUidFromCookie()) != NULL ||
    ($UID = getDefaultUid()) != NULL) {

  $db = new DB();
  $db->open() or die ("Unable to connect to database.");
  $user = new user ($db, $UID);
  if (!$user->valid || $user->disabled) die ("Bad User.");

  $msg = '<p>Bad link.</p>';

  if (isset($_GET['artid'])) {
    $link = new feedlink ($db, intval($_GET['artid']), $user->userid);
    if ($link->valid) {

      if ($link->state != feedlink::STATE_STARRED())
        $link->markOne ($db, $user->userid, $link->id, feedlink::STATE_VISITED());

      /* old behavior: mark articles older than the visited one as seen
      $update_seen = 'UPDATE '. DB_PREFIX.$user->userid .'_links SET state=\''
        . feedlink::STATE_SEEN() ."' WHERE id<{$link->id} AND state='"
        . feedlink::STATE_NEW() ."' AND feed_id='{$link->feed_id}'";
      $db->query($update_seen);
      if ($DEBUG) { echo $db->error(); }
       */

      if ($user->keep_stats)
        feed::inc_stat ($db, $user->userid, $link->feed_id, 'click');

      header("Location: " . $link->link);  // faster, but passes referer
      $msg = '<p>If your browser does not automatically redirect you, click <a href="'. htmlspecialchars($link->link, ENT_COMPAT, 'UTF-8') .'">here</a>.</p>';
    }
  }

  outputHeader ($user);
  //outputHeader ($user, '', '', '', '<meta http-equiv="refresh" content="0;url='. $link->link .'"/>');  // slower, but avoids referer for some browsers
  echo $msg;

} else {
  redirectToLogin();
}

include('./foot.php');
?>
