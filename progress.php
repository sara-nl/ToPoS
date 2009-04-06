<?php

/*·************************************************************************
 * Copyright © 2008 by SARA Computing and Networking Services             *
 * pieterb@sara.nl                                                        *
 **************************************************************************/

require_once('include/global.php');

$escRealm = Topos::escape_string($TOPOS_REALM);
$escPool = Topos::escape_string($TOPOS_POOL);

if (!in_array($_SERVER['REQUEST_METHOD'], array('HEAD', 'GET')))
  Topos::fatal('METHOD_NOT_ALLOWED');
  
$width = 300;
if (!empty($_GET['width']))
  $width = (int)($_GET['width']);

$result = Topos::query(<<<EOS
SELECT COUNT(*), SUM(`poolName` = {$escPool})
FROM `Tokens` NATURAL JOIN `Pools`
WHERE `realmName` = $escRealm;
EOS
);
$row = $result->fetch_row();
$total = (int)($row[0]);
$done = (int)($row[1]);
if (!empty($_GET['total']))
  $total = (int)($_GET['total']);
if ($total === 0) $total = 1;
$percentage = 100 * $done / $total;

$bct = REST::best_content_type(
  array('text/html' => 1,
        'application/xhtml+xml' => 1,
        'text/plain' => 1), 'text/html'
);
if ($bct === 'text/plain') {
  REST::header(array(
    'Content-Type' => 'text/plain; charset=US-ASCII',
    'Refresh' => '60; ' . $_SERVER['REQUEST_URI'],
    'Cache-Control' => 'no-cache',
  ));
  if ($_SERVER['REQUEST_METHOD'] === 'HEAD') exit;
  echo $done / $total;
  exit;
}

REST::header(array(
  'Content-Type' => REST::best_xhtml_type() . '; charset=UTF-8',
  'Refresh' => '60; ' . $_SERVER['REQUEST_URI'],
  'Cache-Control' => 'no-cache',
));
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') exit;
Topos::start_html('Pool');
?><h1>Progress bar</h1>
<table class="progress"><tbody>
<tr>
  <td style="width: <?php echo $width * $done / $total; ?>pt;" class="done">
  <?php if ($percentage >= 50) echo sprintf('%.1f%%', $percentage); ?>
  </td>
  <td style="width: <?php echo $width - $width * $done / $total; ?>pt;" class="todo">
  <?php if ($percentage < 50) echo sprintf('%.1f%%', $percentage); ?>
  </td>
</tr>
</tbody></table><?php
Topos::end_html();

?>