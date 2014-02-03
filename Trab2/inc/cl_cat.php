<?php

class category {
  var $valid = FALSE;
  var $id;          // int 16
  var $name;        // varchar 255

  //
  // Call: feed(): new feed
  //       feed(row): read from db row
  //       feed(db,id,userid): read id from db table
  //
  function category ($rowOrDb = NULL, $id = -1, $userid = NULL) {
    $row = $rowOrDb;
    if ($id != -1 && $userid !== NULL) {
      $query = 'SELECT * FROM '. DB_PREFIX.$userid ."_cat WHERE id='$id'";
      $result = $rowOrDb->query($query);
      if (!$result || mysql_num_rows($result)==0)
        return;
      $row = mysql_fetch_assoc($result);
      mysql_free_result($result);
    }

    if ($row !== NULL) {
      $this->id = $row['id'];
      $this->name = $row['name'];
    } else {
      $this->id = 0;
      $this->name = '';
    }

    $this->valid = TRUE;
  }

  function update ($db, $userid) {
    $query = 'UPDATE '. DB_PREFIX.$userid .'_cat SET '.
      'name=\''. mysql_real_escape_string($this->name) .'\''.
      " WHERE id='$this->id'";

    return $db->query($query);
  }

  function insert ($db, $userid) {
    $query = 'INSERT INTO '. DB_PREFIX.$userid ."_cat VALUES ('0'," .
      '\''. mysql_real_escape_string($this->name) .'\')';

    $result = $db->query($query);
    if ($result)
      $this->id = mysql_insert_id($db->link);
    return $result;
  }

  function delete ($db, $userid) {
    $query = 'DELETE FROM '. DB_PREFIX.$userid ."_cat WHERE id='$this->id' LIMIT 1";
    return $db->query($query);
  }

  // This function can be called as a static method, like category::create()
  function create ($db, $userid) {
    $table = DB_PREFIX . $userid . '_cat';
    if (!$db->query ("SELECT id FROM $table LIMIT 1")) {
      $query = "CREATE TABLE $table (".
        'id INT(16) NOT NULL AUTO_INCREMENT, '.
        'name VARCHAR(255) NOT NULL, '.
        'PRIMARY KEY (id), UNIQUE name (name))';
      return $db->query($query);
    } else {
      return TRUE;
    }
  }

	// This function can be called as a static method, like category::all()
	// Returns an array of category objects, optionally filtered or ordered.
	//
	function all ($db, $userid, $filter = '') {
		$out = array();
		$result = $db->query ('SELECT * FROM '. DB_PREFIX.$userid ."_cat $filter");
		while ($row = mysql_fetch_assoc($result)) {
			$out[] = new category($row);
		}
		return $out;
	}

}

