<?php

/*
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
 */

require_once( dirname(__FILE__) . '/global.php' );

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
   * @return object Directory
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
  public function line($name, $size, $description) {
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
  public function line($name, $size, $description) {
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
  public function line($name, $size, $description) {
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
  public function line($name, $size, $description) {
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

  public function line($name, $size, $description) {
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

