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

require_once('include/global.php');

$file = fopen( '/dev/urandom', 'r' );
$random = bin2hex(fread($file, 12));
fclose($file);

$url = Topos::urlbase() . "realms/$random/";

$bct = REST::best_content_type(
  array('text/html' => 1,
        'application/xhtml+xml' => 1,
        'text/plain' => 1), 'text/html'
);
if ($bct === 'text/plain') {
  REST::header( array(
    'status' => 'SEE_OTHER',
    'Location' => $url,
    'Content-Type' => 'text/plain; charset=US-ASCII',
  ));
  if ($_SERVER['REQUEST_METHOD'] === 'HEAD') exit;
  echo $url;
  exit;
}

REST::header( array(
  'status' => 'SEE_OTHER',
  'Location' => $url,
  'Content-Type' => REST::best_xhtml_type() . '; charset=UTF-8'
));
Topos::start_html('New realm redirect');
echo "<p><a href=\"$url\">$url</a></p>";
Topos::end_html();
