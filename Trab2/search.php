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
 *  view={L|B}                - change preferred view to list or block
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

$DEBUG = 0;
if ($DEBUG) error_reporting(E_ALL);

// Only for this page do we allow a default user to pass.
$restricted = TRUE;
if (($UID = getUidFromCookie()) != NULL) {
  $restricted = FALSE;
} else {
  $UID = getDefaultUid();
}

/*
 * this if statement doesn't close until way down at the bottom!
 */
if ($UID != NULL)
{
  $db = new DB();
  $db->open() or die ('Unable to connect to the database server.');

  $user = new user ($db, $UID);
  if (!$user->valid || $user->disabled) die ('Bad user, sorry.');
  $user->restricted = $restricted;

  $getArgs = array();
  if (($user->prefs = getFieldFromCookie('prefs')) == NULL)
    $user->prefs = array();

  // -------------------------------------------------------------------------------
  // Parse some arguments
  //
  if (isset($_REQUEST['category']) && is_numeric($_REQUEST['category'])) {
    $category = new category ($db, intval($_REQUEST['category']), $user->userid);
    if ($category->valid) {
      $getArgs['category'] = $category->id;
    } else {
      $category = NULL;
    }
  }

  if (isset($_REQUEST['more']) && is_numeric($_REQUEST['more'])) {
    $feed = new feed ($db, intval($_REQUEST['more']), $user->userid);
    if ($feed->valid) {
      $getArgs['more'] = $feed->id;
    } else {
      $feed = NULL;
    }
  }

  if (updatePrefs($user))
    setAuthCookie ($UID, !$restricted,
      (isset($user->prefs['R']) ? (time()+60*60*24*20) : 0), $user->prefs);

  // Begin output
  outputHeader ($user, 'Search', 'Search Results',
    "rnewsInit({'snip':{$user->snippets}})", NULL, NULL, $getArgs);
  outputNavigation ($db, $user, isset($feed) ? $feed->cat_id :
    (isset($category) ? $category->id : 0));

  echo '<div id="wrapper"><div id="content">';
  echo checkInsecureFilePerms();

  // -------------------------------------------------------------------------------
  // Parse search request
  //
  if (isset($_REQUEST['q'])) {

    // Filter out anything non-alphanumeric
    $q = preg_replace('[^A-Za-z0-9 ]', '', $_REQUEST['q']);
    $q = mysql_real_escape_string($q);
    $getArgs['q'] = $q;

    $search = "title LIKE '%$q%'";
    if (isset($_REQUEST['contents']))
    {
      $search = "($search OR description LIKE '%$q%')";
      $getArgs['contents'] = 1;
    }
    if (isset($_REQUEST['star']))
    {
      $search .= " AND state='".feedlink::STATE_STARRED()."'";
      $getArgs['star'] = 1;
    }

    if (isset($feed))
      outputOneFeed ($db, $user, $feed, $getArgs, $search);
    else
      outputFeeds ($db, $user, $category, $getArgs, $search);

  } else {
?>
<div class="category">
<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST">
  <fieldset>
    <legend>Search Articles</legend>
    <p>Search phrase:
    <input type="text" name="q" size="25" maxlength="64" value="" /><?php echo helplink('search') ?></p>
    <p>Will search for phrase exactly as given, except that if it is all lowercase, the search will be case-insensitive.  Non-alphanumeric characters are removed.</p>

    <fieldset>
      <legend>Limit results to:</legend>
      <p>Category:
      <select name="category">
        <option value="0">All Sources
<?php
    foreach (category::all($db, $user->userid, 'ORDER BY name') as $cat) {
      echo '<option '. ((isset($category) && $category->id == $cat->id) ? 'selected="1" ' : '')  .' value="' . $cat->id . '" />'. htmlspecialchars($cat->name, ENT_NOQUOTES, 'UTF-8') ."\n";
    }
?>
      </select></p>

      <p>Feed:
      <select name="more">
        <option value="0">All Feeds
<?php
    foreach (feed::all($db, $user->userid, 'ORDER BY name') as $fd) {
      echo '<option '. ((isset($feed) && $feed->id == $fd->id) ? 'selected="1" ' : '')  .' value="' . $fd->id . '" />'. htmlspecialchars($fd->name, ENT_NOQUOTES, 'UTF-8') ."\n";
    }
?>
      </select></p>

      <p>Search contents of article, too?
      <input type="checkbox" name="contents" /> <span>Yes</span><?php echo helplink('search-contents') ?></p>
      <p>Limit to starred favorites?
      <input type="checkbox" name="star" /> <span>Yes</span></p>
    </fieldset>

    <p><input type="Submit" name="search" value="Search" /></p>
  </fieldset>
</form>

<?php
  }

  echo '</div></div>';

} else {  // here's the continuation of the 'if userid cookie is set' part
  redirectToLogin();
}

include('./foot.php');
?>
