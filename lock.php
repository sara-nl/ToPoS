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
$escLockUUID = Topos::escape_string($TOPOS_POOL);

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
  Topos::real_query(<<<EOS
UPDATE `Tokens`
SET `tokenLockTimeout` = 0, `tokenLockUUID` = null
WHERE `tokenLockUUID` = {$escLockUUID};
EOS
  );
  if (Topos::mysqli()->affected_rows) {
    Topos::log('unlock', array(
      'realmName' => $TOPOS_REALM,
      'UUID' => $TOPOS_POOL,
    ));
    REST::header(array(
      'Content-Type' => REST::best_xhtml_type() . '; charset=UTF-8'
    ));
    Topos::start_html('Lock destroyed');
    echo '<p>Lock successfully destroyed.</p>';
    Topos::end_html();
    exit;
  } else {
    Topos::fatal('NOT_FOUND');
  }
}

if ( !in_array( $_SERVER['REQUEST_METHOD'],
                array('HEAD', 'GET') ) )
  Topos::fatal('METHOD_NOT_ALLOWED');

if ( !empty($_GET['timeout']) &&
     ($timeout = (int)($_GET['timeout'])) > 0 ) {
  Topos::real_query(<<<EOS
UPDATE `Tokens`
SET `tokenLockTimeout` = UNIX_TIMESTAMP() + {$timeout}
WHERE `tokenLockUUID` = {$escLockUUID}
  AND `tokenLockTimeout` > UNIX_TIMESTAMP();
EOS
  );
  if (!Topos::mysqli()->affected_rows)
    Topos::fatal('NOT_FOUND');
}

$result = Topos::query(<<<EOS
SELECT `poolName`, `tokenId`, `tokenLockTimeout` - UNIX_TIMESTAMP()
FROM `Pools` NATURAL JOIN `Tokens`
WHERE `tokenLockUUID` = $escLockUUID
  AND `tokenLockTimeout` > UNIX_TIMESTAMP();
EOS
);
if (!($row = $result->fetch_row()))
  Topos::fatal('NOT_FOUND');
$tokenURL = Topos::urlbase() . 'realms/' . REST::urlencode($TOPOS_REALM) .
  '/pools/' . REST::urlencode($row[0]) . '/tokens/' . $row[1];
  
$bct = REST::best_content_type(
  array('text/html' => 1,
        'application/xhtml+xml' => 1,
        'text/plain' => 1), 'text/html'
);
if ($bct === 'text/plain') {
  REST::header(array(
    'Content-Type' => 'text/plain; charset=US-ASCII',
    'Cache-Control' => 'no-cache',
  ));
  if ($_SERVER['REQUEST_METHOD'] === 'HEAD') exit;
  echo <<<EOS
PoolName: {$row[0]}
TokenId: {$row[1]}
TokenURL: $tokenURL
Timeout: {$row[2]}
EOS;
  exit;
}

REST::header(array(
  'Content-Type' => REST::best_xhtml_type() . '; charset=UTF-8',
  'Cache-Control' => 'no-cache',
));
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') exit;
Topos::start_html('Lock info');
?><h1>Delete</h1>
<form action="<?php echo $TOPOS_POOL; ?>?http_method=DELETE" method="post">
<input type="submit" value="Delete this lock"/>
</form>
<h1>Lock info</h1>
<table class="lockinfo"><tbody>
<tr><th>PoolName:</th><td id="poolName"><?php echo htmlentities($row[0]); ?></td></tr>
<tr><th>TokenId:</th><td id="tokenId"><?php echo htmlentities($row[1]); ?></td></tr>
<tr><th>TokenURL:</th><td id="tokenURL"><a href="<?php echo $tokenURL; ?>"><?php echo $tokenURL; ?></a></td></tr>
<tr><th>Timeout:</th><td id="timeout"><?php echo htmlentities($row[2]); ?></td></tr>
</tbody></table><?php
Topos::end_html();
