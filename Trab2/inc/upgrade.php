<?php

//--------------------------------------------------------------------
// Figure out 
//
function grokDBVersion(&$db) {
  // 0.72 -> 0.80 added the 'name' field to user_prefs
  if (!$db->query('SELECT name FROM '.DB_PREFIX.'user_prefs LIMIT 1'))
    return '0.72';

  // 0.80 -> 0.90 splayed links into individual tables
  if ($db->query('SELECT id FROM '.DB_PREFIX.'links LIMIT 1'))
    return '0.8x';

  // 0.92 -> 1.00 last added the 'max_links' field to _feeds
	$result = $db->query ('SELECT userid FROM '. DB_PREFIX .'user_prefs');
	while ($row = mysql_fetch_assoc($result))
	{
		$userid = $row['userid'];
		if (!$db->query('SELECT max_links FROM '.DB_PREFIX.$userid.'_feeds LIMIT 1'))
			return '0.90';
	}

  // 1.00 -> 1.01 added a guid field to _links
	$result = $db->query ('SELECT userid FROM '. DB_PREFIX .'user_prefs');
	while ($row = mysql_fetch_assoc($result))
	{
		$userid = $row['userid'];
		if (!$db->query('SELECT guid FROM '.DB_PREFIX.$userid.'_links LIMIT 1'))
			return '1.00';
	}

  return RNEWS_VERSION;
}

//--------------------------------------------------------------------
// Return whether an upgrade is needed
//
function isUpgradeNeeded ($curVer) {
  switch ($curVer) {
    case '0.72':
    case '0.80':
    case '0.81':
    case '0.8x':
    case '0.90':
    case '1.00':
      return true;
  }
  return false;
}

//--------------------------------------------------------------------
function upgradeNote ($curVer) {
  switch ($curVer) {
    case '0.72':
      return '<p><i>Sorry, I cannot automatically upgrade from 0.72.  You must export feeds to OPML and re-import into a new installation.</i></p>';

    case '0.80':
    case '0.81':
    case '0.8x':
    case '0.90':
    case '1.00':
      return '<p><i>Note: depending on how many articles you have, the upgrade may take several minutes.</i></p>';

      return '';
  }
  return '';
}

