<?php

define('RNEWS_VERSION', '1.01');

@include('./inc/config_user.php');    // optional include

if (function_exists('mb_internal_encoding'))
  mb_internal_encoding('UTF-8');

define('MAGPIE_CACHE_DIR', 'magpierss/cache');
define('MAGPIE_FETCH_TIME_OUT', 15); // 15 second timeout
define('MAGPIE_CACHE_AGE', 600);  // how long cache is valid (s)
if (isset($_SERVER['HTTP_USER_AGENT']))
  define('MAGPIE_USER_AGENT', $_SERVER['HTTP_USER_AGENT']);
define('MAGPIE_OUTPUT_ENCODING', 'UTF-8');

define('AJAX_LOAD', TRUE);        // Asynchronously load new articles?
define('AJAX_PARALLEL', 2);       // How many to load in parallel
define('AJAX_TIMEOUT', 2000);     // How long before timing out (ms)
define('JS_COMPRESS', TRUE);      // Whether to use compressed JS

define('COOKIE_NAME', 'RNAUTH');  // Key name used for cookies
define('CRYPTO_ALG', 'sha1');     // Algorithm for hashing
define('XSS_TAGS_NONE', 0);       // XSS code points
define('XSS_TAGS_STRUCT', 1);
define('XSS_TAGS_FORMAT', 2);
define('XSS_TAGS_IMAGE', 4);
define('XSS_TAGS_TABLE', 8);
define('XSS_TAGS_ANY', 15);

define('DEFAULT_IMAGE', 'img/xml.png'); // Feed image
define('DATE_FORMAT', 'n/j/y g:ia');      // PHP date() format string
define('SNIPPET_SHORT', 70);          // Length of short snippets
define('SNIPPET_LONG', 150);          // Length of long snippets
define('ADD_META_INFO', TRUE);    // Add meta-info to article descr?

define('PROFILING_ENABLED', FALSE);// Show page load times?
?>
