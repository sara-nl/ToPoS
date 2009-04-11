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


if (!in_array($_SERVER['REQUEST_METHOD'], array('HEAD', 'GET')))
  Topos::fatal('METHOD_NOT_ALLOWED');

$result = Topos::query(<<<EOS
SELECT `tokenLockUUID`
FROM `Pools` NATURAL JOIN `Tokens`
WHERE `realmName` = {$escRealm} AND `tokenLockTimeout` > UNIX_TIMESTAMP()
ORDER BY 1;
EOS
);

REST::header(array(
  'Content-Type' => REST::best_xhtml_type() . '; charset=UTF-8',
  'Cache-Control' => 'no-cache',
));
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') exit;
Topos::start_html('Locks');
?><h1>Directory index</h1><?php
Topos::directory_list_start();
while ($row = $result->fetch_row())
  Topos::directory_list_line(array(
    'name' => $row[0],
    'desc' => 'A token lock'
  ));
Topos::directory_list_end();
Topos::end_html();

?>