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
    echo REST::html_start('Token destroyed');
    echo '<p>Token destroyed successfully.</p>';
    echo REST::html_end();
    exit;
  } else {
    REST::fatal(REST::HTTP_NOT_FOUND);
  }
}

if (!in_array($_SERVER['REQUEST_METHOD'], array('HEAD', 'GET')))
  REST::fatal(REST::HTTP_METHOD_NOT_ALLOWED);
if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
  REST::fatal(REST::HTTP_NOT_MODIFIED);

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
  REST::fatal(REST::HTTP_NOT_FOUND);

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
