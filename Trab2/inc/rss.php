<?php

//require_once('../magpierss/rss_fetch.inc');
//require_once('../magpierss/rss_utils.inc');

//----------------------------------------------------------------------------
function readRssFeeds (&$db, &$user, &$category)
{
  foreach (feed::all($db, $user->userid,
    ($category ? "WHERE cat_id='$category->id' " : '') .'ORDER BY name') as $feed)
  {
      readRssFeed ($db, $user, $feed);
  }
}

//----------------------------------------------------------------------------
// Only fetch the RSS feed so often (it may also be cached by Magpie)
//
function updateRssFeed (&$user, &$feed)
{
  $ts = strtotime($feed->last_update);
  return !$ts || ($ts == -1) || ((time() - $ts) > $user->timeout);
}

//----------------------------------------------------------------------------
// Return errors, warnings
function readRssFeed (&$db, &$user, &$feed, $force = FALSE)
{
  global $DEBUG;
  $errStr = '';
  $warnStr = '';
  $goodInserts = 0;

  if ($force || updateRssFeed ($user, $feed))
  {
    if ($DEBUG) { echo '<!-- fetching sources for '. $feed->rss_link ." -->\n"; }

    if (!$DEBUG) error_reporting(E_USER_ERROR);  // suppress noisy magpie warnings
    $rss = fetch_rss($feed->rss_link);

    if ($rss)
    {
      if (empty($feed->image_link) && $user->show_images && $rss->image)
        saveFeedImage ($db, $user, $feed, $rss);

      $items = $rss->items;

      if ($DEBUG) { echo '<!-- got '. count($items) .' items total '
        . (isset($rss->from_cache) ? '(from cache)' : '') .' -->'; }

      // Notify the user if the feed link (seems to have) changed
      if (isset($rss->movedToUrl)) {
        $errStr .= ' Feed has <a href="'
          . htmlspecialchars($rss->movedToUrl, ENT_COMPAT, 'UTF-8')
          .'">moved</a>. To update it, <a href="add.php?edit='. $feed->id .'&amp;url='
          . urlencode($rss->movedToUrl) .'">click here</a> and save changes.';
      } else if (isset($rss->redirectedToUrl)) {
        $warnStr .= ' Feed was <a href="'
          . htmlspecialchars($rss->redirectedToUrl, ENT_COMPAT, 'UTF-8')
          .'">redirected</a>. If you want to update it, <a href="add.php?edit='
          .$feed->id .'&amp;url='. urlencode($rss->redirectedToUrl)
          .'">click here</a> and save changes.';
      }

      // Feeds with more articles than max_links will cause a disappear/reappear
      // behavior that is annoying.  Only insert up to max_links items, ignoring the
      // oldest articles. [We assume they are listed newest -> oldest.]
      if ($feed->max_links > 0 && count($rss->items) > $feed->max_links) {
        if ($DEBUG) { echo '<!-- ignoring '. (count($rss->items)-$feed->max_links) .' old items -->'; }
        $items = array_slice($rss->items, 0, $feed->max_links);
      }

      // Reverse the array so that we add on oldest first; this preserves the
      // assumption that newer DB ids are newer articles.
      for ($i = count($items) - 1; $i >= 0; $i--) {

        $link = linkFromItem ($items[$i], $feed->id);

        if ($link) {
          // Use no transaction here--this is expected to fail if already have item
          if ($link->insert($db, $user->userid)) {
            $goodInserts++;
            if ($DEBUG > 1) { echo "<!-- inserted link {$link->id} -->\n"; }
          } else {
            if ($DEBUG) { echo '<!-- '. $db->error() ." -->\n"; } // 'Duplicate entry' is normal
          }
        }
      }

      // Keep count of how many new articles have been fetched and saved.
      // Feed will be updated below, when the timestamp changes.
      //  feed::inc_stat ($db, $user->userid, $feed->id, 'total', $goodInserts);
      $feed->stat_total += $goodInserts;

      if ($rss->WARNING)
        $warnStr .= ' '. $rss->WARNING;
    }
    else
    {
      $errStr .= ' Error fetching feed: '. magpie_error();
    }

    // now we nuke the old rows
    if ($feed->max_links > 0) {
      $links = feedlink::all ($db, $user->userid, "WHERE feed_id='{$feed->id}' ORDER BY pubdate DESC, id DESC LIMIT {$feed->max_links},1");
      if (count($links) > 0) {
        $cutRow = $links[0]->id;

        if ($DEBUG) { echo "<!-- cut row is $cutRow -->\n"; }
        
        // XXX move sql to feedlink class
        if (!$db->query('DELETE FROM '. DB_PREFIX.$user->userid ."_links WHERE id < $cutRow AND feed_id='{$feed->id}' AND state<>'".feedlink::STATE_STARRED()."'")) {
          if ($DEBUG) { echo "<!-- delete old links failed -->\n"; }
        }
      }
    }

    $now = date('Y-m-d H:i:s');
    if ($goodInserts > 0) {
      if ($DEBUG) { echo "<!-- updating last_add -->\n"; }
      $feed->last_add = $now;  // date the feed was really changed
    }

    // freshen the last_update time, and any other fields that have changed above
    $feed->last_update = $now;
    if (!$feed->update ($db, $user->userid)) {
      if ($DEBUG) { echo '<!-- last_update update failed -->'; }
    }
  }
  return array($goodInserts, $errStr, $warnStr);
}

