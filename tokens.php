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
$poolId = Topos::poolId($TOPOS_POOL);

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
//  Topos::real_query('START TRANSACTION;');
  Topos::real_query(<<<EOS
DELETE `Pools`, `Tokens`
FROM `Pools` NATURAL LEFT JOIN `Tokens`
WHERE `Pools`.`poolName` = {$escPool};
EOS
  );
  REST::fatal(
    REST::HTTP_OK,
    'Pool destroyed successfully.'
  );
}

// Handle the creation of a number of tokens, set by the user.
$input = NULL;
if ( $_SERVER['REQUEST_METHOD'] === 'POST' &&
     strpos( @$_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded' ) === 0 ) {
  if ( isset( $_POST['pool'] ) ) {
    if ( $_POST['pool'] === '' )
      REST::fatal(
        REST::HTTP_BAD_REQUEST,
        'Empty parameter "pool"'
      );
    $tokens = isset( $_POST['tokens'] )
      ? preg_split('/[^\\d]+/', $_POST['tokens'], -1, PREG_SPLIT_NO_EMPTY)
      : array();
    $tgtPool = $poolId;
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
  } elseif ( isset( $_POST['ntokens'] ) ) {
    $offset = (int)(@$_POST['offset']);
    $ntokens = (int)($_POST['ntokens']);
    if ($ntokens <= 0 || $ntokens > 1000000)
      REST::fatal(
        REST::HTTP_BAD_REQUEST,
        'Bad values for "ntokens" or "offset"'
      );
    Topos::real_query(
      "CALL `createTokens`({$escPool}, {$ntokens}, {$offset});"
    );
    REST::fatal(REST::HTTP_ACCEPTED);
  } elseif ( isset( $_POST['tokens'] ) ) {
    $input = tmpfile();
    fwrite( $input, $_POST['tokens'] );
    fseek( $input, 0 );
    $_SERVER['CONTENT_TYPE'] = 'text/plain; charset="UTF-8"';
  } else REST::fatal( REST::HTTP_BAD_REQUEST );
}

// Handle a upload of a single text file, of which each line will be a token.
if ( $_SERVER['REQUEST_METHOD'] === 'POST' &&
     strpos( @$_SERVER['CONTENT_TYPE'], 'text/' ) === 0 ) {
  $esccontenttype = Topos::escape_string($_SERVER['CONTENT_TYPE']);
  if (!$input) $input = REST::inputhandle();
  $stmt1 = Topos::mysqli()->prepare(<<<EOS
INSERT INTO `TokenValues` (
  `tokenValue`
) VALUES (?);
EOS
  );
  $stmt2 = Topos::mysqli()->prepare(<<<EOS
INSERT INTO `Tokens` (
  `tokenId`, `poolId`, `tokenType`, `tokenLength`, `tokenCreated`
) VALUES (?, {$poolId}, {$esccontenttype}, ?, UNIX_TIMESTAMP());
EOS
  );
  $bindTokenValue = $bindTokenId = $bindTokenLength = null;
  $stmt1->bind_param("s", $bindTokenValue);
  $stmt2->bind_param("ii", $bindTokenId, $bindTokenLength);
  ini_set('auto_detect_line_endings', 1);
  while ( ( $line = fgets( $input ) ) ) {
    $bindTokenValue = rtrim( $line, "\r\n" );
    if ( !$stmt1->execute() )
      REST::fatal(
        REST::HTTP_INTERNAL_SERVER_ERROR,
        'stmt1: ' . $stmt1->error
      );
    $bindTokenId = $stmt1->insert_id;
    $bindTokenLength = strlen($bindTokenValue);
    if ( !$stmt2->execute() )
      REST::fatal(
        REST::HTTP_INTERNAL_SERVER_ERROR,
        'stmt2: ' . $stmt2->error
      );
  }
  REST::fatal(REST::HTTP_ACCEPTED);
}

// Handle upload of multiple tokens in a multipart/form-data request body:
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
  // Check if we have the right mime type:
  if ( strpos( @$_SERVER['CONTENT_TYPE'], 'multipart/form-data' ) !== 0 )
    REST::fatal(REST::HTTP_UNSUPPORTED_MEDIA_TYPE);
  if (empty($_FILES))
    REST::fatal(REST::HTTP_BAD_REQUEST, "Nothing to process");
  
  $stmt1 = Topos::mysqli()->prepare(<<<EOS
INSERT INTO `TokenValues` (
  `tokenValue`
) VALUES (?);
EOS
  );
  $stmt2 = Topos::mysqli()->prepare(<<<EOS
INSERT INTO `Tokens` (
  `tokenId`, `poolId`, `tokenName`, `tokenType`, `tokenLength`, `tokenCreated`
) VALUES (?, {$poolId}, ?, ?, ?, UNIX_TIMESTAMP());
EOS
  );
  $bindTokenValue = $bindTokenId = $bindTokenType = $bindTokenLength = $bindTokenName = null;
  $stmt1->bind_param("b", $bindTokenValue);
  $stmt2->bind_param("issi", $bindTokenId, $bindTokenName, $bindTokenType, $bindTokenLength);
  $t_upload_map = array();
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
      if ( !$stmt1->execute() )
        REST::fatal(
          REST::HTTP_INTERNAL_SERVER_ERROR,
          'stmt1: ' . $stmt1->error
        );
      $t_upload_map[$paramname][$key] = $stmt1->insert_id;
    } // foreach ( $file['name'] as $key => $filename )
  } // foreach ($_FILES as $paramname => $file)
  // For this operation, we need MySQL transactions.
  Topos::real_query('START TRANSACTION;');
  try {
    foreach ($_FILES as $paramname => $file) {
      foreach ( $file['name'] as $key => $filename ) {
        if (isset($t_upload_map[$paramname][$key])) {
          $bindTokenId = $t_upload_map[$paramname][$key];
          $bindTokenName = empty($filename) ? '' : $filename;
          $bindTokenType = empty($file['type'][$key])
            ? 'application/octet-stream' : $file['type'][$key];
          $bindTokenLength = $file['size'][$key];
          if ( !$stmt2->execute() ) {
            Topos::mysqli()->rollback();
            REST::fatal(
              REST::HTTP_INTERNAL_SERVER_ERROR,
              'stmt2: ' . $stmt2->error
            );
          } // if ( !$stmt2->execute() )
        } // if (isset($t_upload_map[$paramname][$key]))
      } // foreach ( $file['name'] as $key => $filename )
    } // foreach ($_FILES as $paramname => $file)
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
FROM `Tokens`
WHERE `poolId`  = {$poolId}
ORDER BY 1;
EOS;
$result = Topos::query($query);

$form = <<<EOS
<h2>Forms</h2>
<h3>Populate this pool with numbers</h3>
<form action="./" method="post">
<input type="text" name="ntokens"/> #tokens<br/>
<input type="text" name="offset"/> offset<br/>
<input type="submit" value="Populate"/>
</form>
<h3>Move tokens into this pool</h3>
<form action="./" method="post">
<input type="text" name="pool"/> Source pool name<br/>
<input type="text" name="tokens"/> Token IDs (separated by anything)<br/>
<input type="submit" value="Move"/>
</form>
<h3>Create tokens from a parameter list</h3>
<form action="./" method="post">
<textarea name="tokens"></textarea><br/>
<input type="submit" value="Create"/>
</form>
<h3>Create tokens from files</h3>
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
