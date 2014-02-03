<?php

class feedlink {
  // In PHP 5 we can use const here, but not in PHP 4
  function STATE_NEW()     { return 0; }
  function STATE_SEEN()    { return 1; }
  function STATE_VISITED() { return 2; }
  function STATE_STARRED() { return 3; }
  function STATE_DELETED() { return 4; }

  function stateStr($s) {
    switch ($s) {
      case 0:  return 'new';
      case 1:  return 'seen';
      case 2:  return 'visited';
      case 3:  return 'starred';
      default: return '??';
    }
  }

  var $valid = FALSE;
  var $id;          // int 16
  var $feed_id;     // int 16
  var $link;        // varchar 255
  var $title;       // varchar 255
  var $description; // text
  var $state;       // int  (STATE_*)
  var $pubdate;     // datetime
  var $guid;        // varchar 32

  //
  // Call: feed(): new feed
  //       feed(row): read from db row
  //       feed(db,id,userid): read record from db
  //
  function feedlink ($rowOrDb = NULL, $id = -1, $userid = NULL) {
    $row = $rowOrDb;
    if ($id != -1 && $userid != NULL) {
      $query = 'SELECT * FROM '. DB_PREFIX.$userid ."_links WHERE id='$id'";
      $result = $rowOrDb->query($query);
      if (!$result || mysql_num_rows($result)==0)
        return;
      $row = mysql_fetch_assoc($result);
      mysql_free_result($result);
    }

    if ($row !== NULL) {
      $this->id = $row['id'];
      $this->feed_id = $row['feed_id'];
      $this->link = $row['link'];
      $this->title = $row['title'];
      $this->description = $row['description'];
      $this->state = $row['state'];
      $this->pubdate = $row['pubdate'];
      $this->guid = $row['guid'];
    } else {
      $this->id = 0;
      $this->feed_id = 0;
      $this->link = '';
      $this->title = '';
      $this->description = '';
      $this->state = feedlink::STATE_NEW();
      $this->pubdate = '';
      $this->guid = '';
    }

    $this->valid = TRUE;
  }

  function insert ($db, $userid) {
    $query = 'INSERT INTO '. DB_PREFIX.$userid .'_links VALUES (\'0\',' .
      '\'' . intval($this->feed_id) . '\', ' .
      '\'' . mysql_real_escape_string($this->link) . '\', ' .
      '\'' . mysql_real_escape_string($this->title) . '\', ' .
      '\'' . mysql_real_escape_string($this->description) . '\', ' .
      '\'' . intval($this->state) . '\', ' .
      '\'' . $this->pubdate . '\', ' .
      '\'' . $this->guid . '\')';

    $result = $db->query($query);
    if ($result)
      $this->id = mysql_insert_id($db->link);
    return $result;
  }

  function update ($db, $userid) {
    $query = 'UPDATE '. DB_PREFIX.$userid .'_links SET ' .
      "feed_id='" . intval($this->feed_id) . '\', ' .
      "link='" . mysql_real_escape_string($this->link) . '\', ' .
      "title='" . mysql_real_escape_string($this->title) . '\', ' .
      "description='" . mysql_real_escape_string($this->description) . '\', ' .
      "state='" . intval($this->state) . '\', ' .
      "pubdate='" . $this->pubdate . '\', ' .
      "guid='" . $this->guid . '\' ' .
      "WHERE id='$this->id'";

    return $db->query($query);
  }
             
  function updateMeta ($db, $userid) {
    $query = 'UPDATE '. DB_PREFIX.$userid .'_links SET ' .
      "feed_id='" . intval($this->feed_id) . '\', ' .
      "state='" . intval($this->state) . '\', ' .
      "pubdate='" . $this->pubdate . '\', ' .
      "guid='" . $this->guid . '\' ' .
      "WHERE id='$this->id'";

    return $db->query($query);
  }
             
  // This function may be called as if a static method, like links::create()
  function create ($db, $userid) {
    $table = DB_PREFIX . $userid .'_links';
    if (!$db->query ("SELECT id FROM $table LIMIT 1")) {
      $query = "CREATE TABLE $table ( ".
        'id INT(16) NOT NULL AUTO_INCREMENT, '.
        'feed_id INT(16), '.
        'link VARCHAR(255), '.
        'title VARCHAR(255), '.
        'description TEXT, '.
        'state int(1), '.
        'pubdate DATETIME, '.
        'guid VARCHAR(32), '.
        'primary KEY (id), UNIQUE KEY (feed_id,guid) )';
      return $db->query($query);
    } else {
      return TRUE;
    }
  }

  // This function can be called as a static method, like feedlink::all()
  // Returns an array of feedlink objects, optionally filtered or ordered.
  //
  function all ($db, $userid, $filter = '') {
    $out = array();
    $result = $db->query ('SELECT * FROM '. DB_PREFIX.$userid ."_links $filter");
    while ($row = mysql_fetch_assoc($result)) {
      $out[] = new feedlink($row);
    }
    return $out;
  }

  // static
  function purge ($db, $userid, $feedid) {
    return $db->query ('DELETE FROM '.DB_PREFIX.$userid ."_links WHERE feed_id='$feedid'");
  }
  // static
  function purgeOld ($db, $userid, $days) {
    return $db->query ('DELETE FROM '.DB_PREFIX.$userid .'_links WHERE DATE_SUB(CURDATE(), INTERVAL '. intval($days) .' DAY) >= pubdate');
  }

  // static
  function mark ($db, $userid, $feedid = null, $linkid = null) {
    $sql = 'UPDATE '.DB_PREFIX.$userid."_links SET state='". feedlink::STATE_SEEN()
      . "' WHERE state='". feedlink::STATE_NEW() ."'";
    if ($feedid != null)
      $sql .= " AND feed_id='". intval($feedid) ."'";
    if ($linkid != null) {
      $links = feedlink::all ($db, $userid, "WHERE id='".intval($linkid)."'");
      if (count($links) != 1)
        return FALSE;
      $sql .= " AND ((pubdate < '{$links[0]->pubdate}') OR ((pubdate = '{$links[0]->pubdate}') AND (id <= '". intval($linkid) ."')))";
    }
    return $db->query ($sql);
  }

  // static
  function markOne ($db, $userid, $linkid, $st) {
    $sql = 'UPDATE '.DB_PREFIX.$userid."_links SET state='". intval($st)
      . "' WHERE id='". intval($linkid) ."'";
    return $db->query ($sql);
  }
}

