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
