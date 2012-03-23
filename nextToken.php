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

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
  $tokenType = Topos::escape_string(
    empty($_SERVER['CONTENT_TYPE'])
      ? 'application/octet-stream'
      : $_SERVER['CONTENT_TYPE']
  );
  $tokenName = '';
  if (!empty($_SERVER['HTTP_CONTENT_DISPOSITION'])) {
    if (preg_match('/;\\s*filename\\s*=\\s*"((?:[^"\\\\]|\\\\.)+)"/',
                   $_SERVER['HTTP_CONTENT_DISPOSITION'], $matches))
      $tokenName = $matches[1];
  }
  $tokenName = Topos::escape_string($tokenName);
  
  $stmt = Topos::mysqli()->prepare(
    'INSERT INTO `TokenValues` (`tokenValue`) VALUES (?);'
  );
  $null = null;
  $stmt->bind_param("b", $null);
  $stream = REST::inputhandle();
  while ( !feof($stream) )
    $stmt->send_long_data( 0, fread( $stream, 8192 ) );
  fclose($stream);
  if ( !$stmt->execute() )
    REST::fatal(REST::HTTP_INTERNAL_SERVER_ERROR, $stmt->error);
  $tokenId = Topos::mysqli()->insert_id;
  
  Topos::real_query(<<<EOS
INSERT INTO `Tokens`
       (`tokenId`, `poolId`, `tokenType`, `tokenName`, `tokenCreated`, `tokenLength`)
SELECT {$tokenId}, {$poolId}, {$tokenType}, {$tokenName},
       UNIX_TIMESTAMP(), LENGTH(`tokenValue`)
FROM `TokenValues`
WHERE `tokenId` = {$tokenId};
EOS
  );
  if (isset($_GET['delete'])) {
    try {
      if (!is_string($_GET['delete']))
        throw new Exception('Bad value for parameter "delete"', REST::HTTP_BAD_REQUEST);
      preg_match('/\\d+$/', $_GET['delete'], $matches);
      if (!($delete = (int)($matches[0])))
        throw new Exception('Bad value for parameter "delete"', REST::HTTP_BAD_REQUEST);
      Topos::real_query(<<<EOS
DELETE `Tokens`, `TokenValues`
FROM `Tokens` NATURAL JOIN `TokenValues` 
WHERE `Tokens`.`tokenId` = {$delete}
  AND `poolId` = {$poolId};
EOS
      );
      if (!Topos::mysqli()->affected_rows)
        throw new Exception("Token $deleteTokenId not found", REST::HTTP_NOT_FOUND);
    } // if()
    catch (Exception $e) {
      Topos::real_query("DELETE FROM `Tokens` WHERE `tokenId` = {$tokenId};");
      REST::fatal($e->getCode(), $e->getMessage());
    }
  }
  $tokenURL = Topos::urlbase() . 'pools/' . REST::urlencode($TOPOS_POOL) . '/tokens/' . $tokenId;
  REST::created($tokenURL);
}


REST::require_method('HEAD', 'GET');

$nameClause = ( is_string( @$_GET['name'] ) && strlen( $_GET['name'] ) ) ?
  "AND MATCH(`tokenName`) AGAINST (" . Topos::escape_string($_GET['name']) . " IN BOOLEAN MODE)" :
  '';

if ( (int)(@$_GET['timeout']) > 0) {
  $timeout = (int)($_GET['timeout']);
  if ($timeout < 1)
    REST::fatal(
      REST::HTTP_BAD_REQUEST,
      'Illegal value for parameter "timeout"'
    );
  $escDescription = isset($_GET['description']) ?
    Topos::escape_string((string)($_GET['description'])) : "''";
  $lockUUID = Topos::uuid();
  $timeout = <<<EOS
, `tokenLockTimeout` = UNIX_TIMESTAMP() + {$timeout}
, `tokenLockUUID` = '{$lockUUID}'
, `tokenLockDescription` = {$escDescription}
EOS;
} else {
  $timeout = '';
  $lockUUID = null;
}

while (true) {
  while (true) {
    $result = Topos::query("SELECT `minLeases` FROM `Pools` WHERE `poolId` = {$poolId};");
    $minLeases = ($row = $result->fetch_row()) ? $row[0] : 0;
    $result = Topos::query(<<<EOS
SELECT `tokenId`, `tokenLeases` FROM `Tokens`
WHERE `poolId` = {$poolId}
  AND `Tokens`.`tokenLeases` = {$minLeases}
  AND `Tokens`.`tokenLockTimeout` <= UNIX_TIMESTAMP()
  {$nameClause}
LIMIT 100;
EOS
    );
    if ($result->num_rows)
      break;
    $result = Topos::query(<<<EOS
SELECT MIN(`tokenLeases`) FROM `Tokens`
WHERE `poolId` = {$poolId}
  AND `tokenLockTimeout` <= UNIX_TIMESTAMP();
EOS
    );
    $row = $result->fetch_row();
    if ( $row[0] === null )
      REST::fatal(REST::HTTP_NOT_FOUND, 'No token available');
    Topos::real_query(<<<EOS
UPDATE `Pools`
SET `minLeases` = {$row[0]}
WHERE `poolId` = {$poolId};
EOS
    );
  } // while
  while ( ( $row = $result->fetch_row() ) ) {
    Topos::real_query(<<<EOS
UPDATE `Tokens` SET `tokenLeases` = {$row[1]} + 1 {$timeout}
WHERE `tokenId` = {$row[0]} AND `tokenLeases` = {$row[1]};
EOS
    );
    if (Topos::mysqli()->affected_rows) break 2;
  } // while
} // while


$url = Topos::urlbase() . 'pools/' . REST::urlencode($TOPOS_POOL) .
  '/tokens/' . $row[0];

if ($lockUUID) {
  header( "X-Topos-OpaqueLockToken: opaquelocktoken:$lockUUID" );
  header( 'X-Topos-LockURL: ' . Topos::urlbase() . 'pools/' .
          REST::urlencode($TOPOS_POOL) . '/locks/' . $lockUUID );
}
REST::redirect( REST::HTTP_SEE_OTHER, $url );
