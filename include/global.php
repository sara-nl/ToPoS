<?php

/*·************************************************************************
 * Copyright ©2009 SARA Computing and Networking Services
 *                 Amsterdam, the Netherlands
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License. You may obtain
 * a copy of the License at <http://www.apache.org/licenses/LICENSE-2.0>
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 * $Id$
 **************************************************************************/

require_once(dirname(__FILE__) . '/REST_1.1/REST.php');
#require_once('rest.php');

#$debug = fopen(dirname(__FILE__) . '/debug.txt', 'a');
#fwrite($debug, "\n\n" . var_export($_SERVER, true));
#fclose($debug);

//session_name('aanwezigheidsbord');
//session_set_cookie_params( 0, dirname($_SERVER['SCRIPT_NAME']) );
//session_start();

date_default_timezone_set('Europe/Amsterdam');

function sara_exception_handler(Exception $e) {
  Topos::fatal(
    'INTERNAL_SERVER_ERROR',
    '<pre>' . htmlspecialchars($e->getTraceAsString() . "\n" . $e->getMessage()) . '</pre>'
  );
}
set_exception_handler('sara_exception_handler');

// Parse the PATH_INFO string, if present:
/**
 * @var string
 */
$TOPOS_REALM = null;
/**
 * @var string
 */
$TOPOS_POOL =  null;
/**
 * @var string
 */
$TOPOS_TOKEN = null;
if ( !empty($_SERVER['PATH_INFO']) &&
     preg_match( '/\\/([\\w\\-.]+)(?:\\/(\\d+))?/',
                 $_SERVER['PATH_INFO'], $matches ) ) {
  $TOPOS_POOL =  @$matches[2];
  $TOPOS_TOKEN = @$matches[3];
}

/**
 * A MySQL exception
 * @package Topos
 */
class Topos_MySQL extends Exception {}


/**
 * A temporary Exception: Try again.
 * @package Topos
 */
class Topos_Retry extends Topos_MySQL {}

  
/**
 * Just a namespace.
 * @package Topos
 */
