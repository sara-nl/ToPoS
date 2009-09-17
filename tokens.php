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

// Handle the creation of a number of tokens, set by the user.
if ( $_SERVER['REQUEST_METHOD'] === 'POST' &&
     strpos( @$_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded' ) === 0 ) {
  if ( !is_string(@$_POST['tokens']) )
    REST::fatal(REST::HTTP_BAD_REQUEST, 'Missing required parameter "tokens"');
  $tokens = preg_split('/[^\\d]+/', $_POST['tokens'], -1, PREG_SPLIT_NO_EMPTY);
//  if (empty($tokens))
//    REST::fatal(REST::HTTP_BAD_REQUEST, 'Illegal value for parameter "tokens" (1)');
  if ( !empty($_POST['pool']) ) {
    $tgtPool = Topos::poolId($TOPOS_POOL);
    $srcPool = Topos::poolId($_POST['pool']);
    $tokenIds = count($tokens)
      ? 'AND `tokenId` IN (' . implode(',', $tokens) . ')'
      : '';
    Topos::real_query(<<<EOS
UPDATE `Tokens`
SET `poolId` = $tgtPool
WHERE `poolId` = $srcPool
      $tokenIds;
EOS
    );
    REST::fatal(
      REST::HTTP_OK,
      Topos::mysqli()->affected_rows . ' tokens moved'
    );
  } elseif ( count($tokens) == 1 ) {
    $tokens = $tokens[0];
    if ( !$tokens || $tokens > 1000000 )
      REST::fatal(REST::HTTP_BAD_REQUEST, 'Illegal value for parameter "tokens" (2)');
    Topos::real_query(
      "CALL `createTokens`({$escPool}, {$tokens});"
    );
    REST::fatal(REST::HTTP_ACCEPTED);
  } else {
    REST::fatal(REST::HTTP_BAD_REQUEST, 'Illegal value for parameter "tokens" (3)');
  }
}

// Handle upload of multiple tokens in a multipart/form-data request body:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Check if we have the right mime type:
  if ( strpos( @$_SERVER['CONTENT_TYPE'], 'multipart/form-data' ) !== 0 )
    REST::fatal(REST::HTTP_UNSUPPORTED_MEDIA_TYPE);
  // For this operation, we need MySQL transactions.
  Topos::real_query('START TRANSACTION;');
  try {
    Topos::real_query('SET foreign_key_checks = 0;');
    $t_upload_map = array();
    if (!empty($_FILES)) {
      $poolId = Topos::poolId($TOPOS_POOL);
      $query1 = <<<EOS
INSERT INTO `TokenValues` (
  `tokenValue`
) VALUES (?);
EOS;
      $query2 = <<<EOS
INSERT INTO `Tokens` (
  `tokenId`, `poolId`, `tokenName`, `tokenType`, `tokenLength`, `tokenCreated`
) VALUES (?, {$poolId}, ?, ?, ?, UNIX_TIMESTAMP());
EOS;
      $stmt1 = Topos::mysqli()->prepare($query1);
      $stmt2 = Topos::mysqli()->prepare($query2);
      $bindTokenValue = $bindTokenId = $bindTokenType = $bindTokenLength = $bindTokenName = null;
      $stmt1->bind_param("b", $bindTokenValue);
      $stmt2->bind_param("issi", $bindTokenId, $bindTokenName, $bindTokenType, $bindTokenLength);
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
              "Errno {$file['error'][$key]} occured during file upload."
            );
          $stream = fopen( $file['tmp_name'][$key], 'r' );
          while ( !feof($stream) )
            $stmt1->send_long_data( 0, fread( $stream, 8192 ) );
          fclose($stream);
          if ( !$stmt1->execute() ) {
            Topos::mysqli()->rollback();
            REST::fatal(REST::HTTP_INTERNAL_SERVER_ERROR, 'X' .$stmt1->error);
          }
          $bindTokenId = $stmt1->insert_id;
          
          $bindTokenName = empty($filename) ? '' : $filename;
          $bindTokenType = empty($file['type'][$key])
            ? 'application/octet-stream' : $file['type'][$key];
          $bindTokenLength = $file['size'][$key];
          if ( !$stmt2->execute() ) {
            Topos::mysqli()->rollback();
            REST::fatal(REST::HTTP_INTERNAL_SERVER_ERROR, 'Y'.$stmt2->error);
          }
//          $t_upload_map[$stmt1->insert_id] = array(
//            'Original Name' => $bindTokenName,
//            'Content-Type' => $bindTokenType,
//            'Content-Length' => $bindTokenLength
//          );
        } // foreach()
      } // foreach()
    } // if()
    Topos::real_query('SET foreign_key_checks = 1;');
  } // try
  catch (Topos_MySQL $e) {
    Topos::mysqli()->rollback();
    throw $e;
  }
  if (!Topos::mysqli()->commit())
    REST::fatal(
      REST::HTTP_SERVICE_UNAVAILABLE,
      'Transaction failed: ' . htmlentities(Topos::mysqli()->error)
    );

  REST::fatal(REST::HTTP_ACCEPTED);
}


REST::require_method('HEAD', 'GET');

$query = <<<EOS
SELECT `tokenId`,
       `tokenLength`,
       `tokenType`,
       `tokenName`,
       `tokenLockUUID`,
       `tokenLockTimeout` - UNIX_TIMESTAMP(),
       `tokenLockDescription`,
       `tokenLeases`,
       `tokenCreated`
FROM `Pools` NATURAL JOIN `Tokens`
WHERE `poolName`  = {$escPool}
ORDER BY 1;
EOS;
$result = Topos::query($query);

$form = <<<EOS
<h2>Forms</h2>
<h3>Populate this pool</h3>
<form action="./" method="post">
# tokens: <input type="text" name="tokens"/>
<input type="submit" value="Populate"/>
</form>
<h3>Move tokens to this pool</h3>
<form action="./" method="post">
<p>Source pool:<input type="text" name="pool"/><br/>
Token IDs: <input type="text" name="tokens"/> (IDs separated by anything)<br/>
<input type="submit" value="Move"/></p>
</form>
<h3>Create tokens</h3>
<form action="./" method="post" enctype="multipart/form-data">
<input type="file" name="create[]" /> File 1<br />
<input type="file" name="create[]" /> File 2<br />
<input type="file" name="create[]" /> File <i>n</i><br />
<input type="submit" value="Post file(s)" />
</form>
EOS;
$directory = RESTDir::factory('Tokens')->setForm($form);
header('X-Token-Count: ' . $result->num_rows);

while ($row = $result->fetch_row())
  $directory->line(
    $row[0], array(
      'Size' => $row[1] . ' B',
      'Content-Type' => $row[2],
      'Original Name' => $row[3],
      'LockTokenHTML' => ($row[5] > 0 ? "<a href=\"../locks/{$row[4]}\">{$row[4]}</a>" : ''),
      'Timeout' => (
        $row[5] > 0
        ? sprintf( '%d:%02d:%02d',
                   ($row[5] / 3600),
                   ($row[5] / 60 % 60),
                   ($row[5] % 60)
          )
        : ''
      ),
      'Created' => Topos::sortable_date( $row[8] ),
      'Leases' => $row[7],
      'LockDescription' => ($row[5] > 0 ? $row[6] : ''),
    )
  );
$directory->end();
