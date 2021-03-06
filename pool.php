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

//Deprecated:
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
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


REST::require_method('HEAD', 'GET');
  
// Fetch number of tokens 
$query = <<<EOS
SELECT COUNT(`tokenId`), SUM(UNIX_TIMESTAMP() < `tokenLockTimeout`)
FROM `Pools` NATURAL JOIN `Tokens`
WHERE `poolName`  = {$escPool};
EOS;
list( $ntokens, $nlocks ) = Topos::query($query)->fetch_row();

$form = <<<EOS
<h2>Forms</h2>
<h3>Getting the next token</h3>
<form action="nextToken" method="get">
<input type="text" name="name"/> Token name search string<br/>
<input type="text" name="timeout"/> Timeout in seconds (leave empty for shared tokens)<br/>
<input type="text" name="description"/> Lock description (leave empty for shared tokens)<br/>
<input type="submit" value="Get next token"/>
</form>
<h3>Progress bar (deprecated)</h3>
<form action="progress" method="get">
<input type="text" name="total"/> Total number of tokens<br/>
<input type="text" name="width" value="300" /> Width<br/>
<input type="submit" value="Show progress bar"/>
</form>
EOS;

$t_pool = htmlentities($TOPOS_POOL);
$directory = RESTDir::factory(
  'Pool "' . htmlspecialchars($TOPOS_POOL, ENT_QUOTES, 'UTF-8') . '"'
)->setForm($form)->setHeaders('Size', 'Description');
$directory->line('tokens/', array('Size' => (int)$ntokens . ' tokens'));
$directory->line('locks/', array('Size' => (int)$nlocks . ' locks'));
$directory->line('nextToken');
#$directory->line('tarball', array('Description' => 'all tokens in a single file'));
$directory->line('progress', array('Description' => 'a progress bar'));
$directory->end();
