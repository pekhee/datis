<?php
/*
 * =======================================================================
 * CONFIG
 * =======================================================================
 */
/**
 * HELP FOR CONFIG
 */

$help = "Usage: [OPTION]

Options:";

/**
 * GET OPTIONS FOR CONFIG
 */

foreach ($args as $key => $value) {
    switch ($key) {
    case 'h':
    case 'help':
        echo $help . $help_all;
        bye();
        break;
    }
}

/**
 * START FOR CONFIG
 */

if ($info['db']['username'] == '' || $info['db']['password'] == '' || $info['db']['dbname'] == '') {
    echo FAIL . ": Database credentials are not in {$config_file}.\n         Create a database first using 'db create'\n";
    break;
}

$new_config['user'] = $info['db']['username'];
$new_config['password'] = $info['db']['password'];
$new_config['server'] = 'localhost';
$new_config['dbname'] = $info['db']['dbname'];
$new_config['prefix'] = 'pre_';
$serialize = base64_encode(serialize($new_config));

$data = "<?php
error_reporting(E_ALL);
\$config = '{$serialize}';
\$cid = 1;";

if (file_put_contents($pwd . '/' . $config['temp'] . '/main/index.php', $data)) {
    echo SUCCESS . ": Config file generated.\n";
} else {
    echo FAIL . ": Config file NOT generated !\n";
    bye();
}

// Zend the file
if (!Zend::zend_guard()) {
	break;
}

$ftp = new Ftp($info);
if (!$ftp->connect()) {
	bye();
}

// Rename the old file
$ftp->create_old('/inc/index.php');

// Upload index.php
$ftp->put($pwd . '/' . $config['temp'] . '/zend/main/index.php', '/inc/index.php', false);
