<?php
/*
 * =======================================================================
 * INIT
 * =======================================================================
 */

/**
 * HELP FOR INIT
 */

$help = "Usage: init [OPTIONS]
Options:";

/**
 * GET OPTIONS FOR INIT
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
 * START OF INIT
 */
// Create directory
mkdir($pwd . '/' . $config['config_dir'], 0755, true);

// Save it to file
$data = new Config_Lite();
$config_file = (isset($config_file)) ? $config_file : $config['config_dir'] . '/' . $config['config'];

// Check if file exists
if (file_exists($config_file)) {
    echo NOTICE . ": " . $config_file . " already exists. You can also use -c option.\nOverwrite? (y/*)\n";
    if (str_replace("\n", '', fgets(STDIN)) != y) {
        bye();
    }
}

$data->setFilename($config_file);

echo 'Directory created: ' . $pwd . '/' . $config['config_dir'] . "\n";

// Get ftp credentials and save it
echo "FTP SERVER: \n";
$ftp_server = str_replace("\n", '', fgets(STDIN));
echo "FTP USERNAME: \n";
$ftp_username = str_replace("\n", '', fgets(STDIN));
echo "FTP PASSWORD: \n";
$ftp_password = str_replace("\n", '', fgets(STDIN));
echo "FTP PATH: [/public_html] \n";
$ftp_path = str_replace("\n", '', fgets(STDIN));
$ftp_path = ($ftp_path == '') ? "/public_html" : $ftp_path;

$data['ftp'] = array(
    'server' => $ftp_server,
    'username' => $ftp_username,
    'path' => $ftp_path,
    'password' => $ftp_password
);

echo "Git or SVN?[Git] \n";
$vcs = (preg_match("/svn/i", str_replace("\n", '', fgets(STDIN)))) ? false : true;

// If it is empty, it matches everything
$data['global'] = array(
    'ignore' => "/(^{$config['config_dir']}\/)|(\.sql\$)|(.*sql\.gz)|(^\.gitignore$)/",
    'git' => $vcs
);

$data->save();
echo NOTICE . ": FTP configurations saved in '$config_file' \n";

$info = $data;
$head = Version::get_head();
$ftp = new Ftp($info);
$ftp->connect();
$ftp->set_revision_server($head);

echo NOTICE . ": *** Put Zend xml file (guard.xml) in {$pwd}/{$config['config_dir']} \n";