//----------------------------------------------------------------------------
function linkFromItem (&$item, $feedid)
{
  // Pull the date
  if (isset($item['date_timestamp'])) {
    $pubd = date("Y-m-d H:i:s", $item['date_timestamp']);
  } else if (isset($item['pubdate'])) {
    $pubd = date("Y-m-d H:i:s", strtotime($item['pubdate']));
  }
  if (empty($pubd) || !strncmp('1970-01-01', $pubd, 10))
    $pubd = date("Y-m-d H:i:s", time());

  // use guid field if link is empty
  if (!isset($item['link']) || empty($item['link'])) {
    $item['link'] = $item['guid'];
  }

  if (!preg_match('/^(https?|ftp|mailto):\/\//', $item['link']))  // basic validation
    return null;

  // Favor atom_content, since it often has the entire contents
  if (isset($item['atom_content']) && (strlen(trim($item['atom_content'])) > 2)) {
    $item['description'] = $item['atom_content'];
  } else if (!isset($item['description'])) {
    if (isset($item['summary']))
      $item['description'] = $item['summary'];
    else
      $item['description'] = '';
  }

  // Make up a title if one doesn't exist
  if (!isset($item['title']) || empty($item['title'])) {
    $item['title'] = substr ($item['description'], 0, 50) .'...';
  }

  // XXX this should be in a new db field
  $meta = '';
  if (defined('ADD_META_INFO') && ADD_META_INFO) {

    // If there is an author, make a note at the end
    if (isset($item['author_name']) && strlen(trim($item['author_name'])) > 1)
      $author = $item['author_name'];
    else if (isset($item['author']) && strlen(trim($item['author'])) > 1)
      $author = $item['author'];
    if (isset($author))
      $meta .= " [Author: $author]";

    // If there are categories, make a note at the end
    if (isset($item['category']) && strlen(trim($item['category'])) > 1) {
      $meta .= " [Category: {$item['category']}]";
    }

    // If there is an enclosure, append a link to it in the description
    if (isset($item['enclosure']) && isset($item['enclosure']['url'])) {
      $meta .= ' [<a href="'. $item['enclosure']['url'] .'"';
      if (isset($item['enclosure']['type'])) {
        $meta .= ' title="'. $item['enclosure']['type'];
        if (isset($item['enclosure']['length']))
          $meta .= ' '. intval($item['enclosure']['length'] / 1024) .' KB';
        $meta .= '"';
      }
      $meta .= '>Link to media</a>]';
    }

    if ($meta)
      $meta = '<p class="meta">'. strip_tags_xss (XSS_TAGS_STRUCT|XSS_TAGS_FORMAT, $meta) .'</p>';
  }

  $link = new feedlink();
  $link->feed_id = $feedid;
  $link->link = $item['link'];
  $link->title = strip_tags_xss (XSS_TAGS_NONE, $item['title']);
  $link->description = trim(strip_tags_xss (XSS_TAGS, $item['description'])) . $meta;
  $link->state = feedlink::STATE_NEW();
  $link->pubdate = $pubd;
  $link->guid = md5($link->title . $link->link);

  return $link;
}

