<?php

//-------------------------------------------------------------------------
// Accepts well-formed OPML, as well as Rojo broken output, and Flicker feeds.
//
function importOpml ($db, $user, $opml)
{
  $out = '';
  $cats = array();
  $lastCatId = -1;
  $lastCatName = '';

  $xml_parser = xml_parser_create();
  if (xml_parse_into_struct ($xml_parser, $opml, $values, $tags))
  {
    // Read categories so we can lookup their IDs
    foreach (category::all($db, $user->userid) as $cat)
      $cats[htmlspecialchars($cat->name, ENT_QUOTES, 'UTF-8')] = $cat->id;

    // Read each outline element and create a feed
    foreach ($tags as $key => $val)
    {
      if ($key === 'OUTLINE')
      {
        foreach ($val as $outline)
        {
          $el = $values[$outline];

          if (isset($el['attributes']) &&
            (array_key_exists ('TITLE', $el['attributes']) ||
             array_key_exists ('TEXT', $el['attributes'])))
          {
            if (array_key_exists ('XMLURL', $el['attributes']))
            {
              // It's a feed if it has a URL
              $feed = new feed();
              $feed->name = array_key_exists ('TITLE', $el['attributes']) ?
                $el['attributes']['TITLE'] : $el['attributes']['TEXT'];
              if (array_key_exists ('HTMLURL', $el['attributes'])) {
                $feed->main_link = $el['attributes']['HTMLURL'];
              }
              $feed->rss_link = $el['attributes']['XMLURL'];
              $feed->headlines = $user->headlines;

              $catout = '';
              if (array_key_exists ('CATEGORY', $el['attributes']))
              {
                $lastCatId = -1;
                $catname = $el['attributes']['CATEGORY'];
                if (array_key_exists ($catname, $cats)) {
                  // already have the category, use it
                  $feed->cat_id = $cats[$catname];
                  $catout = " in category <a href=\"index.php?category=$feed->cat_id\"><i>$catname</i></a>";
                } else {
                  $cat = new category();
                  $cat->name = $catname;
                  if ($cat->insert ($db, $user->userid)) {
                    $cats[$catname] = $cat->id;   // add to cache
                    $feed->cat_id = $cat->id;
                    $catout = " in new category <a href=\"index.php?category=$feed->cat_id\"><i>$catname</i></a>";
                  } else {
                    $feed->cat_id = $user->default_cat;
                    $catout = ' in default category';
                  }
                }
              }
              else if ($lastCatId != -1)
              {
                $feed->cat_id = $lastCatId;
                $catout = " in category <a href=\"index.php?category=$feed->cat_id\"><i>$lastCatName</i></a>";
              }

              if ($feed->insert ($db, $user->userid)) {
                $out .= "<p>Added <a href=\"index.php?more=$feed->id\"><b>".htmlspecialchars($feed->name, ENT_NOQUOTES, 'UTF-8')."</b></a>$catout.</p>";
              } else {
                $out .= '<p class="error">Failed to add "'. htmlspecialchars($feed->name, ENT_NOQUOTES, 'UTF-8') .'": '. $db->error() . '</p>';
              }
            }
            else
            {
              // No URL, but has a title.  Treat as a category.
              if (array_key_exists ('CATEGORY', $el['attributes'])) {
                $catname = $el['attributes']['CATEGORY'];
              } else {
                $catname = $el['attributes']['TITLE'];
              }

              $valid = TRUE;
              if (!array_key_exists ($catname, $cats))
              {
                $cat = new category();
                $cat->name = $catname;
                if ($cat->insert ($db, $user->userid)) {
                  $cats[$catname] = $cat->id;   // add to cache
                } else {
                  $out .= '<p class="error">Failed to add "'. $catname .'": '. $db->error() . '</p>';
                  $valid = FALSE;
                }
              }

              if ($valid) {
                $lastCatId = $cats[$catname];
                $lastCatName = $catname;
              }
            }
          }
          else
          {
            $out .= '<!-- ignoring malformed element -->';
          }
        }
      }
    }
  }
  else
  {
    $out .= '<p class="error">XML error ('.
      xml_error_string (xml_get_error_code($xml_parser)) .') at line '.
      xml_get_current_line_number ($xml_parser) .'.</p>';
  }

  xml_parser_free ($xml_parser);
  return $out;
}

//-------------------------------------------------------------------------
function exportOpml ($db, $user, $selCat = NULL) {
  $now = date('r');
  $out = '<'.'?xml version="1.0" encoding="UTF-8" ?'.">\n";
  $out .= <<<END
<opml version="1.0">
  <head>
    <title>{$user->name}'s Rnews feeds</title>
    <dateCreated>{$now}</dateCreated>
    <ownerName>{$user->name}</ownerName>
  </head>
  <body>

END;

  // Save the category names for lookup by id
  foreach (category::all($db, $user->userid) as $cat) {
    $cats[$cat->id] = htmlspecialchars($cat->name, ENT_QUOTES, 'UTF-8');
  }

  // Now read the feeds and output
  $filter = ($selCat == NULL) ? ' ORDER BY cat_id' : (' WHERE cat_id='.$selCat->id);

  foreach (feed::all($db, $user->userid, $filter) as $feed) {
    $out .= '    <outline version="RSS" text="'. htmlspecialchars($feed->name, ENT_QUOTES, 'UTF-8')
      .'" title="'. htmlspecialchars($feed->name, ENT_QUOTES, 'UTF-8')
      .'" xmlUrl="'. htmlspecialchars($feed->rss_link, ENT_QUOTES, 'UTF-8')
      .'" htmlUrl="' . htmlspecialchars($feed->main_link, ENT_QUOTES, 'UTF-8')
      .'" category="' . $cats[$feed->cat_id]
      .'" type="rss" />'."\n";
  }

  $out .= "  </body>\n</opml>";

  return $out;
}

//-------------------------------------------------------------------------
function getPackages($path)
{
  $packs = array();

  if (is_dir($path)) {
    if ($h = @opendir ($path)) {
      while (($file = readdir($h)) !== false) {
        if (preg_match('/\.(opml|xml)$/i', $file)) {
          if (($contents = @file_get_contents($path.'/'.$file)) !== FALSE)
            if (preg_match("/<title>(.*)<\/title>/i", $contents, $matches))
              $packs[$matches[1]] = $path.'/'.$file;
        }
      }
      closedir($h);
    }
  }

  return $packs;
}

