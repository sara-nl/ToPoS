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


$escPool = Topos::escape_string($TOPOS_POOL);

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
  // For this operation, we need MySQL transactions.
  Topos::real_query('START TRANSACTION;');
  try {
    if (isset($_GET['delete'])) {
      if (!is_string($_GET['delete'])) {
        Topos::mysqli()->rollback();
        REST::fatal(
          REST::HTTP_BAD_REQUEST,
          'Illegal value for delete parameter.'
        );
      }
      preg_match('/\\d+$/', $_GET['delete'], $matches);
      $delete = (int)($matches[0]);
      Topos::real_query(<<<EOS
DELETE `Tokens`.* FROM `Tokens` NATURAL JOIN `Pools`
WHERE `tokenId` = $delete
  AND `poolName` = {$escPool};
EOS
      );
      if (!Topos::mysqli()->affected_rows) {
        Topos::mysqli()->rollback();
        REST::fatal(REST::HTTP_NOT_FOUND, "Token $deleteTokenId not found");
      }
    } // if()
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
    
    Topos::real_query('SET foreign_key_checks = 0;');
    $stmt = Topos::mysqli()->prepare(
      'INSERT INTO `TokenValues` (`tokenValue`) VALUES (?);'
    );
    $null = null;
    $stmt->bind_param("b", $null);
    $stream = REST::inputhandle();
    while ( !feof($stream) )
      $stmt->send_long_data( 0, fread( $stream, 8192 ) );
    fclose($stream);
    if ( !$stmt->execute() ) {
      Topos::mysqli()->rollback();
      REST::fatal(REST::HTTP_INTERNAL_SERVER_ERROR, $stmt->error);
    }
    $tokenId = Topos::mysqli()->insert_id;
    
    Topos::real_query(<<<EOS
INSERT INTO `Tokens`
       (`tokenId`, `poolId`, `tokenType`, `tokenName`, `tokenCreated`, `tokenLength`)
SELECT {$tokenId}, getPoolId({$escPool}), {$tokenType}, {$tokenName},
       UNIX_TIMESTAMP(), LENGTH(`tokenValue`)
FROM `TokenValues`
WHERE `tokenId` = {$tokenId};
EOS
    );
    Topos::real_query('SET foreign_key_checks = 1;');
  }
  catch (Topos_MySQL $e) {
    Topos::mysqli()->rollback();
    throw $e;
  }
  if (!Topos::mysqli()->commit())
    REST::fatal(
      REST::HTTP_SERVICE_UNAVAILABLE,
      'Transaction failed: ' . htmlentities(Topos::mysqli()->error)
    );
  $type = REST::best_content_type(
    array(
      'text/plain' => 1.0,
      REST::best_xhtml_type() => 1.0,
    ), REST::best_xhtml_type()
  );
  $tokenURL = Topos::urlbase() . 'pools/' . REST::urlencode($TOPOS_POOL) . '/tokens/' . $tokenId;
  REST::created($tokenURL);
}


REST::require_method('HEAD', 'GET');


//$poolCondition = '';
//if (!empty($TOPOS_POOL))
//  $poolCondition = 'AND `poolName` = ' .
//    Topos::escape_string( $TOPOS_POOL );
//elseif (!empty($_GET['pool']))
//  $poolCondition = 'AND `poolName` LIKE ' .
//    Topos::escape_string( str_replace(
//      array('%',   '_',   '*'),
//      array('\\%', '\\_', '%'),
//      $_GET['pool']
//    ));

//  try {
//    $result = Topos::query(<<<EOS
//SELECT COUNT(*) FROM `Pools`
//WHERE `realmName` = {$escRealm} {$poolCondition}
//FOR UPDATE;
//EOS
//    );
//  }
//  catch (Topos_MySQL $e) {
//    Topos::mysqli()->rollback();
//    REST::fatal(
//      REST::HTTP_BAD_REQUEST,
//      'Illegal regular expression.'
//    );
//  }
//  $row = $result->fetch_row();
//  if (!$row[0]) {
//    Topos::mysqli()->rollback();
//    REST::fatal(REST::HTTP_NOT_FOUND, 'No matching token pools');
//  }

