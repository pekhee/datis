<?php

// Create account
//include dirname(__FILE__) . '/../mods/account.php';
//Files::del_temp();

// Create Database
$action2 = 'create';
include dirname(__FILE__) . '/../mods/db.php';
Files::del_temp();

// Upload Files
$args['zip'] = TRUE;
$args['directory'] = $pwd;

include dirname(__FILE__) . '/../mods/push.php';

unset($args['directory']);
unset($args['zip']);
Files::del_temp();

// Config Datis
include dirname(__FILE__) . '/../mods/config.php';
Files::del_temp();

// Restore clear datis
$action2 = 'restore';
$args['server'] = 'true';
$args['file'] = $pwd  . '/sql/clear_datis.sql.gz';

include dirname(__FILE__) . '/../mods/db.php';

unset($args['file']);
unset($args['server']);
Files::del_temp();

// Update Revision
