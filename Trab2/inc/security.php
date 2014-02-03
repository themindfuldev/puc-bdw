<?php

if (!function_exists('hash')) {   // PHP < 5 doesn't have hash()
  function hash ($alg, $data) {
    return $alg($data);
  }
  function hash_hmac ($alg, $data, $key, $raw=FALSE) {
    $blocksize = 16;
    if (strlen($key) > $blocksize)
      $key = pack('H*', hash($alg, $key));
    $key = str_pad($key, $blocksize, chr(0x00));
    $ipad = str_repeat(chr(0x36), $blocksize);
    $opad = str_repeat(chr(0x5c), $blocksize);
    return hash($alg,($key^$opad) . pack('H*', hash($alg,($key^$ipad) . $data)));
  }
}

// Store the userid, authenticated flag, and originating IP in the cookie, 
// integrity-protected with a keyed-hash using our secret key
// Do not use ';', ':', or '=' in prefs.
function setAuthCookie ($uid, $auth, $expiry, $prefs = array()) {
  $key = pack('H*', COOKIE_HASH_KEY);
  $data = "$uid:$auth:". $_SERVER['REMOTE_ADDR'] .':';
  foreach (array_keys($prefs) as $k)
    $data .= $k.'='.$prefs[$k].';';
  $mac = hash_hmac (CRYPTO_ALG, $data, $key);
  setcookie(COOKIE_NAME, "$data:$mac", $expiry);
}

// Format:  <uid:authflag:IP:prefs:MAC>
//
function getFieldFromCookie($field) {
  if (isset($_COOKIE[COOKIE_NAME])) {
    $key = pack('H*', COOKIE_HASH_KEY);
    $mac = substr(strrchr($_COOKIE[COOKIE_NAME], ":"), 1);   // hash is last
    $data = substr($_COOKIE[COOKIE_NAME], 0, -strlen($mac)-1);
    $cmac = hash_hmac (CRYPTO_ALG, $data, $key);             // first validate data
    if ($mac === $cmac) {
      list ($uid, $auth, $ip, $prefs) = explode(":", $data); // then split into parts
      if ($auth && (!COOKIE_LOCK_IP || $ip === $_SERVER['REMOTE_ADDR'])) {
        if ($field === 'uid') {
          return $uid;
        } elseif ($field === 'auth') {
          return $auth;
        } elseif ($field === 'ip') {
          return $ip;
        } elseif ($field === 'prefs') {     // prefs are: [key=value;] ...
          $ar = array();
          if (!empty($prefs)) {
            foreach (explode(';', $prefs) as $pair) {
              if (!empty($pair)) {
                list ($key, $value) = explode('=', $pair);
                $ar[$key] = $value;
              }
            }
          }
          return $ar;
        }
      }
    }
  }
  return NULL;
}

function getUidFromCookie() {
  return getFieldFromCookie('uid');
}

function isAuthenticCookie() {
  return getFieldFromCookie('auth') == 1;
}

function filterUserid($userid) {
  return preg_replace('/[^A-Za-z0-9]/', '', $userid);
}

function getRandomHex ($bytes) {
  $out = '';
  for ($i = 0; $i < $bytes; $i++)
    $out .= sprintf('%02x', mt_rand(0, 255));
  return $out;
}

function saltedPass ($salt, $data) {
  return hash (CRYPTO_ALG, "$salt$data");
}

function redirectToLogin() {
  $https = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] !== 'off');
  if ($https != FORCE_SLOGIN)   // if changing to/from https, use entire URL
    $red = otherUrl(basename($_SERVER['SCRIPT_NAME']), $https)
      . ($_SERVER['QUERY_STRING'] ? ('?'.$_SERVER['QUERY_STRING']) : '');
  else
    $red = $_SERVER['REQUEST_URI'];
  header('Location: '. otherUrl('auth.php?redirect='.urlencode($red), FORCE_SLOGIN));
  outputHeader();
  echo '<div id="wrapper"><div id="content"><div class="category">';
  echo '<p>You do not seem to be logged in! <a href="auth.php">Log in</a>.</p>';
  echo '</div></div></div>';
}

// Force a secure connection if so configured
function forceSlogin() {
  if (FORCE_SLOGIN &&
    (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on' || $_SERVER['HTTPS'] === 0 || $_SERVER['SERVER_PORT'] != 443)) {

    header ('Location: https://'. strip_tags($_SERVER['SERVER_NAME']) . $_SERVER['REQUEST_URI']);
    exit(0);
  }
}

function checkInsecureFilePerms() {
  clearstatcache();
  $msg = '';
  if (is_readable('install.php') || is_writable('inc/config_user.php')) {
    $msg = '<p class="error"><b>IT IS VITAL</b> that you:';
    if (is_readable('install.php'))
      $msg .= '<br />&nbsp;&mdash; Remove <i>read</i> access to <tt>install.php</tt>, so your settings are not exposed on the web';
    if (is_writable('inc/config_user.php'))
      $msg .= '<br />&nbsp;&mdash; Remove <i>write</i> access to <tt>inc/config_user.php</tt>, so your settings cannot be changed by others';
    $msg .= ' &nbsp;&nbsp;'. helplink ('security-perms') .'</p>';
  }
  return $msg;
}

function getDefaultUid() {
  return (DEFAULT_USER > 0) ? DEFAULT_USER : NULL;
}

function strip_tags_xss ($what, $s) {
  $profile = false;

  if ($what === XSS_TAGS_ANY)
    return $s;

  $allowed = array();
  if ($what & XSS_TAGS_STRUCT) {
    $allowed += array('p' => array(),
      'ul' => array(),
      'ol' => array(),
      'dl' => array(),
      'li' => array(),
      'dt' => array(),
      'dd' => array(),
      'br' => array(), 
      'hr' => array(), 
      'a' => array('href' => 1, 'title' => 1));
  }
  if ($what & XSS_TAGS_FORMAT) {
    $allowed += array('b' => array(),
      'i' => array(),
      'strong' => array(),
      'em' => array(),
      'tt' => array(),
      'big' => array(),
      'small' => array(),
      'blockquote' => array(),
      'q' => array(),
      'pre' => array(),
      'sub' => array(),
      'sup' => array(),
      'ins' => array(),
      'del' => array(),
      'kbd' => array(),
      'samp' => array(),
      'code' => array());
  }
  if ($what & XSS_TAGS_IMAGE) {
    $allowed += array('img' => array('src' => 1,
      'alt' => 1,
      'align' => 1,
      'height' => array('xminval' => 2),  # no web bugs
      'width' => array('xminval' => 2))); # no web bugs
  }
  if ($what & XSS_TAGS_TABLE) {
    $allowed += array('table' => array('border' => 1,
      'frame' => 1,
      'rules' => 1,
      'cellspacing' => 1,
      'cellpadding' => 1),
    'thead' => array(),
    'tfoot' => array(),
    'tbody' => array(),
    'caption' => array(),
    'colgroup' => array('span' => 1, 'width' => 1),
    'col' => array('span' => 1, 'width' => 1),
    'th' => array('rowspan' => 1, 'colspan' => 1),
    'td' => array('rowspan' => 1, 'colspan' => 1));
  }

  if ($profile) $t = mutime();
  require_once('./inc/kses.php');
  $s = kses ($s, $allowed);
  return $profile ? ('<!-- '. ((mutime()-$t)*1000000) .' us -->'. $s) : $s;
}

