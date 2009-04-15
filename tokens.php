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
$escPool  = Topos::escape_string($TOPOS_POOL);

if ( !in_array( $_SERVER['REQUEST_METHOD'],
                array('HEAD', 'GET') ) )
  Topos::fatal('METHOD_NOT_ALLOWED');

$result = Topos::query(<<<EOS
SELECT `tokenId`, LENGTH(`tokenValue`)
FROM `Pools` NATURAL JOIN `Tokens`
WHERE `realmName` = {$escRealm}
  AND `poolName`  = {$escPool}
ORDER BY 1;
EOS
);

$directory = ToposDirectory::factory();
while ($row = $result->fetch_row())
  $directory->line(
    $row[0], $row[1] . ' bytes',
    '<form action="' . $row[0] . '?http_method=DELETE" method="post"><input type="submit" value="Delete this token" /></form>'
  );
$directory->end();
