<?php

class configw
{
  // In PHP 5 we can use const here, but not in PHP 4
  function CONFIG_FILE() { return 'inc/config_user.php'; }

  var $data;

  function configw()
  {
    $this->data = array();
  }

  // Read the config file and make an associative array of all the defines.
  //
  function open()
  {
    if (!($contents = file_get_contents(configw::CONFIG_FILE())))
      return FALSE;

    $results = array();
    $n = preg_match_all ("/^\s*define\s*\('([^']+)'\s*,\s*(.+)\);\s*$/m",
      $contents, $results);

    for ($i = 0; $i < $n; $i++) {
      $this->data[$results[1][$i]] = $results[2][$i];
    }

    return TRUE;
  }

  function get ($name)
  {
    if (isset($this->data, $name))
      return $this->data[$name];
    else
      return NULL;
  }

  function set ($name, $value)
  {
    $this->data[$name] = $value;
  }

  function close()
  {
    if (!($f = fopen (configw::CONFIG_FILE(), 'w')))
      return FALSE;

    $s = "<?php\n// This file is overwritten by install.php\n\n";

    foreach ($this->data as $key => $value)
      $s .= "define('$key', $value);\n";

    $s .= "\n?>\n";
    $rc = fwrite($f, $s);

    fclose($f);

    return $rc;
  }

}

