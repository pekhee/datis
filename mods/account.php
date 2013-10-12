<?php
/**
 * HELP
 */

$help = "Usage: [OPTION]

Options:";

/**
 * GET OPTIONS FOR ACCOUNT
 */

foreach ($args as $key => $value) {
    switch ($key) {
    case 'h':
    case 'help':
        echo $help;
        bye();
        break;
    }
}

/**
 * START
 */
require dirname(__FILE__) . '/../inc/xmlapi.php';
// Make the config directory, and the files in it
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

if ($config['whm']['username'] == '') {
    echo "WHM Admin Username: \n";
    $root_user = str_replace("\n", '', fgets(STDIN));
} else {
    $root_user = $config['whm']['username'];
    echo "WHM Admin Username: ".$root_user."\n";
}

if ($config['whm']['password'] == '') {
    echo "WHM Admin Password: \n";
    $root_pass = str_replace("\n", '', fgets(STDIN));
} else {
    $root_pass = $config['whm']['password'];
    echo "WHM Admin Username: xxxxxxx"."\n";
}

$xmlapi = new xmlapi($config['whm']['ip']);
$xmlapi->set_debug((isset($verbose)) ? 1 : 0);
$xmlapi->set_output("array");
$xmlapi->password_auth($root_user, $root_pass);

// Get User Info
echo "Acc Username: \n";
$username = str_replace("\n", '', fgets(STDIN));
echo "Acc Domain: \n";
$domain = str_replace("\n", '', fgets(STDIN));
$password = generateRandomString();

// Create Cpanel Account
$acct = array(
    'username' => $username,
    'password' => $password,
    'domain' => $domain
    // plan => '',
    // contactemail => '',
    // pkgname => '',
);

$status = $xmlapi->createacct($acct);

if ($status['result']['status']) {
    echo SUCCESS . ": " . $status['result']['statusmsg'] . "\nConfig file saved in {$config_file}";
} else {
    echo FAIL . ": " . $status['result']['statusmsg'] . "\n";
}

$data['ftp'] = array(
    'server' => $domain,
    'username' => $username,
    'path' => "/public_html",
    'password' => $password
);

$data['global'] = array(
    'ignore' => "/(^{$config['config_dir']}\/)|(\.sql\$)|(sql\.gz)/"
);
$data->save();