class Topos {


public static function sortable_date($timestamp) {
  return gmdate( 'Y-m-d\\TH:i:s\\Z', $timestamp );
}
  
  
/**
 * @var mysqli
 */
private static $MYSQLI = null;
/**
 * @return mysqli
 * @throws DAV_Status
 */
public static function mysqli() {
  if (self::$MYSQLI === null) {
    self::$MYSQLI = new mysqli(
      'localhost', 'topos', 'T49WpiQT', 'topos_4'
    );
    if ( !self::$MYSQLI )
      throw new Topos_MySQL(mysqli_connect_error());
//    self::$MYSQLI->real_query(
//      'SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE;'
//    );
//    self::$MYSQLI->autocommit(false);
//    self::$MYSQLI->commit();
  }
  return self::$MYSQLI;
}


public static function escape_string($string) {
  return is_null($string)
    ? 'NULL'
    : '\'' . self::mysqli()->escape_string($string) . '\'';
}


private static $poolIds = array();
public static function poolId($realmName, $poolName) {
  $escRealmName = self::escape_string($realmName);
  $escPoolName = self::escape_string($poolName);
  $result = self::query("SELECT getPoolId($escRealmName, $escPoolName);");
  $row = $result->fetch_row();
  return $row[0];
}


/**
 * @param string $query
 * @return void
 * @throws Exception
 */
public static function real_query($query) {
  if (! self::mysqli()->real_query($query)) {
    if (self::mysqli()->errno == 1205 ||
        self::mysqli()->errno == 1213)
      throw new Topos_Retry( self::mysqli()->error );
    throw new Topos_MySQL( self::mysqli()->error, self::mysqli()->errno );
  }
}


/**
 * @param string $query
 * @return mysqli_result
 * @throws Exception
 */
public static function query($query) {
  if ( !( $retval = self::mysqli()->query($query) ) ) {
    if (self::mysqli()->errno == 1205 ||
        self::mysqli()->errno == 1213)
      throw new Topos_Retry(self::mysqli()->error);
    throw new Topos_MySQL( self::mysqli()->error, self::mysqli()->errno );
  }
  return $retval;
}


public static function uuid() {
  $result = self::query('SELECT UUID();');
  $row = $result->fetch_row();
  return $row[0];
}


/**
 * Sends error code to client
 * @param $status string The status code to send to the client
 * @param $message string The message in the content body
 * @return void This function never returns.
 */
public static function fatal($status, $message = '') {
  $debug = fopen(dirname(__FILE__) . '/debug.txt', 'a');
  fwrite($debug, "\n\n{$status} {$message}\n" . var_export($_SERVER, true));
  fclose($debug);
  return REST::fatal($status, $message, self::urlbase().'style.css');
}


/**
 * @param $title string
 */
public static function start_html($title) {
  echo REST::xml_header();
  $indexURL = dirname($_SERVER['REQUEST_URI']);
  if ($indexURL != '/') $indexURL .= '/';
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-us">
<head>
  <title><?php echo htmlspecialchars($title); ?></title>
  <link rel="stylesheet" type="text/css" href="<?php echo self::urlbase(); ?>style.css" />
  <link rel="index" rev="child" type="application/xhtml+xml" href="<?php echo $indexURL; ?>"/>
</head><body>
<span style="float: right;"><a rel="index" rev="child" href="<?php echo $indexURL; ?>">Index</a></span><?php
}


/**
 * Outputs HTML end-tags
 */
public static function end_html() {
  echo '</body></html>';
}


/**
 * Shows a message screen to the user.
 * @param string $message HTML message
 * @param string $status HTTP status
 * @param string $redirect URL for automatic redirection
 * @param string $location Location of the created URL
 */
public static function show_message($message, $status, $location) {
  REST::header(array(
    'status' => $status,
    'Content-Type' => 'application/xhtml+xml; charset=UTF-8',
    'Location' => REST::rel2url($location)
  ));
  self::start_html('Redirect');
?><p><?php echo $message; ?></p>
<script type="text/javascript">
  setTimeout(
    'window.location.href = "<?php echo $location; ?>";',
    1000
  );
</script><?php
  self::end_html();
  exit;
}


private static $transactionId = NULL;
/**
 * @todo
 * @return unknown_type
 */
public static function transactionId() {
  if (is_null(self::$transactionId)) {
    $url = self::escape_string($_SERVER['REQUEST_URI']);
    $query = <<<EOS
INSERT INTO `Transactions` (`transactionAddress`, `transactionTimestamp`,
                            `transactionMethod`,  `transactionURL`)
VALUES ('{$_SERVER['REMOTE_ADDR']}', UNIX_TIMESTAMP(),
        '{$_SERVER['REQUEST_METHOD']}', {$url});
EOS;
    self::real_query($query);
    self::$transactionId = self::mysqli()->insert_id;
  }
  return self::$transactionId;
}


public static function log($action, $params) {
  $logEntry = array();
  foreach ($params as $key => $value)
    $logEntry[] = "$key=$value";
  $logEntry = join('&', $logEntry);
  $logEntry = self::escape_string("$action?$logEntry");
  $transactionId = self::transactionId();
  $query = <<<EOS
INSERT INTO `Logs` (`transactionId`, `logEntry`)
VALUES ( $transactionId, $logEntry );
EOS;
  self::real_query($query);
}


/**
 * Cache for urlbase().
 * @var string
 */
private static $URLBASE = null;
/**
 * Returns the base URI.
 * The base URI is 'protocol://server.name:port'
 * @return string
 */
public static function urlbase() {
  if ( is_null( self::$URLBASE ) ) {
    //DAV::debug('$_SERVER: ' . var_export($_SERVER, true));
    self::$URLBASE = REST::urlbase() . '/3/';
  }
  return self::$URLBASE;
}


} // class Topos



/**
 * Renders directory content in various formats.
 */
class ToposDirectory {


  /**
   * @var string
   */
  protected $html_form = "";


  /**
   * @var bool
   */
  protected $header_sent = false;


  /**
   * Abstract class has protected ctor;
   */
  protected function __construct($form) {
    $this->html_form = $form;
  }


