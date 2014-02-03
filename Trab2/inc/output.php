<?php

require_once('./inc/rss.php');

// -------------------------------------------------------------------------------
function outputHeader ($user = NULL, $t = NULL, $status = NULL,
  $onload = 'rnewsInit({})', $meta = NULL, $js = NULL, $getArgs = NULL) {

  $title = defined('RNEWS_TITLE') ? RNEWS_TITLE : '';
  if (!empty($t)) { $title .= ": $t"; }

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <meta http-equiv="Content-type" content="text/html; charset=UTF-8" />
<?php if ($meta) echo $meta ?>
    <link rel="shortcut icon" href="favicon.ico" />
    <link rel="stylesheet" href="rnews-side.css" type="text/css" />
<!--[if IE 6]>
    <link rel="stylesheet" href="rnews-ie6.css" type="text/css" />
<![endif]-->
<?php if (defined('JS_COMPRESS') && JS_COMPRESS): ?>
    <script type="text/javascript" src="all.js"></script>
<?php else: ?>
    <script type="text/javascript" src="ajax.js"></script>
    <script type="text/javascript" src="functions.js"></script>
    <script type="text/javascript" src="rollover.js"></script>
<?php endif; ?>
<?php if ($js): ?>
    <script type="text/javascript">
      <!--
<?php echo $js ?>
      // -->
    </script>
<?php endif; ?>
    <title><?php echo 'Rnews '.$title ?></title>
  </head>
  <body<?php if ($onload) echo " onload=\"$onload\"" ?>>
  <div id="container">
  <div id="header">
    <a href="index.php"><img id="logo" src="img/rnews-logo.png" alt="Rnews" /></a>
<?php
  echo '<h1><a href="index.php">'.
    (defined('RNEWS_TITLE') ? RNEWS_TITLE : 'Feed Aggregator') .'</a></h1>';
?>
    <?php outputUserActions($user) ?>
    <?php if ($user !== NULL) outputSearchBox($getArgs) ?>
    <div id="status"><?php echo $status ? $status : '&nbsp;' ?></div>
  </div>
<?php

}

// -------------------------------------------------------------------------------
function outputUserActions(&$user) {
  echo '<div class="actions">';
  if ($user !== NULL && !$user->restricted) {
    echo htmlspecialchars($user->name, ENT_NOQUOTES, 'UTF-8') .': ';
    echo '<a href="prefs.php">Preferences</a>&nbsp;|&nbsp; ';
    echo '<a href="auth.php?logout=1">Log Out</a>';
  } else {
    echo '<a href="auth.php">Log In</a>';
  }
  echo '</div>';
}

// -------------------------------------------------------------------------------
function outputSearchBox($getArgs = NULL) {
  $args = '';
  if ($getArgs) {
    if (isset($getArgs['more']))
      $args = '?more='. intval($getArgs['more']);
    elseif (isset($getArgs['category']))
      $args = '?category='. intval($getArgs['category']);
  }
  $args = ($args ? "$args&amp;" : '?') .'filter=N&amp;nopref=1';
?>
  <div id="search">
    <form action="search.php<?php echo $args ?>" name="search" method="post">
      <input type="text" name="q" size="12" maxlength="30" tabindex="1" class="text" />
      <input type="submit" name="search" value="Search" tabindex="2" class="button" />
    </form>
    <span><a href="search.php">Advanced</a></span>
  </div>
<?php
}

// -------------------------------------------------------------------------------
// This provides category links in the header
//
function outputNavigation (&$db, &$user, $selCatId = 0) {

  echo '<div id="navigation"><ul>';
  echo '<li><a href="index.php?all=1"><em>All Sources</em></a></li>';
  echo '<li class="sep"><a href="search.php?q=&star=1&view=L&filter=N&nopref=1"><em>All Favorites</em></a></li>';

	foreach (category::all($db, $user->userid, 'ORDER BY name') as $cat) {
		echo '<li'. (($selCatId && ($selCatId == $cat->id)) ? ' class="selected"' : '')
			.'><a href="index.php?category='. $cat->id .'">'. htmlspecialchars($cat->name, ENT_NOQUOTES, 'UTF-8') .'</a></li>';
	}

  echo '</ul></div>' . "\n";
}

