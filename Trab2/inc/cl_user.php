<?php

class user {
  var $valid = FALSE;       // not saved in DB
  var $restricted = FALSE;  // not saved in DB
  var $prefs = array();     // not saved in DB: view V={B|W|L}, sort S={S|N}, filter F={A|N}, async A={0|1}
  var $id;          // int 16
  var $userid;      // varchar 32
  var $name;        // varchar 64
  var $timeout;     // mediumint 8
  var $headlines;   // varchar 255
  var $salt;        // varchar 8
  var $passwd;      // varchar 100
  var $show_images; // tinyint 1
  var $default_cat; // mediumint 8
  var $max_links;   // mediumint 8
  var $keep_stats;  // tinyint 1
  var $expand_one;  // tinyint 1
  var $new_window;  // tinyint 1
  var $snippets;    // tinyint 1
  var $disabled;    // tinyint 1

  //
  // Call: user(): new user
  //       user(row): read row from db
  //       user(db,id): read id from db
  //
  function user ($rowOrDb = NULL, $id = -1) {
    $row = $rowOrDb;
    if ($id != -1) {
      $query = 'SELECT * FROM '. DB_PREFIX ."user_prefs WHERE id='$id'";
      $result = $rowOrDb->query($query);
      if (!$result || mysql_num_rows($result)==0)
        return;
      $row = mysql_fetch_assoc($result);
      mysql_free_result($result);
    }

    if ($row !== NULL) {
      $this->id = $row['id'];
      $this->userid = $row['userid'];
      $this->name = $row['name'];
      $this->timeout = $row['timeout'];
      $this->headlines = $row['number_headlines'];
      $this->salt = $row['salt'];
      $this->passwd = $row['passwd'];
      $this->show_images = $row['show_images'];
      $this->default_cat = $row['default_category'];
      $this->max_links = $row['max_links'];
      $this->keep_stats = $row['keep_stats'];
      $this->expand_one = $row['expand_one'];
      $this->new_window = $row['new_window'];
      $this->snippets = $row['snippets'];
      $this->disabled = $row['disabled'];
    } else {
      $this->id = -1;
      $this->userid = '';
      $this->name = '';
      $this->timeout = 1800;
      $this->headlines = 10;
      $this->salt = '';
      $this->passwd = '';
      $this->show_images = 1;
      $this->default_cat = 0;
      $this->max_links = 60;
      $this->keep_stats = 1;
      $this->expand_one = 0;
      $this->new_window = 0;
      $this->snippets = 1;
      $this->disabled = 0;
    }

    $this->valid = TRUE;
  }

  function update ($db) {
    $query = 'UPDATE '. DB_PREFIX .'user_prefs SET ' .
      "userid='" . mysql_real_escape_string($this->userid) . "', " .
      "name='" . mysql_real_escape_string($this->name) . "', " .
      "timeout='" . intval($this->timeout) . "', " .
      "number_headlines='" . intval($this->headlines) . "', " .
      "salt='" . mysql_real_escape_string($this->salt) . "', " .
      "passwd='" . mysql_real_escape_string($this->passwd) . "', " .
      "show_images='" . intval($this->show_images) . "', " .
      "default_category='" . intval($this->default_cat) . "', " .
      "max_links='" . intval($this->max_links) . "', " .
      "keep_stats='" . intval($this->keep_stats) . "', " .
      "expand_one='" . intval($this->expand_one) . "', " .
      "new_window='" . intval($this->new_window) . "', " .
      "snippets='" . intval($this->snippets) . "', " .
      "disabled='" . intval($this->disabled) . "' " .
      "WHERE id='$this->id'";

    return $db->query($query);
  }

  function insert ($db) {
    $query = 'INSERT INTO '. DB_PREFIX ."user_prefs VALUES ('0'," .
      "'" . mysql_real_escape_string($this->userid) . "', " .
      "'" . mysql_real_escape_string($this->name) . "', " .
      "'" . intval($this->timeout) . "', " .
      "'" . intval($this->headlines) . "', " .
      "'" . mysql_real_escape_string($this->salt) . "', " .
      "'" . mysql_real_escape_string($this->passwd) . "', " .
      "'" . intval($this->show_images) . "', " .
      "'" . intval($this->default_cat) . "', " .
      "'" . intval($this->max_links)   . "', " .
      "'" . intval($this->keep_stats)  . "', " .
      "'" . intval($this->expand_one)  . "', " .
      "'" . intval($this->new_window)  . "', " .
      "'" . intval($this->snippets)    . "', " .
      "'" . intval($this->disabled)    . "')";

    $result = $db->query($query);
    if ($result)
      $this->id = mysql_insert_id($db->link);
    return $result;
  }

  // This function can be called like a static method, ie without an
  // instance, like user::create($db)
  function create ($db) {
    if (!$db->query ('SELECT id FROM '. DB_PREFIX .'user_prefs LIMIT 1')) {
      $query = 'CREATE TABLE '. DB_PREFIX .'user_prefs ('.
        'id INT(16) NOT NULL AUTO_INCREMENT, '.
        'userid VARCHAR(32) DEFAULT NULL, '.
        'name VARCHAR(64) DEFAULT NULL, '.
        'timeout MEDIUMINT(8) NOT NULL DEFAULT \'1800\', '.
        'number_headlines TINYINT(4) NOT NULL DEFAULT \'10\', '.
        'salt VARCHAR(8) DEFAULT NULL, '.
        'passwd VARCHAR(100) DEFAULT NULL, '.
        'show_images TINYINT(1) DEFAULT \'1\', '.
        'default_category MEDIUMINT(8) DEFAULT \'0\', '.
        'max_links MEDIUMINT(8) DEFAULT \'60\', '.
        'keep_stats TINYINT(1) DEFAULT \'1\', '.
        'expand_one TINYINT(1) DEFAULT \'0\', '.
        'new_window TINYINT(1) DEFAULT \'0\', '.
        'snippets TINYINT(1) DEFAULT \'1\', '.
        'disabled TINYINT(1) DEFAULT \'0\', '.
        'PRIMARY KEY (id), UNIQUE KEY userid (userid) )';
      return $db->query($query);
    } else {
      return TRUE; 
    }
  }

  // To be called statically.  This is basically a constructor based
  // on userid (which may fail).
  function fromUserid (&$db, $userid) {
    $query = 'SELECT * FROM '. DB_PREFIX .'user_prefs WHERE userid=\''.
      mysql_real_escape_string($userid) .'\'';
    $result = $db->query($query);
    if (!$result || mysql_num_rows($result)==0)
      return NULL;
    $row = mysql_fetch_assoc($result);
    return new user ($row);
  }

  // This function can be called as a static method, like user::all()
  // Returns an array of category objects, optionally filtered or ordered.
  //
  function all ($db, $filter = '') {
    $out = array();
    $result = $db->query ('SELECT * FROM '. DB_PREFIX ."user_prefs $filter");
    while ($row = mysql_fetch_assoc($result)) {
      $out[] = new user($row);
    }
    return $out;
  }
}