  /**
   * @param string $type one of 'text/html', 'text/json', 'text/csv', 'text/plain'
   * @return object ToposDirectory
   */
  public static function factory($html_form = null) {
    //self::$html_form = $html_form;
    $best_xhtml_type = REST::best_xhtml_type();
    $type = REST::best_content_type(
    array(
    $best_xhtml_type => 1.0,
        'text/plain' => 0.3,
        'text/tdv' => 0.5,
        'text/csv' => 0.8,
        'application/json' => 1.0,
    ), $best_xhtml_type
    );
    REST::header("{$type}; charset=UTF-8");
    switch ($type) {
      case 'application/xhtml+xml':
      case 'text/html'            : return new ToposDirectoryHTML($html_form);
      case 'text/tdv'             :
      case 'text/plain'           : return new ToposDirectoryPlain($html_form);
      case 'application/json'     : return new ToposDirectoryJSON($html_form);
      case 'text/csv'             : return new ToposDirectoryCSV($html_form);
    }
  }

  /**
   * @param $name string
   */
  public function line($name, $size = '', $description = '') {
    throw new Exception( 'Not implemented' );
  }


  /**
   * Ends the output.
   */
  public function end() {
    throw new Exception( 'Not implemented' );
  }


} // class ToposDirectory


/**
 * Displays content in plain text format (tab delimited)
 */
class ToposDirectoryPlain extends ToposDirectory {


  /**
   * @param $name string
   * @return string
   */
  public function line($name, $size = '', $description = '') {
    echo "{$name}\t{$size}\n";
  }


  /**
   * Ends the output.
   * @return string
   */
  public function end() {
    echo '';
  }


} // class ToposDirectoryPlain


/**
 * Displays content in plain text format (tab delimited)
 */
class ToposDirectoryCSV extends ToposDirectory {

  private function start() {
    echo "Name,Size,Description\r\n";
    $this->header_sent = true;
  }

  /**
   * @param $name string
   */
  public function line($name, $size = '', $description = '') {
    if (!$this->header_sent) {
      $this->start();
    }
    $name = str_replace('"', '""', $name);
    $size = str_replace('"', '""', $size);
    $description = str_replace('"', '""', $description);
    echo "\"{$name}\",\"{$size}\",\"{$description}\"\r\n";
  }


  /**
   * Ends the output.
   * @return string
   */
  public function end() {
    if (!$this->header_sent) {
      $this->start();
    }
    echo '';
  }


} // class ToposDirectoryCSV


/**
 * Displays content in plain text format (tab delimited)
 */
class ToposDirectoryHTML extends ToposDirectory {


  private function start() {
    Topos::start_html('Directory index');
    if ($this->html_form !== null)
      echo $this->html_form;
    echo <<<EOS
<h1>Contents</h1>
<table class="toc" id="directory_index"><tbody>
<tr><th class="name">Name</th><th class="size">Size</th><th class="description">Description</th></tr>
EOS;
    $this->header_sent = true;
  }

  /**
   * @param $name string
   * @return string
   */
  public function line($name, $size = '', $description = '') {
    if (!$this->header_sent) {
      $this->start();
    }
    $is_dir = substr($name, -1) === '/';
    echo '<tr class="' . ( $is_dir ? 'collection' : 'resource' ) .
      '"><td class="name"><a rel="child" href="' . REST::urlencode($name) .
      '">' . htmlentities($name) . "</a></td>
      <td class=\"size\">{$size}</td><td class=\"description\">{$description}</td></tr>\n";
  }


  /**
   * Ends the output.
   * @return string
   */
  public function end() {
    if (!$this->header_sent) {
      $this->start();
    }
    echo "</tbody></table>";
    Topos::end_html();
  }


} // class ToposDirectoryHTML


/**
 * Displays content in plain text format (tab delimited)
 * TODO: Should support streaming
 */
class ToposDirectoryJSON extends ToposDirectory {


  /**
   * Contains a structure...
   */
  private $dir = null;

  private function start() {
    $this->dir = array(
      'header' => array('filename', 'size', 'description'),
      'lines'  => array(),
    );
  }

  public function line($name, $size = '', $description = '') {
    if (empty($this->dir))
      $this->start();
    $this->dir['lines'][] = array($name, $size, $description);
  }

  public function end() {
    if (empty($this->dir))
      $this->start();
    echo json_encode($this->dir);
  }

} // class Directory_JSON
