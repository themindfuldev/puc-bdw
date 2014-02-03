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

require_once('./magpierss/rss_fetch.inc');

($DEBUG = 0) && error_reporting(E_ALL);

// We won't allow any access to this page with the proper authorization
//
if (($UID = getUidFromCookie()) != NULL) {
  $db = new DB();
  $db->open() or die ("Unable to connect to database.");

  $user = new user ($db, $UID);
  if (!$user->valid || $user->disabled) die ("Bad User.");

  // This is really only for new/edited feeds
  $js = <<<ENDJS
function setCheckedValue(rads, newValue) {
  if (!rads) return;
  var len = rads.length;
  if (len == undefined) {
    rads.checked = (rads.value == newValue.toString());
    return;
  }
  for (var i = 0; i < len; i++) {
    if (rads[i].value == newValue.toString()) { rads[i].checked = true; }
    else { rads[i].checked = false; }
  }
}
// Select the proper radio when the select/textbox is clicked.
function setFormEvents() {
  var s1 = document.getElementById("s1");
  if (s1) {
    s1.onmousedown = function() {
      setCheckedValue(document.getElementsByName("select_category"), 1) }
  }
  var i1 = document.getElementById("i1");
  if (i1) {
    i1.onclick = function() {
      setCheckedValue(document.getElementsByName("select_category"), 2) }
  }
}
ENDJS;

  outputHeader ($user, 'Add/Edit feed', NULL, 'setFormEvents()', NULL, $js);
  outputNavigation ($db, $user,
    (isset($_GET['category']) ? intval($_GET['category']) : 0));

  echo '<div id="wrapper"><div id="content">';
  echo checkInsecureFilePerms();
  echo '<div class="category">';

  //-----------------------------------------------------------------------
  // Show form for adding NEW src or EDITing old one
  //
  if (isset($_GET['new']) || isset($_GET['edit'])) {

    if (isset($_GET['edit'])) {
      $feed = new feed($db, intval($_GET['edit']), $user->userid);
      if (isset($_GET['url']))
        $feed->rss_link =  $_GET['url'];
    } else {
      $feed = new feed();
      $feed->cat_id = isset($_GET['category']) ? intval($_GET['category']) : -1;
      $feed->rss_link = isset($_GET['url']) ? $_GET['url'] : '';
      $feed->headlines = $user->headlines;
      $feed->max_links = $user->max_links;
    }
?>
<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST">
  <fieldset>
    <legend><?php echo (($feed->id > 0) ? 'Edit' : 'Add').' a feed' ?></legend>

    <fieldset>
      <legend>Category</legend>

      <input type="radio" name="select_category" value="1" checked="true" /> Use existing Category: 
      <select id="s1" name="existing_category">
        <option value="" <?php echo (($feed->cat_id <= 0) ? 'selected="1"' : '') ?> />-no category selected-</option>
<?php
    foreach (category::all($db, $user->userid, 'ORDER BY name') as $cat) {
      echo '<option '. (($feed->cat_id == $cat->id) ? 'selected="1" ' : '')  .' value="' . $cat->id . '">'. htmlspecialchars($cat->name, ENT_NOQUOTES, 'UTF-8') .'</option>';
    }
?>
      </select><br />

      <input type="radio" name="select_category" value="2" />
      Create new Category:
      <input id="i1" type="text" name="new_category" size="30" maxlength="255" />
    </fieldset>

    <p>Enter a link to the RSS/Atom feed: &nbsp;<b><i>(required)</i></b><br />
    <input type="text" name="rss_link"  size="60" maxlength="400" value="<?php echo htmlspecialchars($feed->rss_link, ENT_COMPAT, 'UTF-8') ?>" /><?php echo helplink('add-rss') ?></p>

    <p>Enter the title of the feed:<br />
    <input type="text" name="source_title" size="40" maxlength="255" value="<?php echo htmlspecialchars($feed->name, ENT_COMPAT, 'UTF-8') ?>" /></p>

    <p>Enter a link to the site's main page:<br />
    <input type="text" name="main_link" size="60" maxlength="255" value="<?php echo htmlspecialchars($feed->main_link, ENT_COMPAT, 'UTF-8') ?>" /></p>

    <p>Enter a link to an image for the site: &nbsp;(<i>use</i> img/xml.png <i>to override an image</i>)<br />
    <input type="text" name="image_url"  size="60" maxlength="255" value="<?php echo htmlspecialchars($feed->image_link, ENT_COMPAT, 'UTF-8') ?>" /></p>

    <p>Enter number of headlines to show:<br />
    <input type="text" name="num_headlines"  size="5" maxlength="255" value="<?php echo $feed->headlines ?>" /><?php echo helplink('prefs-headlines') ?></p>

    <p>Enter maximum number of articles to keep (0 for all):<br />
    <input type="text" name="max_links"  size="5" maxlength="255" value="<?php echo $feed->max_links ?>" /><?php echo helplink('prefs-links') ?></p>

<?php if ($user->keep_stats && $feed->id > 0): ?>
    <fieldset>
      <legend>Other Options</legend>

      <p><i>Stats: <?php echo $feed->stat_expand ?> expanded, <?php echo $feed->stat_click ?> clicked, out of <?php echo $feed->stat_total ?> articles. Score is <b><?php echo $feed->getScore() ?></b>.</i> &nbsp; Clear stats?
      <input type="checkbox" name="clearStats" /> Yes</p>

      <p>Purge all articles from this feed?
      <input type="checkbox" name="purgeArticles" /> Yes</p>

    </fieldset>
<?php endif; ?>

<?php if (isset($_REQUEST['from'])): ?>
    <input type="hidden" name="from_url" value="<?php echo htmlspecialchars($_REQUEST['from'], ENT_COMPAT, 'UTF-8') ?>" />
<?php endif; ?>

    <input type="hidden" name="feed_id" value="<?php echo $feed->id ?>" />
    <p><input type="submit" name="feedSubmit" value="<?php echo (($feed->id > 0) ? 'Make changes' : 'Enter new source') ?>" /></p>
  </fieldset>
</form>

<?php
   if (isset($_REQUEST['from']))
    echo '<p>Or, you can <a href="'.$_REQUEST['from'].'">return to your page</a> instead.</p>';
   echo '<p>Also, see <a href="'. otherUrl('prefs.php?bookmarklet=1') .'">instructions for adding feeds</a> to Rnews easily.</p>';

  //-----------------------------------------------------------------------
  // Incoming POST with new/edited source info
  //
  } elseif (isset($_POST['feedSubmit'])) {

    // validate form contents
    if (!$_POST['rss_link']) {

      echo '<p class="error">Sorry! You have to at least fill in the RSS feed link. Please go back and try again.</p>';

    } elseif (!($rss = fetch_rss($_POST['rss_link']))) {

      echo '<p class="error">Sorry! That feed link didn\'t fetch properly.';
      if (!isset($rss->from_cache))
        echo '<br />Error fetching feed: '. magpie_error();
      echo '</p>';
      echo '<p>Are you sure it is an Atom or RSS feed?  Please go back and try again.</p>';

    } else {

      // Fill in missing fields from the feed
      if (!$_POST['source_title'] && isset($rss->channel['title']))
        $_POST['source_title'] = trim($rss->channel['title']);
      if (!$_POST['main_link'] && isset($rss->channel['link']))
        $_POST['main_link'] = trim($rss->channel['link']);

      if ($_POST['source_title'] && $_POST['main_link']) {

        if ($_POST['select_category'] == 1) {
          $cat = new category ($db, intval($_POST['existing_category']), $user->userid);
        } else {
          // Create the new category and use its ID
          $cat = new category();
          $cat->name = trim($_POST['new_category']);
          if (!$cat->insert ($db, $user->userid)) {
            echo '<p class="error">There has been an error creating the category</p>.';
            if ($DEBUG) { echo $db->error(); }
            unset($cat);
          }
        }

        if (isset($cat) && $cat->valid) {
          if (isset($_POST['feed_id']))
            $feed = new feed ($db, intval($_POST['feed_id']), $user->userid);
          if (!isset($feed) || !$feed->valid)
            $feed = new feed();

          // Warn when the maximum grows
          $warnMaxLinks = ($feed->max_links != $_POST['max_links'] &&
            (!isset($_POST['purgeArticles']) || !$_POST['purgeArticles']) &&
            ($_POST['max_links'] == 0 ||
              ($_POST['max_links'] > $feed->max_links && $feed->max_links > 0)));

          if ($feed->rss_link != $_POST['rss_link'] ||
              $feed->image_link != $_POST['image_url'] ||
              $feed->max_links != $_POST['max_links'] ||
              (isset($_POST['purgeArticles']) && $_POST['purgeArticles']))
            $feed->last_update = '0000-00-00 00:00:00';   // force update

          $feed->name = trim($_POST['source_title']);
          $feed->main_link = trim($_POST['main_link']);
          $feed->rss_link = trim($_POST['rss_link']);
          $feed->image_link = trim($_POST['image_url']);
          $feed->cat_id = $cat->id;
          $feed->headlines = trim($_POST['num_headlines']);
          $feed->max_links = trim($_POST['max_links']);

          if (isset($_POST['clearStats']) && $_POST['clearStats'])
            $feed->clear_stats();
          
          if (isset($_POST['purgeArticles']) && $_POST['purgeArticles'])
            feedlink::purge ($db, $user->userid, $feed->id);

          if ($feed->id > 0)
            $rc = $feed->update ($db, $user->userid);
          else
            $rc = $feed->insert ($db, $user->userid);

          if ($rc) {
            echo '<p class="msg">Updated feed: <a href="index.php?more='. $feed->id .'">'. htmlspecialchars($feed->name, ENT_NOQUOTES, 'UTF-8') .'</a> in category <a href="index.php?category='. $feed->cat_id .'">'. htmlspecialchars($cat->name, ENT_NOQUOTES, 'UTF-8') .'</a>.</p>';
            if (isset($_POST['clearStats']) && $_POST['clearStats'])
              echo '<p class="msg">Stats were cleared.</p>';
            if (isset($_POST['purgeArticles']) && $_POST['purgeArticles'])
              echo '<p class="msg">Articles purged.</p>';
            if ($warnMaxLinks)
              echo '<p class="msg">Note: you increased the maximum number of articles to keep.  If the feed source has more articles than the old setting, some old articles may appear as new on the next update.  You can purge the articles if this is a problem.</p>';
            echo '<p><a href="'. $_SERVER['PHP_SELF'] .'?edit='. $feed->id .'">Edit the feed\'s information.</p>';
            if (isset($_POST['from_url']))
              echo '<p><a href="'.$_POST['from_url'].'">Return</a> to your page.</p>';

          } else {
            echo '<p class="error">There has been an error updating the feed.</p>';
            if ($DEBUG) { echo $db->error(); }
          }

        } else {
          echo '<p class="error">You must select or create a category - please try again!</p>';
        }

      } else {
        echo '<p class="error">Sorry, couldn\'t figure out the title or main link, please provide them for me.</p>';
      }  
    }

  //-----------------------------------------------------------------------
  // Show form for editing existing category
  //
  } elseif (isset($_GET['editCat'])) {
    $cat = new category ($db, intval($_GET['editCat']), $user->userid);
    if (!$cat->valid) { return; }
?>

<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST" name="account_prefs">
  <fieldset>
    <legend>Edit category</legend>

    <p>Enter category name:<br />
    <input type="text" name="categoryName" size="30" maxlength="255" value="<?php echo htmlspecialchars($cat->name, ENT_COMPAT, 'UTF-8')?>" /></p>

    <input type="hidden" name="change_id" value="<?php echo $cat->id ?>" />
    <input type="Submit" name="editCatSubmit" value="Make Changes" />
  </fieldset>
</form>

<?php
  //-----------------------------------------------------------------------
  // Show form for editing existing category
  //
  } elseif (isset($_REQUEST['editCatSubmit'])) {
    $cat = new category ($db, intval($_REQUEST['change_id']), $user->userid);
    if ($cat->valid) {
      $cat->name = $_REQUEST['categoryName'];
      if ($cat->update($db, $user->userid)) {
        echo '<p class="msg">Category <a href="index.php?category='. $cat->id .'">'. htmlspecialchars($cat->name, ENT_NOQUOTES, 'UTF-8') .'</a> updated.</p>';
      } else {
        echo '<p class="error">Edit failed, try again.</p>';
      }
    } else {
      echo '<p class="error">Edit failed, try again.</p>';
    }

  //-----------------------------------------------------------------------
  } else {
    // no action specified
    echo '<p class="error">What was it you wanted to do?</p>';
  }

  // Print header with categories
  //outputNavigation($db, $user);

  echo '</div></div></div>';

} else {  // if auth
  redirectToLogin();
}

include('./foot.php');
?>
