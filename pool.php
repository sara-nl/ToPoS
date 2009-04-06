<?php

/*·************************************************************************
 * Copyright © 2008 by SARA Computing and Networking Services             *
 * pieterb@sara.nl                                                        *
 **************************************************************************/

require_once('include/global.php');

if ( $_SERVER['REQUEST_METHOD'] === 'POST' &&
     strpos( @$_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded' ) === 0 ) {
  $_POST['pool'] = $TOPOS_POOL;
  require_once('realm.php');
  exit;
}

$escRealm = Topos::escape_string($TOPOS_REALM);
$escPool = Topos::escape_string($TOPOS_POOL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Check if we have the right mime type:
  if ( strpos( @$_SERVER['CONTENT_TYPE'], 'multipart/form-data' ) !== 0 )
    Topos::fatal('UNSUPPORTED_MEDIA_TYPE');
  // For this operation, we need MySQL transactions.
  Topos::real_query('START TRANSACTION;');
  try {
    if (isset($_POST['delete'])) {
      if (!is_array($_POST['delete']))
        $_POST['delete'] = preg_split(
          '/[\\D]+/', $_POST['delete'], -1, PREG_SPLIT_NO_EMPTY
        );
      foreach ( $_POST['delete'] as $delete )
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
        }
    }
    $t_upload_map = array();
    if (!empty($_FILES)) {
      $poolId = Topos::poolId($TOPOS_REALM, $TOPOS_POOL);
      $stmt = Topos::mysqli()->prepare(<<<EOS
INSERT INTO `Tokens` (
  `poolId`, `tokenValue`, `tokenType`, `tokenCreated`
) VALUES ({$poolId}, ?, ?, UNIX_TIMESTAMP());
EOS
      );
      $bindTokenValue = $bindTokenType = null;
      $stmt->bind_param("bs", $bindTokenValue, $bindTokenType);
      foreach ($_FILES as $paramname => $file) {
        if (!is_array( $file['error'] ) ) {
          $file['name'    ] = array( $file['name'    ] );
          $file['error'   ] = array( $file['error'   ] );
          $file['type'    ] = array( $file['type'    ] );
          $file['tmp_name'] = array( $file['tmp_name'] );
          $file['size'    ] = array( $file['size'    ] );
        }
        foreach ( $file['name'] as $key => $filename ) {
          if ( $file['error'][$key] === UPLOAD_ERR_NO_FILE )
            continue;
          if ( $file['error'][$key] !== UPLOAD_ERR_OK )
            Topos::fatal(
              'BAD_REQUEST',
              htmlentities("Errno {$file['error'][$key]} occured during file upload.")
            );
          $bindTokenType = @$file['type'][$key];
          if (empty($bindTokenType))
            $bindTokenType = 'application/octet-stream';
          $stream = fopen( $file['tmp_name'][$key], 'r' );
          while ( !feof($stream) )
            $stmt->send_long_data( 0, fread( $stream, 8192 ) );
          fclose($stream);
          if ( !$stmt->execute() ) {
            Topos::mysqli()->rollback();
            Topos::fatal('INTERNAL_SERVER_ERROR', $stmt->error);
          }
          $t_upload_map[$stmt->insert_id] = empty($filename) ? $paramname : $filename;
          Topos::log('create', array(
            'realmName' => $TOPOS_REALM,
            'poolName'  => $TOPOS_POOL,
            'tokenId'   => $stmt->insert_id,
          ));
        } // foreach()
      } // foreach()
    } // if()
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

  $bct = REST::best_content_type(
    array( 'text/html' => 1,
           'application/xhtml+xml' => 1,
           'text/plain' => 1,
           'text/tdv' => 1,
           'text/csv' => 1 ), 'text/html'
  );
  REST::header(array(
    'Content-Type' => $bct . '; charset=UTF-8',
  ));
  if ($_SERVER['REQUEST_METHOD'] === 'HEAD') exit;
  if ( in_array( $bct, array( 'text/plain', 'text/tdv' ) ) ) {
    foreach ($t_upload_map as $tokenId => $name)
      echo $tokenId . "\t" . $name . "\n";
    exit;
  }
  if ( $bct === 'text/csv' ) {
    foreach ($t_upload_map as $tokenId => $name)
      echo $tokenId . ',"' . str_replace('"', '""', $name) . "\"\r\n";
    exit;
  }

  Topos::start_html('Pool');
  echo '<p>Tokens created/destroyed successfully.</p>' .
       '<p><a href="./" rel="index">Back to pool</a></p>';
  if (!empty($t_upload_map)) {
    echo '<p>The following tokens have been created:</p><ul>';
    foreach ($t_upload_map as $tokenId => $name)
      echo '<li><a href="tokens/' . $tokenId . '">' . htmlentities($name) . "</a></li>\n";
    echo '</ul>';
  }
  Topos::end_html();
  exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
  Topos::real_query('START TRANSACTION;');
  try {
    Topos::real_query(<<<EOS
DELETE `Tokens`.* FROM `Tokens` NATURAL JOIN `Pools`
WHERE `Pools`.`realmName` = {$escRealm}
  AND `Pools`.`poolName` = {$escPool};
EOS
    );
    Topos::log('delete', array(
      'realmName' => $TOPOS_REALM,
      'poolName' => $TOPOS_POOL,
      'tokens' => Topos::mysqli()->affected_rows
    ));
  }
  catch (Topos_MySQL $e) {
    Topos::mysqli()->rollback();
    throw $e;
  }
  if (!Topos::mysqli()->commit())
    Topos::fatal(
      'SERVICE_UNAVAILABLE',
      'Transaction failed: ' . htmlentities( Topos::mysqli()->error )
    );
  REST::header(array(
    'Content-Type' => REST::best_xhtml_type() . '; charset=UTF-8'
  ));
  Topos::start_html('Pool');
  echo '<p>Pool destroyed successfully.</p>';
  Topos::end_html();
  exit;
}


if (!in_array($_SERVER['REQUEST_METHOD'], array('HEAD', 'GET')))
  Topos::fatal('METHOD_NOT_ALLOWED');
if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
  Topos::fatal('NOT_MODIFIED');

REST::header(array(
  'Content-Type' => REST::best_xhtml_type() . '; charset=UTF-8',
  'Last-Modified' => REST::http_date(0),
));
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') exit;
Topos::start_html('Pool');
?><h1>Forms</h1>
<h2>Delete</h2>
<form action="./?http_method=DELETE" method="post">
<input type="submit" value="Delete this pool"/>
</form>
<h2>Populate this pool</h2>
<form action="./" method="post">
# tokens: <input type="text" name="tokens"/>
<input type="hidden" name="pool" value="<?php echo htmlentities($TOPOS_POOL); ?>"/>
<input type="submit" value="Populate"/>
</form>
<h2>Create and delete tokens in one atomic transaction</h2>
<form action="./" method="post" enctype="multipart/form-data">
<h3>Tokens to delete</h3>
<input type="text" name="delete[]" /> Token 1<br />
<input type="text" name="delete[]" /> Token 2<br />
<input type="text" name="delete[]" /> Token <i>n</i><br />
<h3>Tokens to create</h3>
<input type="file" name="create[]" /> File 1<br />
<input type="file" name="create[]" /> File 2<br />
<input type="file" name="create[]" /> File <i>n</i><br />
<input type="submit" value="Post file(s)" />
</form>
<h2>Getting the next token</h2>
<form action="nextToken" method="get">
<input type="text" name="token"/> Token value RegExp<br/>
<input type="text" name="timeout"/> Timeout in seconds (leave empty for shared tokens)<br/>
<input type="submit" value="Get next token"/>
</form>
<h1>Directory index</h1><?php
Topos::directory_list(array(
  array(
    'name' => 'tokens/',
    'desc' => 'Tokens directory',
  ),
  array(
    'name' => 'nextToken',
    'desc' => 'Redirects to the next available token',
  ),
  array(
    'name' => 'progress',
    'desc' => 'Jan Bot\'s progress bar',
  )
));
Topos::end_html();

?>