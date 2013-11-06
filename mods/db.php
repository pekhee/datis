<?php
/*
 * =======================================================================
 * DATABASE
 * =======================================================================
 */

/**
 * HELP FOR DATABASE
 */

$help = "Usage: backup|restore|sync|create|getconfig|adduser [OPTION]

Options:

--server              Does all backup and restore on server.

-f FILE               Restore from FILE, or backup to FILE.
--file=FILE

-t <TABLE>            Backup only from TABLE
--table<=TABLE>

-s                    Only backup structure, not data
--structure           *** UPON RESTORE DATA WILL BE LOST***";

/**
 * GET OPTIONS FOR DATABASE
 */

foreach ($args as $key => $value) {
    switch ($key) {
    case 'h':
    case 'help':
        echo $help . $help_all;
        bye();
        break;
    case 'server':
        $local = false;
        break;
    case 'file':
    case 'f':
        $file = $value;
        break;
    case 's':
    case 'structure':
        $structure = true;
        break;
    case 't':
    case 'table':
        $table = $value;
        break;
    }
}

/**
 * START OF DATABASE
 */

$file = (isset($file)) ? $file : $pwd . '/' . $config['sql'];
if (! file_exists($file) && $action2 == 'restore') {
    echo FAIL . ": File '$file' does not exist.\nYou can use -f option to specify a file.\n";
    bye();
}
if (file_exists($file) && $action2 == 'backup') {
    echo WARNING . ": File '$file' exists.\n         You can use -f option to save to another file.\n         Overwrite?(y/*)\n";
    if (str_replace("\n", '', fgets(STDIN)) != 'y') {
        bye();
    }
}

// Workaround
if ($local !== false) {
    $local = true;
}

if ($local === true && $action2 != 'create' && $action2 != NULL && $action2 != 'sync' && $action2 != 'getconfig' && $action2 != 'adduser') {
    copy(dirname(__FILE__) . "/../inc/dump.php", $pwd . '/dump.php');
} elseif (($local !== true && $action2 != 'create' && $action2 != NULL) | $action2 == 'sync' | $action2 == 'getconfig' ) {
    copy(dirname(__FILE__) . "/../inc/dump.php", $pwd . '/' . $config['temp'] . '/main/dump.php');

    // Zend the file
    if (!Zend::zend_guard()) {
		bye();
    }

	$ftp = new Ftp($info);
	if (!$ftp->connect()) {
		bye();
	}
    // Upload dump.php
    if (!$ftp->put($pwd . '/' . $config['temp'] . '/zend/main/dump.php', '/dump.php', false)) {
		bye();
	}
}