// -------------------------------------------------------------------------------
function outputCatHead (&$user, &$cat, $args, $viewModes) {
  echo "\n<div class=\"category\">\n";
  echo '<div class="categoryHead clearfix">';
  echo "<h2><a href=\"index.php?category={$cat->id}\">". htmlspecialchars($cat->name, ENT_NOQUOTES, 'UTF-8') ."</a></h2>\n";

  echo '<div class="actions">';

  if ($viewModes) {
    echo viewlinkHTML ($user->prefs, $args);
    echo sortlinkHTML ($user->keep_stats, $user->prefs, $args);
    echo filterlinkHTML ($user->prefs, $args);
  }

  if (!$user->restricted) {
    // Delete
    echo '<a href="prefs.php?deleteCat=y"><img src="img/b2-x_nm.png" alt="delete category" title="delete category" /></a>';

    // Edit
    echo '<a href="add.php?editCat='. $cat->id .'"><img src="img/b2-edit_nm.png" alt="edit category" title="edit category" /></a> &nbsp;&nbsp;';
 
    // Add
    echo '<a href="add.php'. argsToUrl(array('new'=>'y','category'=>$cat->id)) .'"><img src="img/b1-add_nm.png" alt="add a feed" title="add a feed" /></a>';
  }

  // Mark
  unset($args['q']);
  unset($args['contents']);
  $args['seen'] = 'cat';
  $args['category'] = $cat->id;
  echo '<a href="index.php'. argsToUrl($args) .'" onclick="$(\'actionform\').submit();return false;">';
  echo '<img src="img/b1-markall_nm.png" alt="mark category" title="mark category as read" /></a>';
  echo argsToForm('actionform',$args);
  echo "</div></div>\n";
}

// -------------------------------------------------------------------------------
// Output feed header
// -------------------------------------------------------------------------------
function outputFeedhead(&$user, &$feed, $getArgs, $isSearch)
{
  unset($getArgs['q']);
  unset($getArgs['contents']);

  echo '<div class="feedhead clearfix">';
 
  $name = htmlspecialchars($feed->name, ENT_COMPAT, 'UTF-8');
  if ($user->show_images) {
    $image = empty($feed->image_link) ? DEFAULT_IMAGE : $feed->image_link;
    echo '<a href="'. $feed->main_link .'"><img class="feedimg" src="'. $image
      . '" alt="'. $name .'" title="'. $name .'" /></a>';
  }

  echo '<div class="actions">';

  if (!$user->restricted) {
    // Delete
    echo '<a href="prefs.php?delete_feed=' . $feed->id . '"><img src="img/b2-x_nm.png" alt="delete" title="delete feed" /></a>';

    // Edit
    echo '<a href="add.php?edit=' . $feed->id . '"><img src="img/b2-edit_nm.png" alt="edit" title="edit feed" /></a>';

    // Force update
    $updArgs = $getArgs;
    $updArgs['update'] = $feed->id;
    unset($updArgs['seen']);
    echo '<a href="index.php'. argsToUrl($updArgs) .'" title="force update of feed" '
      . (isset($getArgs['more']) ? '' : 'onclick="update(\''.$feed->id.'\');return false;"') .'>';
    echo '<img src="img/b2-update_nm.png" alt="update" title="force update of feed" /></a>&nbsp;&nbsp;';
  }

  // It's confusing to allow marking whole feed when only search results are shown
  if (!$isSearch) {
    // Mark
    $getArgs['seen'] = $feed->id;
    echo '<a href="index.php'. argsToUrl($getArgs);
    echo '" onclick="markFeed(\''.$feed->id.'\');return false;" title="mark feed as read">';
    echo '<img src="img/b2-mark_nm.png" alt="mark" title="mark feed as read" /></a>';
  }

  echo '</div>';    // actions

  echo '<div class="feedtitle">';
  //echo '<a href="index.php?more='. $feed->id .'" title="see all articles in this feed">'. $name .'</a>';
  echo "<a href=\"{$feed->main_link}\">{$name}</a>";
  //echo '<a href="'. $feed->main_link .'"><img src="img/feed-link_nm.png" alt="'.$name.'" title="'.$name.'"/></a>';
  echo '</div>';

  echo "</div>\n "; // feedhead
}

