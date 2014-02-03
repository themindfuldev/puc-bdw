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

$DEBUG = 0;

forceSLogin();
$db = new DB();
$db->open() or die ('Unable to connect to database.');

// -------------------------------------------------------------------------
// Process a create_account request.  Be careful here.
//
if (isset($_POST['create_account'])) {

  require_once('./inc/cl_user.php');
  require_once('./inc/cl_cat.php');
  require_once('./inc/cl_feed.php');
  require_once('./inc/cl_feedlink.php');

  outputHeader();
  echo '<div id="wrapper"><div id="content"><div class="category">';

  // Default policy is to allow creation of an account without passwords--but
  // this is very not recommended!!
  //
	if (!isset($_POST['new_userid'])) {

		echo '<p class="error">Please go back and enter a userid!</p>';

	} elseif ($_POST['new_passwd1'] !== $_POST['new_passwd2']) {

		echo '<p class="error">The two passwords you entered did not match - please try again!</p>';

  } elseif (defined('NEWUSER_SECRET') &&
            NEWUSER_SECRET !== $_POST['secret_entered']) {

    echo '<p class="error">Sorry, I could not create the account!</p>';

	} else {

    $user = new user();

    $user->userid = $_POST['new_userid'];
    $user->name = $_POST['new_name'];
    $user->salt = getRandomHex(4);
    $user->passwd = saltedPass ($user->salt, $_POST['new_passwd1']);

		if ($user->insert($db)) {

      if (feed::create($db, $user->userid) &&
          category::create($db, $user->userid) &&
          feedlink::create($db, $user->userid)) {
				echo '<p class="msg">Account created! Now you may <a href="auth.php">log in</a>, and edit your user preferences.</p>';

        if (isset($_REQUEST['defaultUser']) && $_REQUEST['defaultUser'])
          echo '<p>You indicated that you would like to select a default user\'s feeds to show to non-logged-in visitors.  You may <a href="install.php?selectDefaultUser=1">return and do that now</a>, or <a href="add_user.php?defaultUser=1">add another user account</a>.</p>';
      } else {
        echo '<p class="error">Sorry, I could not create the account!</p>';
        if ($DEBUG) { echo $db->error(); }
      }
		} else {
      echo '<p class="error">Sorry, I could not create the account!</p>';
			if ($DEBUG) { echo $db->error(); }
		}
	}

// -------------------------------------------------------------------------
} elseif (isset($_POST['cancel'])) {

  header ('Location: ' . otherUrl('auth.php', FORCE_SLOGIN));

// -------------------------------------------------------------------------
// Present form for creating a user.  Be careful here.
//
} else {
  outputHeader();
  echo '<div id="wrapper"><div id="content"><div class="category">';
?>
	<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST">
  <fieldset>
  <legend>Create User</legend>
	
  <p><i>All fields are required.</i></p>

	<p>Enter your preferred userid:<br />
	<input type="text" name="new_userid" size="20" maxlength="32" /></p>

	<p>Enter your name:<br />
	<input type="text" name="new_name" size="20" maxlength="64" /></p>

	<p>Enter a password: (Any combination of letters, numbers, and symbols.)<br />
	<input type="password" name="new_passwd1" size="20" maxlength="32" /></p>

	<p>Enter the same password:<br />
	<input type="password" name="new_passwd2" size="20" maxlength="32" /></p>

<?php if (defined('NEWUSER_SECRET') && NEWUSER_SECRET !== ''): ?>
		<p>Please enter the secret phrase - ask the site maintainer if you don't know it:<br />
		<input type="password" name="secret_entered" size="20" maxlength="255" /></p>
<?php endif; ?>
<?php if (isset($_REQUEST['defaultUser']) && $_REQUEST['defaultUser']): ?>
    <input type="hidden" name="defaultUser" value="1" />
<?php endif; ?>

  <p><input type="submit" name="create_account" value="Create Account" />&nbsp;&nbsp;
	<input type="submit" name="cancel" value="Cancel" /></p>
  </fieldset>
  </form>

<?php
// -------------------------------------------------------------------------
}
echo '</div></div></div>';    // container, wrapper, category
include ('./foot.php');
?>