switch ($action2) {
case 'sync':
    Db::backup(false); // Backup from server
    Db::local_restore(); // Restore locally
    break;
case 'backup':
    Db::backup($local);
    break;
case 'restore':
    Db::restore($local);
    break;
case 'getconfig':
    $dbconfig = file_get_contents("http://" . $info['ftp']['server'] . "/dump.php?a1=dbinfo&m=" . rand(1, 1000));
	$dbconfig = unserialize(base64_decode($dbconfig));
    if ($dbconfig !== FALSE) {
        $info->set('db', 'username', $dbconfig['user']);
        $info->set('db', 'password', $dbconfig['password']);
        $info->set('db', 'dbname', $dbconfig['dbname']);
        $info->save();
        echo NOTICE . ": Database credentials was saved to {$config_file}.\n";
    } else {
        echo FAIL . ": Failed.\n";
    }
	break;
case 'adduser':
	echo "DATABASE NAME:";
	$config['dbname'] = str_replace("\n", '', fgets(STDIN));
    require dirname(__FILE__) . '/../inc/xmlapi.php';
    $cpanel = new xmlapi($info['ftp']['server']);
    $cpanel->set_debug((isset($verbose)) ? 1 : 0);
    $cpanel->set_output("array");
    $cpanel->set_port(2083);
    $fail = false;

    // Set credentials
    $cpanel->password_auth($info['ftp']['username'], $info['ftp']['password']);

    // Create A User
    $dbpassword = generateRandomString();
	$config['dbusername'] = 'new_dat';
    $result = $cpanel->api1_query(
		$info['ftp']['username'], "Mysql", "adduser",
        array(
            $config['dbusername'],
            $dbpassword
        )
	);
    if (isset($result['error'])) {
        echo FAIL . ": " . $result['error'];
        $fail = true;
    } else {
        echo SUCCESS . ": Mysql user {$info['ftp']['username']}_{$config['dbusername']} created for {$info['ftp']['server']}. \n";
    }

    // Give Permissions
    $result = $cpanel->api1_query(
		$info['ftp']['username'], "Mysql", "adduserdb",
        array(
            $config['dbname'],
            $config['dbusername'],
            'all'
        )
	);
    if (isset($result['error'])) {
        echo FAIL . ": " . $result['error'] . "\n";
        $fail = true;
    } else {
        echo SUCCESS . ": Mysql user {$info['ftp']['username']}_{$config['dbusername']} was given permissions for database. \n";
    }

    if (! $fail) {
        $info->set('db', 'username', $info['ftp']['username'] . '_' . $config['dbusername']);
        $info->set('db', 'password', $dbpassword);
        $info->set('db', 'dbname', $info['ftp']['username'] . '_' . $config['dbname']);
        $info->save();
        echo NOTICE . ": Database credentials was saved to {$config_file}.\n";
    } else {
        echo FAIL . ": Failed.\n";
    }
	break;
case 'create':
    require dirname(__FILE__) . '/../inc/xmlapi.php';
    $cpanel = new xmlapi($info['ftp']['server']);
    $cpanel->set_debug((isset($verbose)) ? 1 : 0);
    $cpanel->set_output("array");
    $cpanel->set_port(2083);
    $fail = false;

    // Set credentials
    $cpanel->password_auth($info['ftp']['username'], $info['ftp']['password']);

    // Create Database
    $result = $cpanel->api1_query(
		$info['ftp']['username'], "Mysql", "adddb",
        array(
            $config['dbname']
        )
	);
    if (isset($result['error'])) {
        echo FAIL . ": " . $result['error'] . "\n";
        $fail = TRUE;
    } else {
        echo SUCCESS . ": Database {$info['ftp']['username']}_{$config['dbname']} created for {$info['ftp']['server']}. \n";
    }

    // Create A User
    $dbpassword = generateRandomString();
    $result = $cpanel->api1_query(
		$info['ftp']['username'], "Mysql", "adduser",
        array(
            $config['dbusername'],
            $dbpassword
        )
	);
    if (isset($result['error'])) {
        echo FAIL . ": " . $result['error'];
        $fail = true;
    } else {
        echo SUCCESS . ": Mysql user {$info['ftp']['username']}_{$config['dbusername']} created for {$info['ftp']['server']}. \n";
    }

    // Give Permissions
    $result = $cpanel->api1_query(
		$info['ftp']['username'], "Mysql", "adduserdb",
        array(
            $config['dbname'],
            $config['dbusername'],
            'all'
        )
	);
    if (isset($result['error'])) {
        echo FAIL . ": " . $result['error'] . "\n";
        $fail = true;
    } else {
        echo SUCCESS . ": Mysql user {$info['ftp']['username']}_{$config['dbusername']} was given permissions for database. \n";
    }

    if (! $fail) {
        $info->set('db', 'username', $info['ftp']['username'] . '_' . $config['dbusername']);
        $info->set('db', 'password', $dbpassword);
        $info->set('db', 'dbname', $info['ftp']['username'] . '_' . $config['dbname']);
        $info->save();
        echo NOTICE . ": Database credentials was saved to {$config_file}.\n";
    } else {
        echo FAIL . ": Failed.\n";
    }
    break;

default:
    echo $help . $help_all;
    break;
}

// Delete uneeded files
if ($local !== true && $action2 != 'create' && $action2 != NULL) {
	$ftp->delete_remaining();
} elseif ($local === true && $action2 != 'create' && $action2 != NULL) {
    unlink($pwd . '/dump.php');
}
