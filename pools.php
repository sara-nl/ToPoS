<?php

/*·************************************************************************
 * Copyright © 2008 by SARA Computing and Networking Services             *
 * pieterb@sara.nl                                                        *
 **************************************************************************/

require_once('include/global.php');
require_once('include/directory.php');

$escRealm = Topos::escape_string($TOPOS_REALM);


if (!in_array($_SERVER['REQUEST_METHOD'], array('HEAD', 'GET')))
  Topos::fatal('METHOD_NOT_ALLOWED');

$result = Topos::query(<<<EOS
SELECT `poolName`, COUNT(*)
FROM `Pools` NATURAL JOIN `Tokens`
WHERE `realmName` = {$escRealm}
GROUP BY `poolId`
ORDER BY 1;
EOS
);

$directory = ToposDirectory::factory();
while ($row = $result->fetch_row())
  $directory->line( $row[0] . '/', $row[1] . ' tokens', 'A token pool directory');
$directory->end();
