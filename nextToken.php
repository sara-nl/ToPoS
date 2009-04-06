<?php

/*·************************************************************************
 * Copyright © 2008 by SARA Computing and Networking Services             *
 * pieterb@sara.nl                                                        *
 **************************************************************************/

require_once('include/global.php');


$escRealm = Topos::escape_string($TOPOS_REALM);

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
  if (empty($TOPOS_POOL)) $TOPOS_POOL = $_GET['pool'];
  if (empty($TOPOS_POOL))
    Topos::fatal(
      'BAD_REQUEST', 'Missing required pool name'
    );
  // For this operation, we need MySQL transactions.
  Topos::real_query('START TRANSACTION;');
  try {
    if (isset($_GET['delete'])) {
      if (!is_array($_GET['delete']))
        $_GET['delete'] = preg_split(
          '/[\\D]+/', $_GET['delete'], -1, PREG_SPLIT_NO_EMPTY
        );
      foreach ( $_GET['delete'] as $delete )
        if (($deleteTokenId = (int)($delete))) {
          Topos::real_query(<<<EOS
DELETE `Tokens`.* FROM `Tokens` NATURAL JOIN `Pools`
WHERE `tokenId` = $deleteTokenId
  AND `realmName` = {$escRealm};
EOS
          );
          if (!Topos::mysqli()->affected_rows) {
            Topos::mysqli()->rollback();
            Topos::fatal('NOT_FOUND', "Token $deleteTokenId not found");
          }
          Topos::log('delete', array(
            'realmName' => $TOPOS_REALM,
            'poolName'  => $TOPOS_POOL,
            'tokenId'   => $deleteTokenId,
          ));
        } // foreach() if()
    } // if()
    $escPool = Topos::escape_string($TOPOS_POOL);
    $tokenType = empty($_SERVER['CONTENT_TYPE']) ?
      'application/octet-stream' : $_SERVER['CONTENT_TYPE'];
    $tokenType = Topos::escape_string($tokenType);
    $stmt = Topos::mysqli()->prepare(<<<EOS
INSERT INTO `Tokens`
(`poolId`, `tokenValue`, `tokenType`, `tokenCreated`)
VALUES (getPoolId({$escRealm}, {$escPool}), ?, {$tokenType}, UNIX_TIMESTAMP());
EOS
    );
    $null = null;
    $stmt->bind_param("b", $null);
    $stream = REST::inputhandle();
    while ( !feof($stream) )
      $stmt->send_long_data( 0, fread( $stream, 8192 ) );
    fclose($stream);
    if ( !$stmt->execute() ) {
      Topos::mysqli()->rollback();
      Topos::fatal('INTERNAL_SERVER_ERROR', $stmt->error);
    }
    $tokenId = Topos::mysqli()->insert_id;
    Topos::log('create', array(
      'realmName' => $TOPOS_REALM,
      'poolName' => $TOPOS_POOL,
      'tokenId' => $tokenId,
    ));
  }
  catch (Topos_MySQL $e) {
    Topos::mysqli()->rollback();
    throw $e;
  }
  if (!Topos::mysqli()->commit())
    Topos::fatal(
      'SERVICE_UNAVAILABLE',
      'Transaction failed: ' . htmlentities(Topos::mysqli()->error)
    );
  $type = REST::best_content_type(
    array(
      'text/plain' => 1.0,
      REST::best_xhtml_type() => 1.0,
    ), REST::best_xhtml_type()
  );
  $tokenURL = Topos::urlbase() . 'realms/' . REST::urlencode($TOPOS_REALM) .
    '/pools/' . REST::urlencode($TOPOS_POOL) . '/tokens/' . $tokenId;
  REST::header(array(
    'Content-Type' => "{$type}; charset=UTF-8",
    'status' => 'CREATED',
    'Location' => $tokenURL,
  ));
  switch ($type) {
    case 'text/plain':
      echo $tokenURL;
      break;
    default:
      Topos::start_html('New token created');
      echo "<p><a href=\"$tokenURL\">$tokenId</a></p>";
      Topos::end_html();
  }
  exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], array('HEAD', 'GET')))
  Topos::fatal('METHOD_NOT_ALLOWED');

$poolCondition = '';
if (!empty($TOPOS_POOL))
  $poolCondition = 'AND `poolName` = ' .
    Topos::escape_string( $TOPOS_POOL );
