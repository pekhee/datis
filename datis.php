#!/usr/bin/env php
<?php
// Dependencies
require_once dirname(__FILE__) . "/inc/functions.php";
require_once dirname(__FILE__) . "/inc/Lite.php";

$help_all = "\n
-c FILE               Saves to, or read from the FILE inseatd of
--config=FILE         default place.

-x [FILE]             Override the Zend XML configuration file.
--xml[=FILE]

-i                    Ignores errors on Zend encoding.
--ignore

-v                    Displays all errors and warnings.
--verbose

-h                    Shows help text for each action and exits.
--help

Other actions:
[push]                Default, Push latest changes to server
db                    Backup and restore SQL files to Mysql.
config                Creates the index.php config file and uploads.
account               Creates new cPanel account and domain name.
errorlog              Shows errorlog file on the server. \n";

// Get Arguments
$args = parseArgs();
$action1 = @$args[0];
$action2 = @$args[1];
unset($args[0]);
unset($args[2]);

error_reporting(0);
echo "\033[37m"; // Changes color to white

// Load Global Congiguratoin File
if (file_exists(dirname(__FILE__) . '/config.ini')) {
    $config = new Config_Lite(dirname(__FILE__) . '/config.ini');
} else {
    echo "Configuration file not found at " . dirname(__FILE__) . "/config.ini ! \n";
    bye();
}

// Know where we are, gives full path to the current directory
$pwd = getenv("PWD");

// Go to the current directory
chdir($pwd);

/**
 * GET GLOBAL OPTIONS
 */

foreach ($args as $key => $value) {
    switch ($key) {
    case 'c':
    case 'config':
        $config_file = $value;
        break;
    case 'v':
    case 'verbose':
        error_reporting(- 1);
        $verbose = true;
        break;
    case 'x':
    case 'xml':
        $xml_file = $value;
        break;
    case 'i':
    case 'ignore':
        $ignore_errors = true;
        break;
    }
}

// Override config using -c option
$config_file = (isset($config_file)) ? $config_file : $config['config_dir'] . '/' . $config['config'];

if (! file_exists($config_file) && $action1 != 'init' && $action1 != 'help' && $action1 != 'account' && $action1 != 'install') {
    echo "Config file was not found at '$config_file'\nTry using 'help' or 'init', or '-c' option to override conffile.\n";
    bye();
} else {
    $info = new config_lite($config_file);
}

// Create the temp directories
Files::del_temp();
Files::create_temp();

// Modify zend xml config
if ($action1 != 'errorlog' && $action1 != 'init') {
    if (isset($xml_file)) {
        $xml_conf = $xml_file;
    } elseif (isset($info['global']['guard'])) {
        $xml_conf = $info['global']['guard'];
    } else {
        $xml_conf = $config['zend_conf'];
    }
	Zend::modify_zend_xml($xml_conf, @$ignore_errors);
}

// Setting the default
$action1  = ($action1=='') ? 'push' : $action1;
// Action!
require  dirname(__FILE__) . "/mods/{$action1}.php";
// Remove the temp directory
Files::del_tree($pwd . '/' . $config['temp']);
