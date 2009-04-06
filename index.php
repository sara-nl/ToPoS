<?php

/*·************************************************************************
 * Copyright © 2008 by SARA Computing and Networking Services             *
 * pieterb@sara.nl                                                        *
 **************************************************************************/

require_once( 'include/directory.php' );

if (!in_array($_SERVER['REQUEST_METHOD'], array('HEAD', 'GET')))
  Topos::fatal('METHOD_NOT_ALLOWED');
if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
  Topos::fatal('NOT_MODIFIED');
  
$directory = ToposDirectory::factory();
$directory->line('realms/', '', 'A list of all realms. Forbidden for most users, for security reasons.');
$directory->line('newRealm', '', 'Redirects to a new, empty realm.');
$directory->line('topos3_reference_manual.pdf', filesize('topos3_reference_manual.pdf') . ' bytes', 'The official reference manual for this version of ToPoS.');
$directory->end();