elseif (!empty($_GET['pool']))
  $poolCondition = 'AND `poolName` LIKE ' .
    Topos::escape_string( str_replace(
      array('%',   '_',   '*'),
      array('\\%', '\\_', '%'),
      $_GET['pool']
    ));
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
//    Topos::fatal(
//      'BAD_REQUEST',
//      'Illegal regular expression.'
//    );
//  }
//  $row = $result->fetch_row();
//  if (!$row[0]) {
//    Topos::mysqli()->rollback();
//    Topos::fatal('NOT_FOUND', 'No matching token pools');
//  }

$tokenCondition = empty($_GET['token']) ? '' :
  'AND `tokenValue` LIKE ' .
    Topos::escape_string( str_replace(
      array('%',   '_',   '*'),
      array('\\%', '\\_', '%'),
      $_GET['token']
    ));

$loopflag = 1;
while ($loopflag) {
  Topos::real_query('START TRANSACTION;');
  try {
    $result = Topos::query(<<<EOS
SELECT `Tokens`.`tokenId`, `poolName`, `Tokens`.`tokenLeases`
FROM `Pools` STRAIGHT_JOIN `Tokens` ON `Pools`.`poolId` = `Tokens`.`poolId`
WHERE `realmName` = {$escRealm}
  {$poolCondition} {$tokenCondition}
  AND `Tokens`.`tokenLockTimeout` <= UNIX_TIMESTAMP()
ORDER BY 3,1
LIMIT 1
FOR UPDATE;
EOS
    );
    $loopflag = 0;
  }
  catch (Topos_Retry $e) {
    Topos::mysqli()->rollback();
    $loopflag++;
  }
} // while

try {
  if (!($row = $result->fetch_row())) {
    Topos::mysqli()->rollback();
    Topos::fatal('NOT_FOUND', 'No token available');
  }

  $lockUUID = '';
  if ( empty($_GET['timeout']) ||
       (int)($_GET['timeout']) < 1 ) {
    Topos::real_query(<<<EOS
UPDATE `Tokens` SET `tokenLeases` = `tokenLeases` + 1
WHERE `tokenId` = {$row[0]};
EOS
    );
    Topos::log('lease', array(
      'realmName' => $TOPOS_REALM,
      'poolName' => $row[1],
      'tokenId' => $row[0],
    ));
  } else {
    $lockUUID = Topos::uuid();
    $timeout = (int)($_GET['timeout']);
    Topos::real_query(<<<EOS
UPDATE `Tokens`
SET `tokenLeases` = `tokenLeases` + 1,
    `tokenLockTimeout` = UNIX_TIMESTAMP() + {$timeout},
    `tokenLockUUID` = '{$lockUUID}'
WHERE `tokenId` = {$row[0]};
EOS
    );
    Topos::log('lease', array(
      'realmName' => $TOPOS_REALM,
      'poolName' => $row[1],
      'tokenId' => $row[0],
      'timeout' => $timeout,
      'UUID' => $lockUUID,
    ));
  }
} // try
catch (Topos_MySQL $e) {
  Topos::mysqli()->rollback();
  throw $e;
}

if (!Topos::mysqli()->commit())
  Topos::fatal(
    'SERVICE_UNAVAILABLE',
    'Transaction failed: ' . htmlentities( Topos::mysqli()->error )
  );

$url = Topos::urlbase() . 'realms/' . REST::urlencode($TOPOS_REALM) .
    '/pools/' . REST::urlencode($row[1]) . '/tokens/' . $row[0];
$type = REST::best_content_type(
  array(
    'text/plain' => 1.0,
    REST::best_xhtml_type() => 1.0,
  ), REST::best_xhtml_type()
);
$headers = array(
  'status' => 'SEE_OTHER',
  'Location' => $url,
  'Content-Type' => $type . '; charset=UTF-8'
);
if ($lockUUID) {
  $headers['Topos-OpaqueLockToken'] = "opaquelocktoken:$lockUUID";
  $headers['Topos-LockURL'] = Topos::urlbase() . 'realms/' . REST::urlencode($TOPOS_REALM) .
    '/locks/' . $lockUUID;
}
REST::header($headers);
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') exit;
switch ($type) {
  case 'text/plain':
    echo $url;
    break;
  default:
    Topos::start_html('Leased token redirect');
    echo "<p><a href=\"$url\">$row[0]</a></p>";
    Topos::end_html();
}