//----------------------------------------------------------------------------
function outputOneFeed (&$db, &$user, &$feed, $getArgs, $search = NULL)
{
  $snippets = ($user->snippets) ? SNIPPET_LONG : 0;

  echo '<div class="feedwide">';
  outputFeedhead ($user, $feed, $getArgs, $search != NULL);

  // Update the RSS links, if it's time.
  if (!$search) {
    list($numnew, $err, $warn) = readRssFeed ($db, $user, $feed);
    if ($err != '')
      echo '<p class="error">'.$err.'</p>';
    if ($warn != '')
      echo '<p class="warn">'.$warn.'</p>';
  }

  echo '<div id="feed'. $feed->id .'" class="feedcontent clearfix"><dl>';

  $emptyStr = $search ? 'No articles matched.' : 'No articles available.';
  $filter = "WHERE state<>'".feedlink::STATE_DELETED()
    ."' AND feed_id='{$feed->id}'". ($search ? " AND $search" : '')
    .' ORDER BY pubdate DESC, id DESC';

  $links = feedlink::all($db, $user->userid, $filter);
  if (count($links) > 0) {
    foreach ($links as $link) {
      if ($user->expand_one) {
        echo '<dt id="L'. $link->id .'" class="feedlink">'.
          feedlinkHTML ($feed->id, $link, FALSE, $user->new_window, FALSE) ."</dt>\n";
        echo '<dd>'. ajaxExpandHTML($user, $link) ."</dd>\n";
      } else {
        echo '<dt id="L'. $link->id .'" class="feedlink'.($snippets?' ell':'').'">'.
          feedlinkHTML ($feed->id, $link, TRUE, $user->new_window, $snippets) ."</dt>\n";
      }
    }
  } else {
    echo '<dt class="seen">&mdash; '. $emptyStr .'</dt>';
  }

  echo '</dl><p id="info'.$feed->id.'" class="info">'
    . feedInfoHTML ($feed, $user->keep_stats) .'</p>';
  echo "</div></div>\n"; // feed, feedwide

  // Link to the next feed in this category.  Simple sort by id.
  if (!$search) {
    $which = 'Next';
    $sql = 'SELECT * FROM '. DB_PREFIX.$user->userid ."_feeds WHERE cat_id='{$feed->cat_id}' AND id>'{$feed->id}' LIMIT 1";
    $results = $db->query($sql);
    if ($results === FALSE || mysql_num_rows($results) <= 0) {
      $which = 'First';
      $sql = 'SELECT * FROM '. DB_PREFIX.$user->userid ."_feeds WHERE cat_id='{$feed->cat_id}' ORDER BY id LIMIT 1";
      $results = $db->query($sql);
    }
    if ($results !== FALSE && mysql_num_rows($results) > 0) {
      $nextFeed = new feed(mysql_fetch_assoc($results));
      $args = $getArgs;  // copy
      $args['more'] = $nextFeed->id;
      $url = $_SERVER['PHP_SELF'].argsToUrl($args);
      echo '<div class="feedwide">'
        .' <div class="feedcontent closetop">'
        .'  <dl><dt class="seen">'.$which.' feed in category: '
        .'<a href="'.$url.'">'. $nextFeed->name .'</a></dt></dl>'
        .' </div>'
        .'</div>';
    }
  }

  //echo "<script type=\"text/javascript\">rnewsInitSnippet();initFeed({$feed->id},true);ellFeed({$feed->id})</script>";
}

