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

if ( $_SERVER['REQUEST_METHOD'] === 'POST' &&
     strpos( @$_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded' ) === 0 ) {
  if ( empty($_POST['tokens']) )
    REST::fatal(REST::HTTP_BAD_REQUEST, 'Missing one or more required parameters');
  $tokens = (int)($_POST['tokens']);
  if ( !preg_match('/^[\\w\\-.]+$/', $TOPOS_POOL) ||
       !$tokens || $tokens > 1000000)
    REST::fatal(REST::HTTP_BAD_REQUEST, 'Illegal parameter value(s)');
  $escPoolName = Topos::escape_string($TOPOS_POOL);
  Topos::real_query(
    "CALL `createTokens`({$escRealm}, {$escPoolName}, {$tokens});"
  );
  Topos::log('populate', array(
    'realmName' => $TOPOS_REALM,
    'poolName' => $TOPOS_POOL,
    'tokens' => $tokens
  ));
  REST::header(array(
    'Content-Type' => REST::best_xhtml_type() . '; charset=UTF-8'
  ));
  echo REST::html_start('Realm');
  echo '<p>Pool populated successfully.</p>' .
       '<p><a href="./" rel="index">Back</a></p>';
  echo REST::html_end();
  exit;
}

$escPool = Topos::escape_string($TOPOS_POOL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Check if we have the right mime type:
  if ( strpos( @$_SERVER['CONTENT_TYPE'], 'multipart/form-data' ) !== 0 )
    REST::fatal(REST::HTTP_UNSUPPORTED_MEDIA_TYPE);
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
            REST::fatal(REST::HTTP_NOT_FOUND, "Token $deleteTokenId not found");
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
  `poolId`, `tokenValue`, `tokenType`, `tokenLength`, `tokenCreated`
) VALUES ({$poolId}, ?, ?, ?, UNIX_TIMESTAMP());
EOS
      );
      $bindTokenValue = $bindTokenType = $bindTokenLength = null;
      $stmt->bind_param("bsi", $bindTokenValue, $bindTokenType, $bindTokenLength);
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
            REST::fatal(
              REST::HTTP_BAD_REQUEST,
              htmlentities("Errno {$file['error'][$key]} occured during file upload.")
            );
          $bindTokenType = @$file['type'][$key];
          if (empty($bindTokenType))
            $bindTokenType = 'application/octet-stream';
          $bindTokenLength = $file['size'][$key];
          $stream = fopen( $file['tmp_name'][$key], 'r' );
          while ( !feof($stream) )
            $stmt->send_long_data( 0, fread( $stream, 8192 ) );
          fclose($stream);
          if ( !$stmt->execute() ) {
            Topos::mysqli()->rollback();
            REST::fatal(REST::HTTP_INTERNAL_SERVER_ERROR, $stmt->error);
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
    REST::fatal(
      REST::HTTP_SERVICE_UNAVAILABLE,
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

  echo REST::html_start('Pool');
  echo '<p>Tokens created/destroyed successfully.</p>' .
       '<p><a href="./" rel="index">Back to pool</a></p>';
  if (!empty($t_upload_map)) {
    echo '<p>The following tokens have been created:</p><ul>';
    foreach ($t_upload_map as $tokenId => $name)
      echo '<li><a href="tokens/' . $tokenId . '">' . htmlentities($name) . "</a></li>\n";
    echo '</ul>';
  }
  echo REST::html_end();
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
    REST::fatal(
      REST::HTTP_SERVICE_UNAVAILABLE,
      'Transaction failed: ' . htmlentities( Topos::mysqli()->error )
    );
  REST::header(array(
    'Content-Type' => REST::best_xhtml_type() . '; charset=UTF-8'
  ));
  echo REST::html_start('Pool');
  echo '<p>Pool destroyed successfully.</p>';
  echo REST::html_end();
  exit;
}


if (!in_array($_SERVER['REQUEST_METHOD'], array('HEAD', 'GET')))
  REST::fatal(REST::HTTP_METHOD_NOT_ALLOWED);
if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
  REST::fatal(REST::HTTP_NOT_MODIFIED);

$t_pool = htmlentities($TOPOS_POOL);
$directory = ToposDirectory::factory(<<<EOS
<h1>Forms</h1>
<h2>Delete</h2>
<form action="./?http_method=DELETE" method="post">
<input type="submit" value="Delete this pool"/>
</form>
<h2>Populate this pool</h2>
<form action="./" method="post">
# tokens: <input type="text" name="tokens"/>
<input type="hidden" name="pool" value="{$t_pool}"/>
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
EOS
);
$directory->line('tokens/');
$directory->line('nextToken');
$directory->line('progress', '', 'a progress bar');
$directory->end();