//------------------
// Purge the cached file for this url.  This uses magpie internals, as it
// should really be provided there.
//
function purge_rss ($url) {
  init();

  if ( !isset($url) )
    return false;

  if ( !MAGPIE_CACHE_ON )
    return false;

  $cache = new RSSCache( MAGPIE_CACHE_DIR, MAGPIE_CACHE_AGE );

  if ($cache->ERROR)
    return false;

  $cache_key = $url . MAGPIE_OUTPUT_ENCODING;
  $filename = $cache->file_name($cache_key);

  if (file_exists($filename)) {
    unlink($filename);
  }

  return true;
}

//----------------------------------------------------------------------------
function saveFeedImage (&$db, &$user, &$feed, &$rss)
{
  global $DEBUG;

  if ($rss->image) {
    if (FEED_IMG_CACHE) {

      $imgUrl = $rss->image['url'];

      if (preg_match ('/\.(gif|jpg|png)$/', strtolower($imgUrl)) == 0) {
        if ($DEBUG) echo "\n<!-- ignoring weird $imgUrl -->\n";
        return;
      }

      if (($imgFile = fopen ($imgUrl, 'rb')) === FALSE) {
        if ($DEBUG) echo "\n<!-- error opening $imgUrl -->\n";
        return;
      }

      $outFileName = FEED_IMG_FOLDER .'/'. $user->userid . $feed->id . trim(strrchr($imgUrl, '.'));
      if (($outFile = fopen ($outFileName, 'wb')) === FALSE) {
        if ($DEBUG) echo "\n<!-- error opening $outFileName -->\n";
        return;
      }

      while (!feof($imgFile)) {
        $chunk = fread ($imgFile, 1024);
        fwrite ($outFile, $chunk);
      }

      fclose ($imgFile);
      fclose ($outFile);

      resizeFeedImage ($outFileName);

    } else {
      $outFileName = $rss->image['url'];
    }

    $feed->image_link = $outFileName;
    if (!$feed->update ($db, $user->userid))
      if ($DEBUG) echo "\n<!-- error writing feed $feed->id -->\n";

    if ($DEBUG) echo "\n<!-- done saving feed image for $feed->id -->\n";
  }
}

//----------------------------------------------------------------------------
function resizeFeedImage ($filename)
{
  global $DEBUG;

  if (FEED_IMG_CACHE && FEED_IMG_HEIGHT > 0) {
    list($width, $height, $type) = getImageSize($filename);

    if ($height > FEED_IMG_HEIGHT) {
      $scale = FEED_IMG_HEIGHT / $height;
      $newHeight = $height * $scale;
      $newWidth = $width * $scale;

      if ($DEBUG) echo "\n<!-- resizing to $newWidth x $newHeight -->\n";

      switch ($type) {
        case IMAGETYPE_JPEG:
          $origImg = function_exists('imageCreateFromJpeg')
            ? @imageCreateFromJpeg ($filename) : null;
          break;
        case IMAGETYPE_GIF:
          $origImg = function_exists('imageCreateFromGif')
            ? @imageCreateFromGif ($filename) : null;
          break;
        case IMAGETYPE_PNG:
          $origImg = function_exists('imageCreateFromPng')
            ? @imageCreateFromPng ($filename) : null;
          break;
        default:
          if ($DEBUG) echo "\n<!-- unknown img $type -->\n";
          return;
      }

      if (!$origImg) {
        if ($DEBUG) echo "\n<!-- no imageCreate fn for $type -->\n";
        return;
      }

      if (!($newImg = imageCreateTrueColor ($newWidth, $newHeight))) {
        if ($DEBUG) echo "\n<!-- imageCreateTrueColor failed -->\n";
        return;
      }

      if (!imageCopyResampled ($newImg, $origImg, 0, 0, 0, 0,
          $newWidth, $newHeight, $width, $height)) {
        if ($DEBUG) echo "\n<!-- copy resampled failed -->\n";
        return;
      }

      switch ($type) {
        case IMAGETYPE_JPEG:
          $rc = imageJpeg ($newImg, $filename);
          break;
        case IMAGETYPE_GIF:
          $rc = imageGif ($newImg, $filename);
          break;
        case IMAGETYPE_PNG:
          $rc = imagePng ($newImg, $filename);
          break;
        default:
          $rc = FALSE;
          return;
      }

      if (!$rc && $DEBUG) echo "\n<!-- imageFoo failed -->\n";
    }
  }
}

