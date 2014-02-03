<?php

require_once('./inc/config.php');

class DB {
  var $host = DB_HOST;
  var $db = DB_DATABASE;
  var $user = DB_USER;
  var $pass = DB_PASS;
  var $link;

  function DB() {
  }

  function open() {
    $this->link = mysql_connect ($this->host, $this->user, $this->pass);
    if ($this->link) {
//      $this->query("SET NAMES 'utf-8' COLLATE 'utf8_general_ci'");
      $this->query("SET CHARACTER SET UTF-8");
      return mysql_select_db ($this->db);
    } else {
      return FALSE;
    }
  }

  function close() {
    if ($this->link) {
      mysql_close ($this->link);
      unset($this->link);
    }
  }

  function query($q) {
    global $DEBUG;
    if ($this->link) {
      if ($DEBUG > 1) echo "\n<!-- SQL: $q -->\n";
      return mysql_query ($q, $this->link);
    } else {
      return FALSE;
    }
  }

  function error() {
    if ($this->link) {
      return mysql_error();
    } else {
      return 'could not connect to DB';
    }
  }

  // Can call this statically, ie without an instance
  function create() {
    if ($link = mysql_connect (DB_HOST, DB_USER, DB_PASS)) {
      if (mysql_select_db(DB_DATABASE)) {
        return TRUE;
      } else {
        return mysql_query ('CREATE DATABASE '. DB_DATABASE, $link);
      }
    } else {
      return FALSE;
    }
  }
}

