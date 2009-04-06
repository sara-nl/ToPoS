<?php

/*·************************************************************************
 * Copyright © 2008 by SARA Computing and Networking Services             *
 * pieterb@sara.nl                                                        *
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

?>