//--------------------------------------------------------------------
// Upgrade the database.  Returns array of (success, newVersion, message).
//
function upgradeDatabase (&$db, $curVer) {
  $upStr = '';

  set_time_limit(0);

  //--------------------------------------------------------------
  if ($curVer == '0.72')
  {
    return array(false, $curVer,
      'Sorry, I cannot automatically upgrade from 0.72.  You must export feeds to OPML and re-import into a new installation.');
  }

  //--------------------------------------------------------------
  if ($curVer == '0.80' || $curVer == '0.81' || $curVer == '0.8x')
  {
    // Add some flags to user_prefs
    $sql = 'ALTER TABLE '.DB_PREFIX.'user_prefs ADD ('.
      'keep_stats TINYINT(1) DEFAULT \'1\', '.
      'expand_one TINYINT(1) DEFAULT \'0\', '.
      'new_window TINYINT(1) DEFAULT \'0\', '.
      'disabled TINYINT(1) DEFAULT \'0\')';

    if (!$db->query($sql)) {
      $s = $db->error();
      return array(false, $curVer, $upStr .' Failed to alter user_prefs: '. $s .'.');
    }

    // Upgrade each users' feeds:  stat_total, stat_expand, stat_click
		$sql = 'SELECT userid FROM '.DB_PREFIX.'user_prefs';
		if (($result = $db->query($sql)) && mysql_num_rows($result) > 0)
		{
			while ($row = mysql_fetch_assoc($result)) {
				$userid = $row['userid'];

				$sql1 = 'ALTER TABLE '.DB_PREFIX.$userid.'_feeds ADD last_add DATETIME AFTER last_update';
				$sql2 = 'ALTER TABLE '.DB_PREFIX.$userid.'_feeds ADD ('.
					'stat_total INT(10) UNSIGNED DEFAULT \'1\', '.
					'stat_expand INT(10) UNSIGNED DEFAULT \'0\', '.
					'stat_click INT(10) UNSIGNED DEFAULT \'0\')';
				$sql3 = 'ALTER TABLE '.DB_PREFIX.$userid.'_feeds DROP KEY name';

				if (!$db->query($sql1) || !$db->query($sql2) || !$db->query($sql3)) {
					$s = $db->error();
					return array(false, $curVer, $upStr
						." Failed to alter user $userid's user_prefs: $s.");
				}

				if (!$db->query('UPDATE '.DB_PREFIX.$userid.'_feeds SET last_add=NOW()'))
					$upStr .= " [Warning: Failed to update {$userid}'s last_add]";
			}
		}

    // Splay links table into <userid>_links tables, and twiddle fields
    $numLinks = 0;
		$numUsers = 0;
		$sql = 'SELECT userid FROM '.DB_PREFIX.'user_prefs';
		if (($result0 = $db->query($sql)) && mysql_num_rows($result0) > 0)
		{
			while ($row0 = mysql_fetch_assoc($result0))
			{
				$userid = $row0['userid'];

				// Only do it if the userid_links does not already exist
				if (!$db->query('SELECT id FROM '.DB_PREFIX.$userid.'_links LIMIT 1'))
				{
					$numUsers++;

					if (!feedlink::create ($db, $userid))
						return array(false, $curVer, $upStr
							." Failed to create new feedlink table for $userid: ".$db->error());

					$sql = 'SELECT * FROM '. DB_PREFIX ."links WHERE src_id LIKE '{$userid}%' ORDER BY pubdate,id";
					if ($result = $db->query($sql)) 
					{
						$ul = strlen($userid);
						while ($row = mysql_fetch_assoc($result))
						{
							$f = new feedlink();
							$f->feed_id = substr($row['src_id'],$ul);
							$f->link = $row['link'];
							$f->title = $row['title'];
							$f->description = $row['description'];
							switch ($row['state'])
							{
								case 'seen': $f->state = feedlink::STATE_SEEN(); break;
								case 'new': $f->state = feedlink::STATE_NEW(); break;
								case 'visited': $f->state = feedlink::STATE_VISITED(); break;
								default: $f->state = feedlink::STATE_SEEN(); break;
							}
							$f->pubdate = $row['pubdate'];

							$numLinks++;

							if (!$f->insert ($db, $userid))
								return array(false, $curVer, $upStr
									." Failed to add new feedlink {$numLinks} for {$userid}: ".$db->error());

							$f = null;
						}

						mysql_free_result($result);
					}
				} else {
					$upStr .= " [Warning: {$userid}_links already exists--partial upgrade?]";
				}
			}
		}

		if ($numUsers > 0) {
			if (!$db->query('DROP TABLE '.DB_PREFIX.'links'))
				$upStr .= ' [Warning: failed to drop links:'.$db->error().']';
		}

    $upStr .= " Upgraded $curVer to 0.90, moved $numLinks links.";
    $curVer = '0.90';
  }

  //--------------------------------------------------------------
  if ($curVer == '0.90')
  {
    // Make database and user_prefs default to utf-8
    $sql1 = "ALTER DATABASE {$db->db} DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci";
    if (!$db->query($sql1)) {
      $s = $db->error();
      return array(false, $curVer, $upStr .' Failed to alter database to utf-8: '. $s .'.');
    }

    $sql2 = 'ALTER TABLE '.DB_PREFIX.'user_prefs CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci';
    if (!$db->query($sql2)) {
      $s = $db->error();
      return array(false, $curVer, $upStr .' Failed to alter user_prefs to utf-8: '. $s .'.');
    }

		// Add new snippets field to user_prefs if not exists
		$sql1 = 'SELECT snippets FROM '.DB_PREFIX.'user_prefs LIMIT 1';
		if (!$db->query($sql1)) {
			$sql2 = 'ALTER TABLE '.DB_PREFIX.'user_prefs ADD (snippets TINYINT(1) DEFAULT \'1\')';
			if (!$db->query($sql2)) {
				$s = $db->error();
				return array(false, $curVer, $upStr .' Failed to alter user_prefs: '. $s .'.');
			}
		} else {
			$upStr .= ' [Warning: snippets already added--partial upgrade?]';
		}

		$result = $db->query('SELECT userid,max_links FROM '.DB_PREFIX.'user_prefs');
		while ($row = mysql_fetch_assoc($result))
		{
			$userid = $row['userid'];
			$max_links = $row['max_links'];

      // Move to utf-8 for all user tables
      $sql1 = 'ALTER TABLE '.DB_PREFIX.$userid.'_cat CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci';
      $sql2 = 'ALTER TABLE '.DB_PREFIX.$userid.'_feeds CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci';
      $sql3 = 'ALTER TABLE '.DB_PREFIX.$userid.'_links CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci';

      if (!$db->query($sql1) || !$db->query($sql2) || !$db->query($sql3)) {
        $s = $db->error();
        return array(false, $curVer, $upStr
          ." Failed to alter user {$userid}'s tables to utf-8: $s.");
      }

      // Add max_links to each users' feeds table
		  if (!$db->query('SELECT max_links FROM '.DB_PREFIX.$userid.'_feeds LIMIT 1')) {
				$sql2 = 'ALTER TABLE '.DB_PREFIX.$userid.'_feeds ADD max_links INT(4) DEFAULT \'0\' AFTER num_headlines';
				if (!$db->query($sql2)) {
					$s = $db->error();
					return array(false, $curVer, $upStr
						." Failed to alter user {$userid}'s feed table: $s.");
				}

				$sql2 = 'UPDATE '.DB_PREFIX.$userid."_feeds SET max_links='$max_links'";
				if (!$db->query($sql2)) {
					$s = $db->error();
					return array(false, $curVer, $upStr
						." Failed to set user {$userid}'s feed max_links to {$max_links}: $s.");
				}
			} else {
				$upStr .= ' [Warning: max_links already added--partial upgrade?]';
		 	}
    }

    $upStr .= " Upgraded $curVer to 1.00.";
    $curVer = '1.00';
  }

  //--------------------------------------------------------------
  if ($curVer == '1.00')
  {
		$userResult = $db->query('SELECT userid FROM '.DB_PREFIX.'user_prefs');
		while ($row = mysql_fetch_assoc($userResult))
		{
			$userid = $row['userid'];

      // Drop index on links temporarily
      $sql1 = 'DROP INDEX feed_id ON '.DB_PREFIX.$userid.'_links';

      if (!$db->query($sql1)) {
				$upStr .= ' [Warning: failed to drop _links index--partial upgrade? ('.$db->error().')]';
      }

      if (!$db->query('SELECT guid FROM '.DB_PREFIX.$userid.'_links LIMIT 1')) {
        // Add guid field to links table
        $sql = 'ALTER TABLE '.DB_PREFIX.$userid.'_links ADD (guid VARCHAR(32))';

        if (!$db->query($sql)) {
          $s = $db->error();
          return array(false, $curVer, $upStr
            ." Failed to add guid to {$userid}'s links: $s.");
        }
      }

      // Compute guid for every link, update
      $linkResult = $db->query('SELECT id,title,link FROM '.DB_PREFIX.$userid.'_links WHERE guid IS NULL');
      while ($row = mysql_fetch_assoc($linkResult))
      {
        $guid = md5($row['title'] . $row['link']);

        if (!$db->query('UPDATE '.DB_PREFIX.$userid."_links SET guid='$guid' WHERE id='".$row['id']."' LIMIT 1"))
          return array(false, $curVer, $upStr
            ." Failed to update feedlink ".$row['id']." for {$userid}: ".$db->error());
      }

      // Change indexing of _links to use new guid field
      $sql2 = 'ALTER IGNORE TABLE '.DB_PREFIX.$userid.'_links ADD UNIQUE INDEX (feed_id,guid)';

      if (!$db->query($sql2)) {
        $s = $db->error();
        return array(false, $curVer, $upStr
          ." Failed to alter user {$userid}'s links with new guid index: $s.");
      }
    }

    $upStr .= " Upgraded $curVer to 1.01.";
    $curVer = '1.01';
  }

  //--------------------------------------------------------------
  $upStr .= ' At version '. RNEWS_VERSION .'.';
  $curVer = RNEWS_VERSION;

  return array(true, $curVer, $upStr);
}

//--------------------------------------------------------------------
function deleteBadFeedImages(&$db)
{
  $found = false;
  $result = $db->query('SELECT userid FROM '.DB_PREFIX.'user_prefs');
  while ($row = mysql_fetch_assoc($result))
  {
    $userid = $row['userid'];
    $feeds = feed::all ($db, $userid);

    foreach ($feeds as $feed) {

      if (!empty($feed->image_link) &&
          substr($feed->image_link, 0, 4) === 'img/' &&
          !is_readable($feed->image_link))
      {
        $feed->image_link = '';
        $feed->update ($db, $userid);

        $found = true;
      }
    }
  }

  return $found;
}

