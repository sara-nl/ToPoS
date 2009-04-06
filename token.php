<?php

/*·************************************************************************
 * Copyright © 2008 by SARA Computing and Networking Services             *
 * pieterb@sara.nl                                                        *
 **************************************************************************/

require_once('include/global.php');

$escRealm = Topos::escape_string($TOPOS_REALM);
$escPool = Topos::escape_string($TOPOS_POOL);

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
  $retries = 1;
  while ($retries) {
    try {
      Topos::real_query(<<<EOS
DELETE `Tokens`.* FROM `Tokens` NATURAL JOIN `Pools`
WHERE `tokenId` = {$TOPOS_TOKEN}
  AND `realmName` = {$escRealm}
  AND `poolName` = {$escPool};
EOS
      );
      $retries = 0;
    }
    catch (Topos_Retry $e) {
      $retries++;
    }
  }
  if (Topos::mysqli()->affected_rows) {
    Topos::log('delete', array(
      'realmName' => $TOPOS_REALM,
      'poolName' => $TOPOS_POOL,
      'tokenId' => $TOPOS_TOKEN,
    ));
    REST::header(array(
      'Content-Type' => REST::best_xhtml_type() . '; charset=UTF-8'
    ));
    Topos::start_html('Token destroyed');
    echo '<p>Token destroyed successfully.</p>';
    Topos::end_html();
    exit;
  } else {
    Topos::fatal('NOT_FOUND');
  }
}

if (!in_array($_SERVER['REQUEST_METHOD'], array('HEAD', 'GET')))
  Topos::fatal('METHOD_NOT_ALLOWED');
if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
  Topos::fatal('NOT_MODIFIED');

$result = Topos::query(<<<EOS
SELECT `tokenValue`, `tokenType`, `tokenCreated`,
       IF(`tokenLockTimeout` > UNIX_TIMESTAMP(),`tokenLockUUID`,NULL)
FROM `Pools` NATURAL JOIN `Tokens`
WHERE `realmName` = {$escRealm}
  AND `poolName`  = {$escPool}
  AND `tokenId`   = {$TOPOS_TOKEN};
EOS
);
if (!($row = $result->fetch_row()))
  Topos::fatal('NOT_FOUND');

$headers = array(
  'Content-Type' => $row[1],
  'Content-Length' => strlen($row[0]),
  'Last-Modified' => REST::http_date($row[2]),
);
if ($row[3]) {
  $headers['Topos-OpaqueLockToken'] = "opaquelocktoken:{$row[3]}";
  $headers['Topos-LockURL'] = Topos::urlbase() . 'realms/' . REST::urlencode($TOPOS_REALM) .
    '/locks/' . $row[3];
}
REST::header($headers);
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') exit;
echo $row[0];

?>