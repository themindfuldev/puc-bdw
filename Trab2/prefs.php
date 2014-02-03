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
require_once('./inc/cl_cat.php');
require_once('./inc/cl_feed.php');
require_once('./inc/cl_feedlink.php');


($DEBUG = 0) && error_reporting(E_ALL);
noCache();

// We won't allow any access to this page with the proper authorization
//
if (($UID = getUidFromCookie()) != NULL) {

  $db = new DB();
  $db->open() or die ("Unable to connect to database.");
  $user = new user ($db, $UID);
  if (!$user->valid || $user->disabled) die ("Bad User.");

  outputHeader ($user, 'Preferences');
	outputNavigation ($db, $user);
  echo '<div id="wrapper"><div id="content">';
  echo checkInsecureFilePerms();
  echo '<div class="category">';

  //----------------------------------------------------------------
  // Form: delete a feed
  //
	if (isset($_GET['delete'])) {
?>
<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST" name="delete_menu">
  <fieldset>
  <legend>Delete Feed</legend>

    <p>Please select the feed to delete:</p>

    <select name="delete_feed">
    <option value="">-no source selected-</option>

<?php
    foreach (feed::all ($db, $user->userid) as $feed)
      echo '<option value="'. $feed->id .'">'. htmlspecialchars($feed->name, ENT_COMPAT, 'UTF-8') .'</option>';
?>

    </select>

    <p><input type="Submit" name="delete_source_button" value="Delete source"></p>
  </fieldset>
</form>

<?php
  //----------------------------------------------------------------
  // Form: delete feed confirm
  //
  } elseif (isset($_REQUEST['delete_feed'])) {

    $delete_source = intval($_REQUEST['delete_feed']);
    $feed = new feed ($db, $delete_source, $user->userid);
    if (!$feed->valid) { die ("Bad feed"); }

?>

<p>Do you really mean to delete <?php echo htmlspecialchars($feed->name, ENT_NOQUOTES, 'UTF-8') ?>?</p>

<form method="post" action="<?php echo $_SERVER['PHP_SELF'] ?>">
  <input type="hidden" name="kill_id" value="<?php echo $delete_source ?>">
  <input type="Submit" name="delete_feed_confirm" value="yeah, go for it">
</form>

<?php
  //----------------------------------------------------------------
  // Action: delete feed
  //
	} elseif (isset($_POST['delete_feed_confirm'])) {

    $feedid = intval($_POST['kill_id']);
		if ($DEBUG) { echo "killing $feedid"; }

    $feed = new feed ($db, $feedid, $user->userid);
    $cat = new category ($db, $feed->cat_id, $user->userid);

    // XXX move SQL into feed and feedlink classes
		$sql = 'DELETE FROM '. DB_PREFIX.$user->userid .'_feeds WHERE id=\''. $feedid .'\'';
		$sql2 = 'DELETE FROM '. DB_PREFIX.$user->userid ."_links WHERE feed_id='". $feedid . "'";

		if ($db->query($sql)) {
			echo '<p class="msg">Feed deleted!</p>';
			if ($db->query($sql2)) {
				echo '<p class="msg">Old links deleted!</p>';
			}
      echo '<p>Back to <a href="index.php?category='. $cat->id .'">'. htmlspecialchars($cat->name, ENT_NOQUOTES, 'UTF-8') .'</a>, or your <a href="index.php?category='. $user->default_cat .'">default category</a>.</p>';
    } else {
    }

  //----------------------------------------------------------------
  // Form: delete a category
  //
	} elseif (isset($_GET['deleteCat'])) {
?>

<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST" name="delete_menu">
  <fieldset>
    <legend>Delete a category</legend>

    Select the category:
    <select name="deleteCatId">
      <option value="">-no category selected-</option>
<?php
    foreach (category::all($db, $user->userid, 'ORDER BY name') as $cat) {
      // Check that no feeds are assigned to this cat
      if (count(feed::all($db, $user->userid, "WHERE cat_id='$cat->id'")) == 0)
        echo '<option value="'. $cat->id .'">'. htmlspecialchars($cat->name, ENT_NOQUOTES, 'UTF-8') ."</option>\n";
		}
?>
    </select>
    <p><i>Note that only categories with no feeds in them are listed.</i></p>
    <p><input type="Submit" name="delete_source_button" value="Delete category"></p>
  </fieldset>
</form>

<?php
  //----------------------------------------------------------------
  // Action: delete a category
  //
	} elseif (isset($_POST['deleteCatId'])) {

    if (intval($_POST['deleteCatId'])) {
      // XXX move SQL to category class
      $sql = 'DELETE FROM '. DB_PREFIX.$user->userid .'_cat WHERE id=\''. intval($_POST['deleteCatId']) .'\'';

      if ($db->query($sql)) {
        echo '<p class="msg">Category deleted!</p>';
        echo '<p>Back to <a href="index.php?category='. $user->default_cat .'">your default category</a>.</p>';
      } else {
        echo '<p class="error">Delete failed; please try again?.</p>';
      }
    } else {
      echo '<p>nothing to delete</p>';
    }

  //----------------------------------------------------------------
  // Form: confirm purge old links
  //
	} elseif (isset($_GET['purge'])) {
?>
<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST" name="purge_form">
  <fieldset>
  <legend>Purge All Feeds</legend>

      <p>Purge articles older than:
      <select name="purgedays">
        <option value="7">1 week</option>
        <option value="14">2 weeks</option>
        <option value="31">1 month</option>
        <option value="62">2 months</option>
        <option selected value="92">3 months</option>
        <option value="163">6 months</option>
      </select></p>

    <p><em>Note: This will purge articles for <b>all</b> feeds. (Articles currently in the feed will reappear.)</em></p>

    <p><input type="Submit" name="purgeconfirm" value="Purge all feeds"></p>
  </fieldset>
</form>
<?php

  //----------------------------------------------------------------
  // Action: purge old links
  //
	} elseif (isset($_POST['purgeconfirm'])) {

    if (isset($_POST['purgedays']) && intval($_POST['purgedays']) > 0) {

      if (feedlink::purgeOld ($db, $user->userid, intval($_POST['purgedays']))) {
        echo '<p class="msg">Purged article links!</p>';
        echo '<p>Back to <a href="prefs.php">Preferences</a>, or your <a href="index.php?category='. $user->default_cat .'">default category</a>.</p>';
      } else {
        echo '<p class="error">Something went wrong, please try again?.</p>';
      }
    }

  //----------------------------------------------------------------
  // Form: purge one feed
  //
	} elseif (isset($_GET['purgeone'])) {
?>

<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST" name="purge_menu">
  <fieldset>
  <legend>Purge Feed</legend>

    <p>Select the feed to purge:</p>

    <select name="purge_feed">
    <option value="">-no source selected-</option>

<?php
    foreach (feed::all ($db, $user->userid, 'ORDER BY name') as $feed)
      echo '<option value="'. $feed->id .'">'. htmlspecialchars($feed->name, ENT_NOQUOTES, 'UTF-8') .'</option>';
?>
    </select>
    <p><em>Note: this will purge <b>all</b> articles in the feed.</em></p>

    <p><input type="Submit" name="purgeoneconfirm" value="Purge feed"></p>
  </fieldset>
</form>

<?php
  //----------------------------------------------------------------
  // Action: purge one feed
  //
	} elseif (isset($_POST['purgeoneconfirm'])) {

    $feedid = intval($_POST['purge_feed']);
		if ($DEBUG) { echo "purging $feedid"; }

    if (feedlink::purge ($db, $user->userid, $feedid)) {
			echo '<p class="msg">Feed purged!</p>';
      echo '<p>Back to <a href="prefs.php">Preferences</a>, or your <a href="index.php?category='. $user->default_cat .'">default category</a>.</p>';
    } else {
      echo '<p class="error">Failed to purge the feed. '. ($DEBUG ? $db->error() : '') .'</p>';
    }

  //---------------------------------------------------------------------
  // Action/Form: show stats, give option to clear
  //
	} elseif (isset($_REQUEST['viewstats'])) {

    $thresh = isset($_REQUEST['thresh']) ? intval($_REQUEST['thresh']) : 0;
    $threshArg = ($thresh > 0 ? "&thresh=$thresh" : '');
?>

<form action="<?php echo $_SERVER['PHP_SELF']. (isset($_REQUEST['sort']) ? ('?sort='.$_REQUEST['sort']) : '') ?>" method="POST">
  <fieldset>
    <legend>Feed Statistics</legend>
    <p>Stats are <b><?php echo ($user->keep_stats ? 'On' : 'Off') ?></b>.</p>
    <p>Feeds are "scored" to indicate how many articles are read, relative to how many are available (expanded + clicked / num articles * 100).</p>

    <p>Show feeds with at least
    <input type="text" name="thresh" size="10" value="<?php echo $thresh ?>" /> <span>articles.</span>  <input type="submit" name="viewstats" value="Filter" /></p>
    <p>Click on column names to sort.</p>

    <table>
    <tr>
      <th><a href="prefs.php?viewstats=1&sort=cat<?php echo $threshArg ?>">Category</a></th>
      <th><a href="prefs.php?viewstats=1&sort=feed<?php echo $threshArg ?>">Feed name</a></th>
      <th><a href="prefs.php?viewstats=1&sort=exp<?php echo $threshArg ?>">expands</a></th>
      <th><a href="prefs.php?viewstats=1&sort=click<?php echo $threshArg ?>">clicks</a></th>
      <th><a href="prefs.php?viewstats=1&sort=art<?php echo $threshArg ?>">articles</a></th>
      <th><a href="prefs.php?viewstats=1&sort=score<?php echo $threshArg ?>">score</a></th>
      <th><a href="prefs.php?viewstats=1&sort=date<?php echo $threshArg ?>">last added</a></th>
    </tr>
<?php

    $numFeeds = 0;
    $numArticles = 0;
    $numSeen = 0;
    $filter = '';

    if ($thresh > 0)
      $filter = "WHERE (stat_total >= $thresh)";

    $filter .= ' ORDER BY';
    if (!isset($_REQUEST['sort']) || $_REQUEST['sort'] == 'score') {
      $filter .= ' (stat_expand+stat_click)/stat_total DESC, cat_id, name';
    } else {
      switch ($_REQUEST['sort']) {
        case 'cat':   $filter .= ' cat_id, name'; break;
        case 'feed':  $filter .= ' name'; break;
        case 'exp':   $filter .= ' stat_expand DESC, stat_click DESC, name'; break;
        case 'click': $filter .= ' stat_click DESC, stat_expand DESC, name'; break;
        case 'art':   $filter .= ' stat_total DESC, stat_click DESC, stat_expand DESC, name'; break;
        case 'score': $filter .= ' stat_total DESC, stat_click DESC, stat_expand DESC, name'; break;
        case 'date':  $filter .= ' last_add DESC, cat_id, name'; break;
      }
    }

    foreach (feed::all ($db, $user->userid, $filter) as $feed) {
      $cat = new category ($db, $feed->cat_id, $user->userid);
      echo '<tr><td><a href="index.php?category='. $cat->id .'">'. htmlspecialchars($cat->name, ENT_NOQUOTES, 'UTF-8') .'</a></td>';
      echo '<td><a href="index.php?more='. $feed->id .'">'. htmlspecialchars($feed->name, ENT_NOQUOTES, 'UTF-8') .'</a></td>';
      echo "<td>$feed->stat_expand</td>";
      echo "<td>$feed->stat_click</td>";
      echo "<td>$feed->stat_total</td>";
      echo '<td>'. $feed->getScore() .'</td>';
      echo '<td>'. ((($t=strtotime($feed->last_add)) > 0)
        ? $feed->last_add : 'never') .'</td></tr>';

      $numFeeds++;
      $numArticles += $feed->stat_total;
      $numSeen += $feed->stat_expand + $feed->stat_click;
    }

    $numCats = count(category::all ($db, $user->userid));
?>
    </table>

    <p>There are <?php echo $numCats ?> categories and <?php echo $numFeeds ?> feeds.<br />
    Since statistics were enabled/cleared, <?php echo $numArticles ?> articles have been posted to the feeds, of which <?php echo $numSeen ?> have been clicked and/or expanded.</p>

    <p><?php echo ($user->keep_stats ? 'Disable stats?' : 'Enable stats?') ?>
    <input type="checkbox" name="enableStats" /> <span>Yes<?php echo helplink('prefs-stats') ?></span></p>
    <p>Clear all stats?
    <input type="checkbox" name="clearStats" /> <span>Yes</span></p>

    <p><input type="Submit" name="setstats" value="Make Changes" /></p>
  </fieldset>
</form>

<?php

  //---------------------------------------------------------------------
  // Action: turn stats on/off or clear them
  //
	} elseif (isset($_POST['setstats'])) {

    if (array_key_exists('enableStats', $_POST)) {
      $user->keep_stats = !$user->keep_stats;
      if ($user->update($db))
        echo '<p class="msg">Stats are now '. ($user->keep_stats ? 'enabled' : 'disabled') .'.</p>';
      else
        echo '<p class="error">There was a problem toggling stats, try again. '. ($DEBUG ? $db->error() : '') .'</p>';
    }

    if (array_key_exists('clearStats', $_POST)) {
      if (feed::clear_all_stats ($db, $user->userid))
        echo '<p class="msg">Stats were cleared.</p>';
      else
        echo '<p class="error">There was a problem clearing stats, try again. '. ($DEBUG ? $db->error() : '') .'</p>';
    }

    echo '<p>Return to <a href="'. $_SERVER['PHP_SELF'] .'?viewstats=1">Stats</a>.</p>';

  //---------------------------------------------------------------------
  // Action/Form: show stats, give option to clear
  //
	} elseif (isset($_REQUEST['bookmarklet'])) {
?>
  <h2>Adding Feeds to Rnews</h2>

  <p>There are three easy ways to add feeds to Rnews:</p>
  <p><b>1.</b> [Firefox] Register Rnews as a feed handler with this <a href="<?php echo rssRegisterUrl() ?>">link</a>.  When you click on a feed, Firefox will give you the option to Subscribe using Rnews.</p>
  <p><b>2.</b> [Firefox] Install the Rnews extension, available at <a href="http://rnews.sourceforge.net/">project's home page</a>, and add its button to your toolbar.  On a page with feeds, you click the button and select a feed to add.</p>
  <p><b>3.</b> [All browsers] Use a bookmarklet to subscribe to feeds.  Bookmarklets are pieces of Javascript code that you can bookmark, and which run some code when clicked.  The following bookmarklet links find RSS/Atom feeds in the current page and let you easily add them to Rnews.</p>
  <p>To put one on your Bookmark Toolbar, make sure the toolbar is visible, then <b>drag</b> the appropriate link onto the toolbar:</p>
  <ul>
    <li><a href="<?php echo bookmarkletUrl() ?>">+ Rnews</a> (any category)<br /><br /></li>
<?php
    foreach (category::all($db, $user->userid, 'ORDER BY name') as $cat) {
      echo '<li><a href="'. bookmarkletUrl($cat) .'">+ Rnews ('.
        htmlspecialchars($cat->name, ENT_NOQUOTES, 'UTF-8') .")</a></li>\n";
    }
?>
  </ul>
<?php

  //----------------------------------------------------------------
  // Action: change user prefs
  //
	} elseif (isset($_POST['change_prefs'])) {

    $user->name = $_POST['username'];
    $user->timeout = $_POST['timeout_select'];
    $user->headlines = $_POST['number_headlines_select'];
    $user->show_images = array_key_exists('showImages', $_POST);
    if (!empty($_POST['default_category']))
      $user->default_cat = $_POST['default_category'];
    $user->max_links = $_POST['maxLinks'];
    $user->keep_stats = array_key_exists('enableStats', $_POST);
    $user->expand_one = !array_key_exists('expandOne', $_POST);
    $user->new_window = array_key_exists('newWindow', $_POST);
    $user->snippets = array_key_exists('snippets', $_POST);

    if ($user->update ($db)) {
			echo '<p class="msg">Preferences were updated!</p>';
      echo '<p>You may go to <a href="'. defaultUrl($user) .'">your default category</a>.</p>';
		} else {
      echo '<p class="error">There has been a problem updating your preferences, please go back and try again. '. ($DEBUG ? $db->error() : '') .'</p>';
		}

  //---------------------------------------------------------------------
  // Form: change user prefs
  //
	} else {
?>

<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST">
  <fieldset>
    <legend>Change User Preferences</legend>

    <p>Name:
    <input type="text" name="username" size="20" maxlength="64" value="<?php echo htmlspecialchars($user->name, ENT_COMPAT, 'UTF-8') ?>" /></p>

    <p>Default category to display:
    <select name="default_category">
    <option value="0">All Sources
<?php
    foreach (category::all($db, $user->userid, 'ORDER BY name') as $cat) {
      echo '<option '. (($user->default_cat == $cat->id) ? 'selected="1" ' : '')  .' value="' . $cat->id . '" />'. htmlspecialchars($cat->name, ENT_NOQUOTES, 'UTF-8') ."\n";
    }
?>
    </select></p>

    <fieldset>
      <legend>Feed Options</legend>

      <p>Minimum time before re-checking feed sources (in minutes):
      <select name="timeout_select">
        <option selected value="<?php echo $user->timeout; ?>"><?php echo ($user->timeout / 60); ?></option>
        <option value="600">10</option>
        <option value="900">15</option>
        <option value="1200">20</option>
        <option value="1500">25</option>
        <option value="1800">30</option>
        <option value="2700">45</option>
        <option value="3600">60</option>
        <option value="5400">90</option>
      </select><?php echo helplink('prefs-timeout') ?></p>

      <p>Default maximum number of articles to keep per feed (0 for no restriction):
      <input type="text" name="maxLinks" size="5" maxlength="10" value="<?php echo $user->max_links ?>" /><?php echo helplink('prefs-links') ?></p>

      <p>Enable feed <a href="<?php echo $_SERVER['PHP_SELF'] ?>?viewstats=1">statistics</a>?
      <input type="checkbox" name="enableStats" <?php echo ($user->keep_stats ? 'checked="true"' : '') ?> /> <span>Yes</span><?php echo helplink('prefs-stats') ?></p>
    </fieldset>

    <fieldset>
      <legend>Display Options</legend>

      <p>Default number of headlines to show for new sources:
      <select name="number_headlines_select">
        <option selected><?php echo $user->headlines; ?></option>
        <option>5</option>
        <option>10</option>
        <option>15</option>
        <option>20</option>
      </select><?php echo helplink('prefs-headlines') ?></p>

      <p>Display site images? 
      <input type="checkbox" name="showImages" <?php echo ($user->show_images ? 'checked="true"' : '') ?> /> <span>Yes</span><?php echo helplink('prefs-images') ?></p>

      <p>Open article links in a new window?
      <input type="checkbox" name="newWindow" <?php echo ($user->new_window ? 'checked="true"' : '') ?> /> <span>Yes</span></p>

      <p>Collapse all articles when viewing a single feed?
      <input type="checkbox" name="expandOne" <?php echo (!$user->expand_one ? 'checked="true"' : '') ?> /> <span>Yes</span></p>

      <p>Show snippets of article contents?
      <input type="checkbox" name="snippets" <?php echo ($user->snippets ? 'checked="true"' : '') ?> /> <span>Yes</span></p>

    </fieldset>

    <p><input type="Submit" name="change_prefs" value="Make Changes" /></p>
  </fieldset>

  <p><b>Other Options:</b></p>
  <ul>
    <li><a href="auth.php?pwchange=1">Change password</a></li>
    <li><a href="add.php?new=y">Add a new feed</a></li>
    <li><a href="<?php echo $_SERVER['PHP_SELF'] ?>?viewstats=1">View and control feed statistics</a></li>
    <li><a href="<?php echo $_SERVER['PHP_SELF'] ?>?bookmarklet=1">View bookmarklet links and other ways to add feeds</a></li>

    <li><a href="export.php">Import or Export feeds to OPML</a></li>
  </ul>
  <p><b>Cleanup Options:</b></p>
  <ul>
    <li><a href="<?php echo $_SERVER['PHP_SELF'] ?>?delete=y" >Delete a feed</a></li>
    <li><a href="<?php echo $_SERVER['PHP_SELF'] ?>?deleteCat=y" >Delete a category</a></li>
    <li><a href="<?php echo $_SERVER['PHP_SELF'] ?>?purgeone=1">Purge all articles in a single feed</a></li>
    <li><a href="<?php echo $_SERVER['PHP_SELF'] ?>?purge=1">Purge old articles in all feeds</a></li>
  </ul>

</form>

<?php
	}

  echo '</div></div></div>';

} else {	// if auth
  redirectToLogin();
}

include('./foot.php');
?>
