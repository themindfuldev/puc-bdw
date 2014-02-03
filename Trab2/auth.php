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
 * We can't include the header because cookies must be set before *any* output
 */
require_once('./inc/config.php');
require_once('./inc/security.php');
require_once('./inc/functions.php');
require_once('./inc/output.php');
require_once('./inc/cl_db.php');
require_once('./inc/cl_user.php');
require_once('./inc/cl_cat.php');


$DEBUG = 0;

$db = new DB();
$db->open() or die ("Unable to connect to database.");

if (($UID = getUidFromCookie()) != NULL) {
  $user = new user ($db, $UID);
  if (!$user->valid || $user->disabled) unset($user);
}


// -------------------------------------------------------------------------
// Process a login request.  Be very careful here!
//
if (isset($_POST['auth_user'])) {

  forceSlogin();

  $ambiguousRejectMsg = '<p>The system could not verify your account info. Please go <a href="'.
    $_SERVER['PHP_SELF'] . '">back</a> and try again.</p>';

	if ( !isset($_POST['userid_entered']) || !isset($_POST['passwd_entered']) ) {

    // Missing fields
    $msg = '<p>Userid or password missing! Please try again.</p>';

	} else {
		$userid_entered = $_POST['userid_entered'];
		$passwd_entered = $_POST['passwd_entered'];
		$remember_me = array_key_exists('remember_me', $_POST);
		$redirectUrl = array_key_exists('redirect', $_POST) ? $_POST['redirect'] : NULL; 
		$jsSupported = array_key_exists('js_supported', $_POST) ? $_POST['js_supported'] : 0; 

		if ($userid_entered !== filterUserid($userid_entered)) {

      // Icky userid--could be an SQL injection attack
      $msg = $ambiguousRejectMsg;

		} else {

      $user = user::fromUserid ($db, $userid_entered);
			if ($user == NULL || !$user->valid || $user->disabled) {

        // no matching userid
        $msg = $ambiguousRejectMsg;
        $user = NULL;

			} else {

        $checkPass = saltedPass ($user->salt, $passwd_entered);
        if ($checkPass !== $user->passwd) {

          // bad passwd for given userid
          $msg = $ambiguousRejectMsg;
          $user = NULL;

        } else {

          $prefs = array();
          $expiry = 0;                      // default: expire when browser closes
          if ($remember_me) {
            $expiry = time()+60*60*24*20;   // remember me: expires in 20 days
            $prefs['R'] = 1;
          }
					$prefs['J'] = $jsSupported ? 1 : 0;

          // Update cookie to indicate login
          setAuthCookie ($user->id, '1', $expiry, $prefs);

          // Redirect to non-ssl default page
          //header('Location: '. defaultUrl ($user));   // IE pops warning on https->http xition
          $meta = '<meta http-equiv="refresh" content="3;url='. (($redirectUrl != NULL) ? $redirectUrl : defaultUrl($user)) .'" />';

          $msg = '<p>'. $user->name .', you have been successfully logged in. ';
          if ($redirectUrl != NULL) {
            $msg .= 'You are being redirected to your <a href="'. $redirectUrl .'">requested page</a>, or ';
          }
          $msg .= 'You may go to <a href="'. defaultUrl($user) .'">your default category</a>.</p>';
        }
			}
		}
	}

  if (isset($meta))
    outputHeader (isset($user) ? $user : NULL, 'redirect', NULL, NULL, $meta);
  else
    outputHeader (isset($user) ? $user : NULL);
  echo "<div id=\"wrapper\"><div id=\"contentfull\"><div class=\"category\">\n";
  echo $msg;
	
// -------------------------------------------------------------------------
} elseif (isset($_GET['logout'])) {

	if (isset($user)) {
		setAuthCookie ($user->id, '0', 0);
		$_COOKIE[COOKIE_NAME] = null;

    $msg = '<p>You are now logged out. You may <a href="' . $_SERVER['PHP_SELF'] . '">log in</a> again.</p>';
	} else {
		$msg = '<p>You are not logged in!</p>';
  }

  outputHeader();
  echo "<div id=\"wrapper\"><div id=\"contentfull\"><div class=\"category\">\n";
  echo $msg;

// -------------------------------------------------------------------------
// Change password.  Be careful here (though user has logged in already).
//
} elseif (isset($_POST['do_pwchange'])) {

  forceSlogin();

  if (!isset($user)) {

    $msg = '<p>Sorry, not logged in.</p>';

  } else {

    if (!isset($_POST['new_passwd1']) || $_POST['new_passwd1'] != $_POST['new_passwd2']) {

      $msg = '<p>New passwords are not the same! Please go back and try again.</p>';

    } else {

      $checkPass = saltedPass ($user->salt, $_POST['old_passwd']);
      if ($checkPass !== $user->passwd) {

        $msg =  '<p>Sorry.  Please go back and try again!</p>';

      } else {

        // generate a new salt on pw change
        $user->salt = getRandomHex(4);
        $user->passwd = saltedPass ($user->salt, $_POST['new_passwd1']);

        if ($user->update($db)) {

          $meta = '<meta http-equiv="refresh" content="3;url='. otherUrl('prefs.php') .'" />';
          $msg = '<p>Password changed.  Redirecting back to <a href="'.
            otherUrl('prefs.php') .'">preferences</a>.</p>';

        } else {

          $msg = '<p>Hmm. That didnt work, try again?</p>';

        }
      }
    }
  }

  if (isset($meta))
    outputHeader(isset($user) ? $user : NULL, 'redirect', NULL, NULL, $meta);
  else
    outputHeader(isset($user) ? $user : NULL);
  echo "<div id=\"wrapper\"><div id=\"contentfull\"><div class=\"category\">\n";
  echo $msg;

// -------------------------------------------------------------------------
} elseif (isset($_GET['pwchange'])) {

  forceSlogin();

  if (!isset($user)) {
    echo '<p>Sorry, not logged in.</p>';
  } else {
    outputHeader ($user);
	  echo "<div id=\"wrapper\"><div id=\"contentfull\"><div class=\"category\">\n";
?>
	<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST">
  <fieldset>
  <legend>Change Password</legend>

	<p>Please enter your old password:<br />
	<input type="password" name="old_passwd" size="16" maxlength="32" /></p>

	<p>Please enter a new password:<br />
	<input type="password" name="new_passwd1" size="16" maxlength="32" /></p>

	<p>Please enter the new password again:<br />
	<input type="password" name="new_passwd2" size="16" maxlength="32" /></p>

  <p><input type="submit" name="do_pwchange" value="Change Password" />&nbsp;&nbsp;
	<input type="submit" name="cancel" value="Cancel" /></p>
  </fieldset>
  </form>
<?php
  }

// -------------------------------------------------------------------------
} elseif (isset($_REQUEST['cancel'])) {

  //header('Location: '. otherUrl('prefs.php'));

  $meta = '<meta http-equiv="refresh" content="0;url='. otherUrl('prefs.php') .'" />';
  outputHeader(isset($user) ? $user : NULL, 'redirect', NULL, NULL, $meta);
  echo "<div id=\"wrapper\"><div id=\"contentfull\"><div class=\"category\">\n";
  echo '<p>Redirecting back to <a href="'.
    otherUrl('prefs.php') .'">preferences</a>.</p>';

// -------------------------------------------------------------------------
// Present Log In form
//
} else {

	forceSlogin();

	outputHeader();
	echo "<div id=\"wrapper\"><div id=\"contentfull\"><div class=\"category\">\n";
?>
	<form action="<?php echo $_SERVER['PHP_SELF'] ?>" name="login" method="POST">
		<fieldset>
			<legend>Log In</legend>

			<p>Please enter your userid:<br />
			<input type="text" name="userid_entered" size="16" maxlength="32" /></p>

			<p>Please enter your password:<br />
			<input type="password" name="passwd_entered" size="16" maxlength="32" /></p>

			<p><span title="Save login cookie for 20 days"><input type="checkbox" name="remember_me" value="1" /> Remember me</span></p>

<?php
  if (isset($_GET['redirect'])) {
    echo '<input type="hidden" name="redirect" value="'. htmlspecialchars($_GET['redirect'], ENT_NOQUOTES, 'UTF-8') .'" />';
  }
?>
			<input type="hidden" name="js_supported" value="0" /></p>

			<p><input type="submit" name="auth_user" value="Login" onclick="document.login.js_supported.value='1'"/></p>
		</fieldset>
	</form>

	<p>If you do not have an account, you may <a href="add_user.php">create one</a> if the administrator allows it.</p>

<?php
}
echo "</div></div></div>";    // container, wrapper, category
include ('./foot.php');
?>
