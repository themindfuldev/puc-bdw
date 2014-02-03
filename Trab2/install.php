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
 * Installation is a 3- or 4-step process:
 *  1 - get DB info
 *  2 - write DB config, [make DB,] upgrade, show options
 *  4 - get options
 *  5 - write options, link to add_user
 *  6 - [add a user]
 *  7 - [select default user]
 */

require_once('./inc/output.php');
require_once('./inc/functions.php');
require_once('./inc/security.php');
require_once('./inc/cl_configw.php');

($DEBUG = 0) && error_reporting(E_ALL);

outputHeader(NULL, 'Installation');
?>
  <div id="navigation">
    <p><a href="http://rnews.sourceforge.net/">Rnews</a> is a server-side feed aggregator.  It supports multiple users, and has many functional and security features.  Managing your feeds on the server allows you to browse from anywhere&mdash;your feeds are always consistent.</p>
    <p>For help with installation, use the <i>&lt;help&gt;</i> links, or visit the project's <a href="http://sourceforge.net/forum/?group_id=83806">forums</a> on the web.</p>
  </div>
<?php
echo "<div id=\"wrapper\"><div id=\"content\"><div class=\"category\">\n";

// -------------------------------------------------------------------------
// Select the default user (comes from add_user)
//
if (isset($_REQUEST['selectDefaultUser'])) {
  require_once('./inc/config.php');
  require_once('./inc/cl_db.php');
  require_once('./inc/cl_user.php');

  $db = new DB();
  $db->open() or die ('Unable to connect to database.');
?>
  <form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST">
    <fieldset>
      <legend>Install Rnews: Page 3</legend>
      <fieldset>
        <legend>Security</legend>
        <p>Normally, each user must log in to view their feeds, otherwise no feeds are visible.  However, you may wish to select one user's feeds to be shown to anyone at all without logging in.  Feeds can be added and deleted only after logging in, but anyone can mark them as read.</p>
        <p>Select the default user:
        <select name="defaultUserSelection">
        <option value="">-no user selected-</option>
<?php
  foreach (user::all($db) as $user) {
    echo '<option '. ((DEFAULT_USER == $user->id) ? 'selected="1" ' : '') 
      .' value="' . $user->id . '">'. htmlspecialchars($user->name, ENT_NOQUOTES, 'UTF-8') .'</option>';
  }
?>
        </select>
      </fieldset>
      <p><input type="Submit" name="writeDefaultUser" value="Submit"></p>
    </fieldset>
  </form>

<?php
  // -------------------------------------------------------------------------
  // Write the default user
  //
} elseif (isset($_REQUEST['writeDefaultUser'])) {

  if (isset($_POST['defaultUserSelection']) && intval($_POST['defaultUserSelection']))
  {
    $cfg = new configw();

    if ($cfg->open())
    {
      $cfg->set ('DEFAULT_USER', intval($_POST['defaultUserSelection']));

      if ($cfg->close()) {

        echo '<p class="msg">Updated the configuration file with default user selection.</p>';
        echo '<p>Now you may <a href="auth.php">log in</a> to edit your preferences or add feeds.</p>';

      } else {
        echo '<p class="error">Could not write configuration file!</p>';
      }
    } else {
      echo '<p class="error">Could not read configuration file!</p>';
    }
  } else {
    echo '<p>No changes made.</p>';
  }

  echo checkInsecureFilePerms();

// -------------------------------------------------------------------------
// Write config to the configfile, link to add_user
//
} elseif (isset($_POST['writeOptions'])) {

  require_once('./inc/config.php');
  $cfg = new configw();

  if (!$cfg->open())
  {
    echo '<p class="error">Could not read configuration file!</p>';
  }
  else
  {
    // Harvest data from form
    $secret = "'". (isset($_POST['secret']) ? $_POST['secret'] : '') ."'";
    $forceSlogin = array_key_exists('forceSlogin', $_POST) ? 'TRUE' : 'FALSE';
    $lockIP = array_key_exists('lockIP', $_POST) ? 'TRUE' : 'FALSE';
    $hashKey = "'". getRandomHex(16) ."'";
    $tags = ((isset($_POST['xssStruct']) && $_POST['xssStruct']) ? XSS_TAGS_STRUCT : 0) |
      ((isset($_POST['xssFormat']) && $_POST['xssFormat']) ? XSS_TAGS_FORMAT : 0) |
      ((isset($_POST['xssImage']) && $_POST['xssImage']) ? XSS_TAGS_IMAGE : 0) |
      ((isset($_POST['xssTable']) && $_POST['xssTable']) ? XSS_TAGS_TABLE : 0) |
      ((isset($_POST['xssAny']) && $_POST['xssAny']) ? XSS_TAGS_ANY : 0);
    $title = "'". (isset($_POST['title']) ? $_POST['title'] : 'Feed Aggregator') ."'";
    $cache = (isset($_POST['cache']) && $_POST['cache']) ? 'TRUE' : 'FALSE';
    $cacheImages = array_key_exists('cacheImages', $_POST) ? 'TRUE' : 'FALSE';
    $imgFolder = "'". (isset($_POST['imgFolder']) ? $_POST['imgFolder'] : '') ."'";
    $imgHeight = (isset($_POST['imgHeight']) && is_numeric($_POST['imgHeight']) && intval($_POST['imgHeight']) > 0) ? intval($_POST['imgHeight']) : 0;

    // Edit config
    //
    $cfg->set ('NEWUSER_SECRET', $secret);
    $cfg->set ('FORCE_SLOGIN', $forceSlogin);
    $cfg->set ('COOKIE_HASH_KEY', $hashKey);
    $cfg->set ('COOKIE_LOCK_IP', $lockIP);
    $cfg->set ('XSS_TAGS', $tags);
    $cfg->set ('RNEWS_TITLE', $title);
    $cfg->set ('MAGPIE_CACHE_ON', $cache);
    $cfg->set ('FEED_IMG_CACHE', $cacheImages);
    $cfg->set ('FEED_IMG_FOLDER', $imgFolder);
    $cfg->set ('FEED_IMG_HEIGHT', $imgHeight);

    if (!$cfg->close())
    {
      echo '<p class="error">Failed to open config file for writing.  Check that the Rnews folder and <tt>'. configw::CONFIG_FILE() .'</tt> file have write permissions set.</p>';
    }
    else
    {
      echo '<p class="msg">Configuration written to file <tt>'.configw::CONFIG_FILE().'</tt>.</p>';

      $du = (isset($_POST['defaultUser']) && $_POST['defaultUser']);
?>
  <p class="msg"><strong>Rnews was installed successfully.</strong></p>
  <?php echo checkInsecureFilePerms() ?>
  <p>You can now <a href="add_user.php<?php echo ($du ? '?defaultUser=1' : '') ?>">create a user account</a><?php echo ($du ? ', preferably the one you will select as the default user' : '') ?>, or <a href="auth.php">log in</a> if you already have an account.</p>
<?php
    }
	}


  // -------------------------------------------------------------------------
  // 1 - Write DB config,
  // 2 - Create DB if not exists,
  // 3 - Check for upgrade,
  // 4 - Present page two with options
  //
} elseif (isset($_POST['writeDB']) || isset($_POST['upgradeDB'])) {

  $skipForm = false;

  if (isset($_POST['writeDB']))
  {
    if (!isset($_POST['dbhost']) ||
        !isset($_POST['dbuser']) ||
        !isset($_POST['dbpass']) ||
        !isset($_POST['dbname']))
    {
      echo '<p class="error">You must at least enter all the database information.  Please go back and try again.</p>';
      $skipForm = true;

    } else {

      // Edit the DB-related contents of the config file
      //
      $cfg = new configw();

      if (!$cfg->open())
      {
        echo '<p>Could not read configuration file!</p>';
        $skipForm = true;
      }
      else
      {
        $dbprefix = (isset($_POST['dbprefix']) ? $_POST['dbprefix'] : ''); // optional

        $cfg->set ('DB_HOST', "'".$_POST['dbhost']."'");
        $cfg->set ('DB_USER', "'".$_POST['dbuser']."'");
        $cfg->set ('DB_PASS', "'".$_POST['dbpass']."'");
        $cfg->set ('DB_DATABASE', "'".$_POST['dbname']."'");
        $cfg->set ('DB_PREFIX', "'$dbprefix'");

        if (!$cfg->close())
        {
          echo '<p class="error">Could not write configuration file!</p>';
          $skipForm = true;
        }
        else
        {
          echo '<p class="msg">Database configuration written to file <tt>'.configw::CONFIG_FILE().'</tt>.</p>';

          // -----------------------------------
          // Now include the just modified file.
          require_once('./inc/config.php');
          require_once('./inc/cl_db.php');
          require_once('./inc/cl_user.php');
          require_once('./inc/cl_feedlink.php');
          require_once('./inc/upgrade.php');

          // Try to open DB to see if it exists already
          // 
          $db = new DB();
          if (!$db->open())
          {
            // DB open failed, try to create it.
            if (!db::create()) {

              die ('<p class="error">Database creation failed ('. mysql_error() .'), please check the parameters and try again.</p>');

            } else {

              $db->open() or die ("Unable to connect to database.");
            }
          }

          // See if the database is there already in some form
          //
          $sql = 'SELECT userid FROM '.DB_PREFIX.'user_prefs LIMIT 1';
          if (!$db->query($sql))
          {
            // Create user_prefs table
            if (!user::create($db)) {
              echo '<p class="error">Table creation failed.</p>';
              $skipForm = true;
            } else {
              echo '<p class="msg">Database was initialized successfully.</p>';
            }
          }
          else
          {
            // Check for UPGRADEs
            $curVer = grokDBVersion($db);
            if (isUpgradeNeeded ($curVer))
            {
              $skipForm = true;
?>
  <form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="post">
    <fieldset>
      <legend>Install Rnews: Upgrade Database Confirm</legend>

      <p>It seems that your database is from Rnews version <?php echo $curVer ?>.  If so, it must be upgraded to version <?php echo RNEWS_VERSION ?> before continuing.</p>

      <p>Before proceeding, we recommend that you <b>backup your database</b>, since if one part of the upgrade fails it may leave the database in an inconsistent state that is not easily fixed.</p>  If you can not or do not want to backup, at least save your feeds by exporting to OPML.</p>

<?php echo upgradeNote($curVer) ?>

      <p><input type="submit" name="upgradeDB" value="Upgrade"></p>
    </fieldset>
  </form>
<?php
            } // needs upgrade
          } // DB is there
        } // wrote cfg
      } // read cfg
    } // DB fields given
  } // writeDB

  // Handle DB upgrade path
  //
  if (isset($_POST['upgradeDB']))
  {
    // -----------------------------------
    require_once('./inc/config.php');
    require_once('./inc/cl_db.php');
    require_once('./inc/cl_user.php');
    require_once('./inc/cl_feed.php');
    require_once('./inc/cl_feedlink.php');
    require_once('./inc/upgrade.php');

    $db = new DB();
    $db->open() or die ("Unable to connect to database.");

    $curVer = grokDBVersion($db);
    if (isUpgradeNeeded ($curVer))
    {
      list ($rc, $ver, $msg) = upgradeDatabase ($db, $curVer);

      if ($rc) {

        // Check for local images that did not make the move
        deleteBadFeedImages($db);

        echo '<p class="msg"><b>Database upgraded to version '. RNEWS_VERSION .'.</b><br />'
          . $msg .'</p>';
        echo '<p class="msg">Please Continue with the installation options below, as they may have changed.</p>';
      } else {
        echo '<p class="error">Upgrade failed: '.$msg.'</p>';
        $skipForm = true;
      }
    } else {
      echo '<p class="error">It doesn\'t seem that you need to upgrade from '.$curVer
        . ' to '. RNEWS_VERSION .'.</p>';
    }
  }

  if (!$skipForm)
  {
?>
  <form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST">
    <fieldset>
      <legend>Install Rnews: Page 2</legend>

      <fieldset>
        <legend>Security</legend>

        <ol>
        <li><p>To control who may create an account, define a secret below.  New users will have to enter the secret before being allowed to register an account.  This is highly recommended, and you should <b>change the default value</b>.</p>
        <p>New user secret:<br />
        <input type="text" name="secret" size="32" maxlength="32" value="<?php echo htmlspecialchars(NEWUSER_SECRET, ENT_COMPAT, 'UTF-8') ?>" /></p></li>

        <li><p>If you can use SSL on your web server, it is recommended to force logins and password changes to occur over this secure link.  This prevents passwords from traversing the network in the clear, subject to eavesdropping.</p>
        <p>Force secure login?
        <input type="checkbox" name="forceSlogin" <?php echo (FORCE_SLOGIN ? 'checked="true"' : '') ?> /> <span>Yes</span></p></li>

        <li><p>Unless your users visit Rnews from mainly mobile devices, you should lock the cookies to the IP address used to login.  This can help mitigate third-party spoofing.</p>
        <p>Lock IP address in cookies?
        <input type="checkbox" name="lockIP" <?php echo (COOKIE_LOCK_IP ? 'checked="true"' : '') ?> /> <span>Yes</span></p></li>

        <li><p>Since RSS feeds are published by other websites and are displayed on a web page, you probably do not want to allow arbitrary HTML tags to appear there.  This opens you up to Cross-Site Scripting (XSS) attacks.  Select the types of tags you wish to <b>allow</b> below.</p>
        <p>Allowed tags:<br />
        <input type="checkbox" name="xssStruct" <?php echo ((XSS_TAGS & XSS_TAGS_STRUCT) ? 'checked="true"' : '') ?> /> <span>Basic structure&nbsp;&nbsp;</span>
        <input type="checkbox" name="xssFormat" <?php echo ((XSS_TAGS & XSS_TAGS_FORMAT) ? 'checked="true"' : '') ?> /> <span>Formatting&nbsp;&nbsp;</span>
        <input type="checkbox" name="xssImage" <?php echo ((XSS_TAGS & XSS_TAGS_IMAGE) ? 'checked="true"' : '') ?> /> <span>Images&nbsp;&nbsp;</span>
        <input type="checkbox" name="xssTable" <?php echo ((XSS_TAGS & XSS_TAGS_TABLE) ? 'checked="true"' : '') ?> /> <span>Tables&nbsp;&nbsp;</span>
        <input type="checkbox" name="xssAny" <?php echo (((XSS_TAGS & XSS_TAGS_ANY) == XSS_TAGS_ANY) ? 'checked="true"' : '') ?> /> <span>Everything else, too (not recommended)</span></p></li>

        <li><p>Normally, each user must log in to view their feeds, otherwise no feeds are visible.  However, you may wish to select one user's feeds to be shown to anyone at all without logging in.  Feeds can be added and deleted only after logging in, but anyone can mark them as read.</p>
        <p>Choose a default user after installation?
        <input type="checkbox" name="defaultUser" <?php echo ((DEFAULT_USER > 0) ? 'checked="true"' : '') ?> /> <span>Yes</span></p></li>
        </ol>
      </fieldset>

      <fieldset>
        <legend>General Options</legend>

        <ol>
        <li><p>Title to be shown on all pages next to the Rnews logo.<br />
        <input type="text" name="title" size="25" maxlength="100" value="<?php echo RNEWS_TITLE ?>" /></p></li>

        <li><p>Feed contents can be cached on the web server.  This is only useful if it is likely that multiple users will subscribe to some of the same feeds, since Rnews only fetches feeds every so often anyway.</p>
        <p>Cache feeds?
        <input type="checkbox" name="cache" <?php echo (MAGPIE_CACHE_ON ? 'checked="true"' : '') ?> /> <span>Yes</span></p></li>

        <li><p>Feed images can be copied to the web server to reduce load on the publishing sites.  This is recommended.</p>
        <p>Cache feed images?
        <input type="checkbox" name="cacheImages" <?php echo (FEED_IMG_CACHE ? 'checked="true"' : '') ?> /> <span>Yes</span></p></li>

        <ul>
          <li><p>If caching feed images, where should they be stored?  This folder name is relative to the installation folder.  It must be writable by the web server.<br />
          <input type="text" name="imgFolder" size="25" maxlength="150" value="<?php echo FEED_IMG_FOLDER ?>" /></p></li>

          <li><p>If feed images are cached (see above), they can also be first resized so they don't mess up the layout of Rnews too badly. Blank or 0 (zero) turns off resizing.</p>
          <p>Maximum image height (pixels):<br />
          <input type="text" name="imgHeight" size="5" maxlength="5" value="<?php echo FEED_IMG_HEIGHT ?>" /></p></li>
        </ul>
        </ol>
      </fieldset>

      <p><input type="submit" name="writeOptions" value="Next Page"></p>
    </fieldset>
  </form>
<?php
  }


  // -------------------------------------------------------------------------
  // Present initial screen: database settings
  //
} else {
  require_once('./inc/config.php');
?>

  <form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST">
    <fieldset>
      <legend>Install Rnews</legend>
      
      <img style="float:right" src="img/screenshot-sm-g.jpg" alt="screenshot" />
      <p>This page installs/upgrades Rnews version <?php echo RNEWS_VERSION ?>.  The latest version is always available at the <a href="http://rnews.sourceforge.net/">Rnews web site</a>.</p>
      <p>Requirements: 1) a web server with PHP version &gt;= 4.3.0, and 2) a MySQL database account.</p>
