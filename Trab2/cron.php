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
 * Args: [force=1] uid={x|all} [ { category=x | or feed=x } ]
 *       listuid
 *
 * Retrieves feeds from cron or other task scheduler.
 * To be invoked by command line (never by the web), eg:
 *   % ( cd /path/to/rnews;  php cron.php listuid )
 *   % ( cd /path/to/rnews;  php cron.php uid=all category=11 )
 *   % ( cd /path/to/rnews;  php cron.php uid=3 feed=201 force=1 )
 *
 * THERE IS NO SECURITY.  Protect access to this script.
 *
 * Some uid must be given. If 'all' is given, all users' feeds are checked.
 * If no category or feed is given for a user, all feeds are checked.
 * If force=1 is given, feeds are updated regardless of time since last update.
 *
 */

if ($_SERVER['DOCUMENT_ROOT'] || !isset($_SERVER['SHELL']))
  exit;

require_once('./inc/config.php');
require_once('./inc/security.php');
require_once('./inc/functions.php');
require_once('./inc/cl_db.php');
require_once('./inc/cl_user.php');
require_once('./inc/cl_feed.php');
require_once('./inc/cl_feedlink.php');
require_once('./inc/rss.php');
require_once('./magpierss/rss_fetch.inc');
require_once('./magpierss/rss_utils.inc');

($DEBUG = 0) && error_reporting(E_ALL);

$db = new DB();
$db->open() or die ("Unable to connect to database.");

// Parse arguments, as given above
if (isset($_SERVER['argv'])) {
  foreach ($_SERVER['argv'] as $arg) {
    if (strpos($arg,'=') !== false) {
      $a = explode('=',$arg);
      $args[$a[0]] = $a[1];
    } else if ($arg == 'listuid') {
      foreach (user::all($db) as $user)
        if (!$user->disabled)
          echo "user {$user->userid} ({$user->name}) is uid {$user->id}\n";
      exit;
    }
  }
}

$force = isset($args['force']) && $args['force'];

if (isset($args['uid'])) {
  if ($args['uid'] === 'all')
    $users = user::all($db);
  else
    $users = array(new user ($db, intval($args['uid'])));
} else {
  exit;  // require an arg
}


foreach ($users as $user)
{
  if ($user->valid && !$user->disabled)
  {
    if (isset($args['category']))  // all feeds in category
    {
      $feeds = feed::all ($db, $user->userid,
        "WHERE cat_id='". intval($args['category']) ."'");
    }
    else if (isset($args['feed']))  // just one feed
    {
      $feeds = array(new feed ($db, intval($args['feed']), $user->userid));
    }
    else
    {
      $feeds = feed::all ($db, $user->userid);  // all feeds
    }

    foreach ($feeds as $feed) {
      if ($feed && $feed->valid) {
        echo "reading uid {$user->id} feed {$feed->id} ";
        list($n,$e,$w) = readRssFeed ($db, $user, $feed, $force);
        echo " added $n to \"{$feed->name}\"\n";
        if ($e)
          echo " error: $e\n";
        if ($w)
          echo " warning: $w\n";
      }
    }
  }
}

?>
