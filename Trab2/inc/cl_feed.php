<?php

class feed {
  var $valid = FALSE;
  var $id;          // int 16
  var $name;        // varchar 255
  var $main_link;   // varchar 255
  var $rss_link;    // text
  var $image_link;  // varchar 255
  var $cat_id;      // tinyint 4
  var $last_update; // datetime
  var $last_add;    // datetime
  var $headlines;   // tinyint 4
  var $max_links;   // int 4
  var $stat_total;  // int 16
  var $stat_expand; // int 16
  var $stat_click;  // int 16
  var $catPtr = NULL;

  //
  // Call: feed(): new feed
  //       feed(row): read row from db
  //       feed(db,id,table): read id from db
  //
  function feed ($rowOrDb = NULL, $id = -1, $userid = NULL) {
    $row = $rowOrDb;
    if ($id != -1 && $userid != NULL) {
      $query = "SELECT * FROM ". DB_PREFIX.$userid ."_feeds WHERE id='$id'";
      $result = $rowOrDb->query($query);
      if (!$result || mysql_num_rows($result)==0)
        return;
      $row = mysql_fetch_assoc($result);
      mysql_free_result($result);
    }

    if ($row !== NULL) {
      $this->id = $row['id'];
      $this->name = $row['name'];
      $this->main_link = $row['main_link'];
      $this->rss_link = $row['rss_link'];
      $this->image_link = $row['image_link'];
      $this->cat_id = $row['cat_id'];
      $this->last_update = $row['last_update'];
      $this->last_add = $row['last_add'];
      $this->headlines = $row['num_headlines'];
      $this->max_links = $row['max_links'];
      $this->stat_total = $row['stat_total'];
      $this->stat_expand = $row['stat_expand'];
      $this->stat_click = $row['stat_click'];
    } else {
      $this->id = 0;
      $this->name = '';
      $this->main_link = '';
      $this->rss_link = '';
      $this->image_link = '';
      $this->cat_id = 0;
      $this->last_update = '0000-00-00 00:00:00';
      $this->last_add = '0000-00-00 00:00:00';
      $this->headlines = 10;
      $this->max_links = 0;
      $this->stat_total = 1;
      $this->stat_expand = 0;
      $this->stat_click = 0;
    }

    $this->valid = TRUE;
  }

  function update ($db, $userid) {
    $query = 'UPDATE '. DB_PREFIX.$userid .'_feeds SET ' .
      "name='" . mysql_real_escape_string($this->name) . "', " .
      "main_link='" . mysql_real_escape_string($this->main_link) . "', " .
      "rss_link='" . mysql_real_escape_string($this->rss_link) . "', " .
      "image_link='" . mysql_real_escape_string($this->image_link) . "', " .
      "cat_id='" . intval($this->cat_id) . "', " .
      "last_update='" . mysql_real_escape_string($this->last_update) . "', " .
      "last_add='" . mysql_real_escape_string($this->last_add) . "', " .
      "num_headlines='" . intval($this->headlines) . "', " .
      "max_links='" . intval($this->max_links) . "', " .
      "stat_total='" . intval($this->stat_total) . "', " .
      "stat_expand='" . intval($this->stat_expand) . "', " .
      "stat_click='" . intval($this->stat_click) . "' " .
      "WHERE id='$this->id'";

    return $db->query($query);
  }

  function insert ($db, $userid) {
    $query = 'INSERT INTO '. DB_PREFIX.$userid .'_feeds VALUES (\'0\',' .
      '\'' . mysql_real_escape_string($this->name) . '\', ' .
      '\'' . mysql_real_escape_string($this->main_link) . '\', ' .
      '\'' . mysql_real_escape_string($this->rss_link) . '\', ' .
      '\'' . mysql_real_escape_string($this->image_link) . '\', ' .
      '\'' . intval($this->cat_id) . '\', ' .
      '\'' . mysql_real_escape_string($this->last_update) . '\', ' .
      '\'' . mysql_real_escape_string($this->last_add) . '\', ' .
      '\'' . intval($this->headlines) . '\', ' .
      '\'' . intval($this->max_links) . '\', ' .
      '\'' . intval($this->stat_total) . '\', ' .
      '\'' . intval($this->stat_expand) . '\', ' .
      '\'' . intval($this->stat_click) . '\')';

    $result = $db->query($query);
    if ($result)
      $this->id = mysql_insert_id($db->link);
    return $result;
  }

  function delete ($db, $userid) {
    $query = 'DELETE FROM '. DB_PREFIX.$userid ."_feeds WHERE id='$this->id' LIMIT 1";
    return $db->query($query);
  }

  // This function can be called as a static method, like feed::create()
  function create ($db, $userid) {
    $table = DB_PREFIX . $userid .'_feeds';
    if (!$db->query ("SELECT id FROM $table LIMIT 1")) {
      $query = "CREATE TABLE $table (".
        'id INT(16) NOT NULL AUTO_INCREMENT, '.
        'name VARCHAR(255), '.
        'main_link VARCHAR(255), '.
        'rss_link VARCHAR(255), '.
        'image_link VARCHAR(255), '.
        'cat_id TINYINT(4) DEFAULT \'0\' NOT NULL, '.
        'last_update DATETIME, '.
        'last_add DATETIME, '.
        'num_headlines TINYINT(4) DEFAULT \'10\' NOT NULL, '.
        'max_links INT(4) DEFAULT \'0\', '.
        'stat_total INT(10) UNSIGNED DEFAULT \'1\', '.
        'stat_expand INT(10) UNSIGNED DEFAULT \'0\', '.
        'stat_click INT(10) UNSIGNED DEFAULT \'0\', '.
        'PRIMARY KEY (id))';
      return $db->query($query);
    } else {
      return TRUE;
    }
  }

  // This function can be called as a static method, like feed::all()
  // Returns an array of feed objects, optionally filtered or ordered.
  //
  function all ($db, $userid, $filter = '') {
    $out = array();
    $result = $db->query ('SELECT * FROM '. DB_PREFIX.$userid ."_feeds $filter");
    while ($row = mysql_fetch_assoc($result)) {
      $out[] = new feed($row);
    }
    return $out;
  }

  // Increment a statistic
  function inc_stat ($db, $userid, $feedid, $stat, $inc = 1) {
    $stat = "stat_$stat";
    $query = 'UPDATE '. DB_PREFIX.$userid ."_feeds SET $stat = $stat + $inc " .
      "WHERE id='". intval($feedid) ."'";

    return $db->query($query);
  }

  // compute the current score
  function getScore() {
    return intval(100*($this->stat_expand+$this->stat_click) / $this->stat_total);
  }

  // clear this feed's internal stats
  function clear_stats() {
    $this->stat_total = 1;
    $this->stat_expand = 0;
    $this->stat_click = 0;
  }

  // static: clear this feed's stats
  function clear_all_stats ($db, $userid) {
    $query = 'UPDATE '. DB_PREFIX.$userid .'_feeds SET stat_total=1,stat_expand=0,stat_click=0';
    return $db->query($query);
  }

}