<?php
  if (!is_readable(configw::CONFIG_FILE()) ||!is_writable(configw::CONFIG_FILE())) {
    echo '<p class="error">Error: the file '. configw::CONFIG_FILE() .' is not writable.  You will not be able to install until you change the file mode to make it writable.</p>';
  }
?>
      <fieldset>
        <legend>Database Information</legend>
        <p><em>Note: only MySQL is currently supported.</em></p>

        <ol>
        <li><p>Enter the <i>hostname</i> on which the database resides. To use the same host as the web server enter <i>localhost</i> (on a Unix derivative), or '<b>.</b>' (on Windows):<br />
        <input type="text" name="dbhost" size="50" maxlength="200" value="<?php echo DB_HOST ?>" /><span>&nbsp;<i>(required)</i></span></p></li>

        <li><p>Enter the <i>user ID</i> for accessing the database:<br />
        <input type="text" name="dbuser" size="20" maxlength="32" value="<?php echo DB_USER ?>" /><span>&nbsp;<i>(required)</i></span></p></li>

        <li><p>Enter the <i>password</i> for accessing the database:<br />
        <input type="password" name="dbpass" size="20" maxlength="32" value="<?php echo htmlspecialchars(DB_PASS, ENT_COMPAT, 'UTF-8') ?>" /><span>&nbsp;<i>(required)</i></span></p></li>

        <li><p>Enter the <i>name of the database</i> to create or use:<br />
        <input type="text" name="dbname" size="20" maxlength="32" value="<?php echo DB_DATABASE ?>" /><span>&nbsp;<i>(required)</i></span></p></li>

        <li><p>Enter the <i>table prefix</i>, if desired (useful if your DB provider only allows you one database):<br />
        <input type="text" name="dbprefix" size="20" maxlength="32" value="<?php echo DB_PREFIX ?>" /></p></li></ol>
      </fieldset>

      <p><input type="submit" name="writeDB" value="Next Page"></p>
    </fieldset>
  </form>


<?php
}
echo "</div></div></div>";    // container, wrapper, category
include ('./foot.php');
?>
