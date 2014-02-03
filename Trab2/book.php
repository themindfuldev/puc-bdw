<?php
// This JS code is inserted into the page being viewed by the bookmarklet.
// It gets an RSS or Atom feed and hands off to add.php.
// Args:  (optional) category = catid
//
require_once('./inc/functions.php');

$cat = (isset($_REQUEST['category']) ?
  ('&category='.intval($_REQUEST['category'])) : '');
$url = otherUrl('add.php?new=y'. $cat .'&url=');

?>
e = document.getElementsByTagName('link');
for (i=0,f=0; i<e.length && !f; i++) {
  if (e[i].getAttribute('rel').indexOf('alternate')!=-1) {
    t = e[i].getAttribute('type');
    if (t.indexOf('application/rss+xml')!=-1 ||
        t.indexOf('application/atom+xml')!=-1) {
      u = e[i].getAttribute('href');
      if (u.charAt(0)=='/') {
        u = location.href+u.substring(1);
      }
      location.href = '<?php echo $url ?>'+encodeURIComponent(u)+'&from='+encodeURIComponent(location.href);
      f = 1;
    }
  }
}
if (!f) {
  alert('Sorry, no feeds found on this page!');
}
