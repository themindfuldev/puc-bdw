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
require_once('./inc/opml.php');

($DEBUG = 0) && error_reporting(E_ALL);

if (($UID = getUidFromCookie()) != NULL) {

  $db = new DB();
  $db->open() or die ("Unable to connect to database.");
  $user = new user ($db, $UID);
  if (!$user->valid || $user->disabled) die ("Bad User.");

  //------------------------------------------------------------------
  if (isset($_REQUEST['opmlexp'])) {

    $cat = NULL;
    if  (isset($_REQUEST['exportCat']) && intval($_REQUEST['exportCat']) > 0) {
      $cat = new category ($db, intval($_REQUEST['exportCat']), $user->userid);
      if (!$cat->valid)
        $cat = NULL;
      $catName = $cat->name;
    } else {
      $catName = 'All Sources';
    }

    header ('Content-Type: application/opml+xml; charset=utf-8');
    header ('Content-Disposition: attachment; filename="Rnews - '. $catName .'.opml"');

    echo exportOpml ($db, $user, $cat);
    exit(0);

  //------------------------------------------------------------------
  } elseif (isset($_REQUEST['opmlimp'])) {

    outputHeader ($user, 'OPML Import');
    outputNavigation ($db, $user);
    echo '<div id="wrapper"><div id="content">';
    echo checkInsecureFilePerms();
    echo '<div class="category">';

    $import = array();

    if (isset($_REQUEST['packs']) && is_array($_REQUEST['packs'])) {
      $packs = getPackages('opml');
      foreach ($_REQUEST['packs'] as $t) {
        if (isset($packs[$t]))
          $import[] = $packs[$t]; // save path
      }
    }

    if (!empty($_FILES['opmlfile']['tmp_name']))
      $import[] = $_FILES['opmlfile']['tmp_name'];

    if (count($import) > 0) {

      foreach ($import as $f) {
        if (($opml = file_get_contents($f)) !== FALSE) {
          echo importOpml ($db, $user, $opml);
        } else {
          echo '<p>Server error opening OPML file.</p>';
        }
      }

      echo '<p>You may go to <a href="'. defaultUrl($user) .'">your default category</a>, or use the menu at the left for navigation.</p>';

    } else {
      echo '<p>No files processed, please try again.</p>';
    }

  //------------------------------------------------------------------
  } else {

    outputHeader ($user, 'OPML Operations');
    outputNavigation ($db, $user);
    echo '<div id="wrapper"><div id="content">';
    echo checkInsecureFilePerms();
    echo '<div class="category">';
?>
  <p>OPML is a semi-standard format for sharing outlines and lists, and is used for blogrolls and RSS feeds.  Rnews can export to and import from this format, in particular, it can be used to upgrade from previous versions of the software.</p>
  <form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST" name="export">
    <fieldset>
      <legend>Export Feeds</legend>

      <p>Select the category of feeds to export to OPML format:</p>
      <select name="exportCat">
        <option value="0" selected="1">All Sources</option>
<?php
    $cats = category::all ($db, $user->userid, 'ORDER BY name');
    foreach ($cats as $cat) {
      echo '<option value="'. $cat->id .'">'.
        htmlspecialchars($cat->name, ENT_NOQUOTES, 'UTF-8') .'</option>';
    }
?>
      </select>
      <p><input type="submit" name="opmlexp" value="Export" /></p>
    </fieldset>
  </form>

  <form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST" name="import" enctype="multipart/form-data">
    <fieldset>
      <legend>Import Feeds</legend>

      <p>Select an OPML file to import feeds:</p>
      <p><input type="file" name="opmlfile" /></p>
<?php
    $packs = getPackages('opml');
    if (count($packs) > 0) {
      echo '<p>Or, choose some pre-packaged bundles of feeds:</p><p style="padding-left:2em;">';
      foreach ($packs as $title => $path)
        echo ' <label><input type="checkbox" name="packs[]" value="'. $title .'" /> '. $title ."</label><br />\n";
      echo '</p>';
    }
?>
      <p><input type="submit" name="opmlimp" value="Import" /></p>
    </fieldset>
  </form>

<?php
  }

  echo '</div></div></div>';

} else {
  redirectToLogin();
}

include('foot.php');
?>
