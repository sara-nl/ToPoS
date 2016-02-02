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

$poolId = Topos::poolId($TOPOS_POOL);

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
  $query = <<<EOS
DELETE FROM `Tokens`
WHERE `Tokens`.`tokenId` = {$TOPOS_TOKEN}
  AND `poolId` = {$poolId};
EOS;
  Topos::real_query($query);
  if (Topos::mysqli()->affected_rows) {
    REST::fatal(
      REST::HTTP_OK,
      'Token destroyed'
    );
  } else {
    REST::fatal(REST::HTTP_NOT_FOUND);
  }
}

REST::require_method('HEAD', 'GET');
if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
  REST::fatal(REST::HTTP_NOT_MODIFIED);

$poolId = Topos::poolId($TOPOS_POOL);
$result = Topos::query(<<<EOS
SELECT `tokenLength`, `tokenType`, `tokenCreated`, `tokenName`,
       IF(`tokenLockTimeout` > UNIX_TIMESTAMP(), `tokenLockUUID`, NULL) AS 'tokenLockUUID', `tokenLeases`
FROM `Tokens`
WHERE `tokenId` = {$TOPOS_TOKEN}
  AND `poolId`  = {$poolId};
EOS
);
if (!($row = $result->fetch_array()))
  REST::fatal(REST::HTTP_NOT_FOUND);
$result = Topos::query(<<<EOS
SELECT `tokenValue` FROM `TokenValues`
WHERE `tokenId` = {$TOPOS_TOKEN}
EOS
);
$tokenValue = $result->fetch_row();
$tokenValue = $tokenValue[0];

$headers = array(
  'Content-Type' => $row['tokenType'],
  'Content-Length' => $row['tokenLength'],
  'Last-Modified' => REST::http_date($row['tokenCreated']),
  'X-Number-Of-Leases' => $row['tokenLeases'],
);
if (!empty($row['tokenName']))
  $headers['Content-Disposition'] = 'inline; filename="' . $row['tokenName'] . '"';
  
if (array_key_exists('tokenLockUUID', $row)) {
  $headers['X-Topos-OpaqueLockToken'] = "opaquelocktoken:{$row['tokenLockUUID']}";
  $headers['X-Topos-LockURL'] = Topos::urlbase() . 'pools/' . REST::urlencode($TOPOS_POOL) .
    '/locks/' . $row['tokenLockUUID'];
}
REST::header($headers);
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') exit;
echo $tokenValue;