// TODO onderstaand blok verder uitwerken.
//if (!empty($_GET['regexp'])) {
//  $regexp = $_GET['regexp'];
//  $loop = true;
//  while ($loop) {
//    try {
//      // start transaction
//      // insert into RegExp on duplicate update
//      // $last_insert_id set?
//      if (true) {
//        // Insert gelukt, dus regexp uitvoeren
//        // 
//        // INSERT INTO `Matches`
//        // SELECT $last_insert_id, `tokenId`
//        // FROM `Tokens` STRAIGHT JOIN `TokenValues`
//        // WHERE `poolId` = $poolId AND `tokenValue` REGEXP $regexp;
//      } else {
//        // Insert mislukt dus regexp ophalen
//        // SELECT `regexpId` FROM `RegExp` WHERE ...
//      }
//    }
//    catch (Topos_Retry $e) {
//      Topos::mysqli()->rollback();
//      continue;
//    }
//    $loop = false;
//  }
//}

$tokenCondition = '';
if (isset($_GET['token'])) {
  $tokenCondition = 'AND `tokenName` LIKE ' .
    Topos::escape_string(
      str_replace(
        array('%',   '_',   '*'),
        array('\\%', '\\_', '%'),
        $_GET['token']
      )
    );
}

$loopflag = 1;
while ($loopflag) {
  Topos::real_query('START TRANSACTION;');
  try {
    $result = Topos::query(<<<EOS
SELECT `Tokens`.`tokenId`, `Tokens`.`tokenLeases`
FROM `Pools` STRAIGHT_JOIN `Tokens` ON `Pools`.`poolId` = `Tokens`.`poolId`
WHERE `poolName` = {$escPool}
  {$tokenCondition}
  AND `Tokens`.`tokenLockTimeout` <= UNIX_TIMESTAMP()
ORDER BY 2,1
LIMIT 1
FOR UPDATE;
EOS
    );
    if (!($row = $result->fetch_row())) {
      Topos::mysqli()->rollback();
      REST::fatal(REST::HTTP_NOT_FOUND, 'No token available');
    }
    $loopflag = 0;
  }
  catch (Topos_Retry $e) {
    Topos::mysqli()->rollback();
    $loopflag++;
  }
} // while

try {
  $lockUUID = '';
  if ( empty($_GET['timeout']) ||
       (int)($_GET['timeout']) < 1 ) {
    Topos::real_query(<<<EOS
UPDATE `Tokens` SET `tokenLeases` = `tokenLeases` + 1
WHERE `tokenId` = {$row[0]};
EOS
    );
  } else {
    $lockUUID = Topos::uuid();
    $timeout = (int)($_GET['timeout']);
    $description = isset($_GET['description'])
      ? $_GET['description'] : '';
    $description = Topos::escape_string($description);
    Topos::real_query(<<<EOS
UPDATE `Tokens`
SET `tokenLeases` = `tokenLeases` + 1,
    `tokenLockTimeout` = UNIX_TIMESTAMP() + {$timeout},
    `tokenLockUUID` = '{$lockUUID}',
    `tokenLockDescription` = {$description}
WHERE `tokenId` = {$row[0]};
EOS
    );
  }
} // try
catch (Topos_MySQL $e) {
  Topos::mysqli()->rollback();
  throw $e;
}

if (!Topos::mysqli()->commit())
  REST::fatal(
    REST::HTTP_SERVICE_UNAVAILABLE,
    'Transaction failed: ' . htmlentities( Topos::mysqli()->error )
  );

$url = Topos::urlbase() . 'pools/' . REST::urlencode($TOPOS_POOL) .
  '/tokens/' . $row[0];

header( "X-Topos-OpaqueLockToken: opaquelocktoken:$lockUUID" );
header( 'X-Topos-LockURL: ' . Topos::urlbase() . 'pools/' .
        REST::urlencode($TOPOS_POOL) .
        '/locks/' . $lockUUID );
REST::redirect(REST::HTTP_SEE_OTHER, $url);

