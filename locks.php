<?php

/*·************************************************************************
 * Copyright © 2008 by SARA Computing and Networking Services             *
 * pieterb@sara.nl                                                        *
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