//----------------------------------------------------------------------------
// This is quite complicated because it handles:
//  - printing all or a single category
//  - printing block, wide-block, and list views
//  - printing search results
//  - allowing async update after page has loaded
//
function outputFeeds (&$db, &$user, &$category, $getArgs, $search = NULL)
{
  global $DEBUG;

  $blockView = !isset($user->prefs['V']) || $user->prefs['V'] != 'L'; // either B or W
  $blockViewWide = isset($user->prefs['V']) && $user->prefs['V'] == 'W'; // only W
  $listView = !$blockView;
  $useAsync = (AJAX_LOAD && $blockView &&
    (isset($user->prefs['J']) && $user->prefs['J'] != '0') &&  // js_supp on login
    (!isset($user->prefs['A']) || $user->prefs['A'] != '0'));  // async=0
    //(!isset($user->prefs['F']) || $user->prefs['F'] != 'N'));  // filter=N

  $cat = NULL;
  $numInPair = 0;       // detect feedpairs
  $feedsSkipped = 0;    // for filter new
  $catStarted = FALSE;  // category started?
  $artOutput = FALSE;   // any article shown?
  $someOutput = FALSE;  // anything at all shown?
  $jsOutput = '';
  $snippets = 0;        // length of descr snippets to show
  if ($user->snippets)
    $snippets = ($blockViewWide || $listView) ? SNIPPET_LONG : SNIPPET_SHORT;

  $emptyStr = $search ? 'No articles matched.' : 'No new articles available.';
  $flushOut = (!$search && !$useAsync);

  $catFilter = $category ? " WHERE cat_id='$category->id'" : '';
  $catFilter .= ' ORDER BY cat_id';
  if ($user->keep_stats &&
      (!isset($user->prefs['S']) || $user->prefs['S'] == 'S'))
    $catFilter .= ', (stat_expand+stat_click)/stat_total DESC';
  $catFilter .= ', name';

  $feeds = feed::all($db, $user->userid, $catFilter);
  $numFeeds = count($feeds);

  if ($numFeeds > 0)
  {
    if ($flushOut) { @ob_flush(); @flush(); }  // Give feedback to the user

    foreach ($feeds as $feed)
    {
      $numFeeds--;
      $timeToUpdate = updateRssFeed ($user, $feed);
      $loadAsync = ($useAsync && !$search && $timeToUpdate);

      if (!$search && !$loadAsync)
        list($numnew, $err,$warn) = readRssFeed ($db, $user, $feed);  // FETCH IT NOW
      else
        $err = $warn = '';

      $links = array();
      if ($loadAsync) {
        $artOutput = TRUE;   // dont show any articles now, will load async
      } else {
        $artFilter = "WHERE feed_id='{$feed->id}' AND state<>'".feedlink::STATE_DELETED()."'";
        if ($search)  // Only simple keyword search is supported
          $artFilter .= " AND $search ORDER BY pubdate DESC, id DESC";
        else
          $artFilter .= " ORDER BY pubdate DESC, id DESC LIMIT ". ($feed->headlines + 1);

        $links = feedlink::all ($db, $user->userid, $artFilter);
      }

      // Show up to LAST unread of the first feed.headlines articles.
      list ($ntoshow, $moreexist, $moreseen) = filterArtGroup ($links, $feed->headlines, !$search);

      if ($DEBUG>=2) { echo "<!-- got ".count($links)." links, show {$ntoshow}: "; foreach ($links as $l) echo "{$l->id}:{$l->state}, "; echo ' -->'; }


      // Filter: skip feeds unless due to update or have new articles
      if (isset($user->prefs['F']) && $user->prefs['F'] == 'N' &&
          (!$timeToUpdate || $search) && $ntoshow == 0)
      {
        $feedsSkipped++;
        continue;
      }


      // PRESENTATION LOGIC
      //
      if ($loadAsync)
        $jsOutput .= "initFeed({$feed->id},false);\n";

      // Detect first pass or change of category, output new div to contain it
      if (!$cat || $feed->cat_id != $cat->id) {
        $firstCat = ($cat == NULL);
        $cat = new category ($db, $feed->cat_id, $user->userid);
        if (!$cat->valid)
          $cat = new category();

        if (!$firstCat) {
          if ($blockView) {
            if (!$blockViewWide && $numInPair == 1) {
              echo '</div>'; //feedpair
              $numInPair = 0;
            }
          } else {
            if (!$artOutput)
              echo "<dt id=\"none{$feed->id}\" class=\"seen\">&mdash; $emptyStr</dt>";
            echo '</dl></div></div></div>'; //dl,feedcontent,feedwide,clearfix
          }
          echo "</div>\n"; //category
        }

        $someOutput = TRUE;
        $catStarted = TRUE;
        $artOutput = FALSE;
        outputCatHead ($user, $cat, $getArgs, $firstCat);

        if ($listView) {
          echo '<div class="clearfix">';
          echo '<div class="feedwide">';
          echo '<div class="feedcontent clearfix">';
          echo '<dl>';
        }
      }

      /* XXX this breaks when filtering, as the formerly wide feed is pulled up into a pair but lacks a "wide" button
      if ($blockView && $numFeeds == 0 && $numInPair == 0) // last feed can be wide
        $blockViewWide = TRUE;
       */
      

      // Block containers
      if ($blockViewWide) {
        echo "\n<div class=\"feedpair clearfix\">\n<div class=\"feedwide\">\n";
      } else if ($blockView) {
        if ($numInPair == 0)
          echo "\n<div class=\"feedpair clearfix\">\n"; // start new pair of blocks
        echo "\n<div class=\"feed\">\n";
      }

      // Feed header
      if ($blockView)
        outputFeedhead ($user, $feed, $getArgs, $search != NULL);

      if ($blockView) {
        if ($err != '')
          echo '<p class="error">'.$err.'</p>';
        if ($warn != '')
          echo '<p class="warn">'.$warn.'</p>';
      }

      // Block feed container and wide button
      if ($blockView) {
        echo '<div id="feed'.$feed->id.'" class="feedcontent clearfix">';
        if (!$blockViewWide)
          echo '<div class="wide" onclick="goWide('.$feed->id.')" title="make full width"><img src="img/morew_nm.png" alt="[wide]" title="make full width" /></div>';
        echo '<dl>';
      }


      // Headlines
      for ($i = 0; $i < $ntoshow; $i++)
        echo '<dt id="L'. $links[$i]->id .'" class="feedlink'. ($snippets?' ell':'')
          .'">'. feedlinkHTML ($feed->id, $links[$i], TRUE, $user->new_window,
            $snippets, ($blockView ? NULL : $feed->name)) ."</dt>\n";



      // None/async, more, info
      if ($blockView) {
        if ($loadAsync)
          echo '<dt id="none'. $feed->id. '" class="loading">&mdash; Loading articles...</dt>';
        else if ($ntoshow == 0)
          echo '<dt id="none'. $feed->id. '" class="seen">&mdash; '. $emptyStr .'</dt>';

        if (!$search) {
          echo '<dt id="more'. $feed->id .'" class="more">';
          if ($moreexist)
            echo morelinkHTML ($feed->id, $ntoshow, $moreseen);
          echo '</dt>';
        }

        echo "</dl>\n";
        echo '<p class="all"><a href="index.php?more='. $feed->id
          .'" title="see all articles in this feed" class="seen">see all...</a></p>';

        echo '<p id="info'.$feed->id.'" class="info">'
          . feedInfoHTML ($feed, $user->keep_stats) .'</p>';

        echo "</div>\n</div>\n"; //feedcontent,feed/wide

        $numInPair++;
        if ($blockViewWide || $numInPair > 1) {
          echo "</div>\n";  //feedpair or clearfix(wide)
          $numInPair = 0;
        }
      }

      if ($ntoshow > 0)
        $artOutput = TRUE; // for list view, filter new

      if ($flushOut) { @ob_flush(); @flush(); }  // Give feedback to the user

    } //foreach feed
    unset($feed);

    // Finish up anything that was started above
    if ($catStarted) {
      if ($blockView) {
        if (!$blockViewWide && $numInPair == 1)
          echo '</div>'; //feedpair
      } else {
        if (!$artOutput)
          echo "<dt id=\"none{$feed->id}\" class=\"seen\">&mdash; $emptyStr</dt>";
        echo "\n</dl>\n</div>\n</div>\n</div>\n"; //dl,fc,fw,cf
      }
      echo "</div>\n"; // cat
      $catStarted = FALSE;
    }
  }


  // If nothing was shown, or if feeds have been filtered and are not shown,
  // give notice to user.
  if (!$someOutput || ($feedsSkipped > 0 && !$search))
  {
    if (count($feeds) == 0)
    {
      $msg = '<a href="add.php?new=y'. ($category ?
        "&amp;category={$category->id}" : '') .'">Add a feed</a>, or import pre-packaged <a href="export.php">feed bundles</a> to get started!';
    }
    else
    {
      if ($search) {
        $msg = $emptyStr;
      } else {
        $args = $getArgs;
        $args['filter'] = 'A';
        $msg = 'Filter: not showing <span id="numSkipped">'. $feedsSkipped
          .'</span> feeds, which have no new articles.  <a href="'. $_SERVER['PHP_SELF']
          . argsToUrl($args) .'">View all feeds</a>.';
      }
    }

    if (!$someOutput)
      outputCatHead ($user, $category, $getArgs, TRUE);
    else
      echo '<div class="category">';

    echo '<div class="clearfix"><div class="feedwide"><div class="feedcontent clearfix"><dl>';
    echo "<dt class=\"seen\">&mdash; $msg</dt>";
    echo "\n</dl></div></div></div></div>\n";
  }

  if (!empty($jsOutput))
    echo '<script type="text/javascript">'. $jsOutput .'</script>';


}

