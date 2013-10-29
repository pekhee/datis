<?php
/*
 * =======================================================================
 * DOWNLOAD
 * =======================================================================
 */


/**
 * HELP
 */

$help = "Usage: [push][OPTIONS]
Options:
-f <FILE>             Download to the specified FILE. *Path must be relative*
--file<=FILE>

-d <DIR>              Download  all files in givern directory.
--directory=<DIR>
";

/**
 * GET OPTIONS FOR PUSH
 */

foreach ($args as $key => $value) {
    switch ($key) {
    case 'h':
    case 'help':
        echo $help . $help_all;
        bye();
        break;
    case 'f':
    case 'file':
        $file_override = $value;
        break;
    case 'd':
    case 'directory':
        $zip_directory = $value;
        break;
    }
}

/**
 * START
 */

$ftp = new Ftp($info);
if (!$ftp->connect()) {
	$result == false;
	bye();
}

$error = array(
    'Zip was done succcessfully.',
    'Cannot zip!'
);

// Upload dump
copy(dirname(__FILE__) . "/../inc/dump.php", $pwd . '/' . $config['temp'] . '/main/dump.php');
if (!Zend::zend_guard()) {
	bye();
}

if (!$ftp->put($config['temp'] . '/zend/main/dump.php', '/dump.php')) {
	bye();
}

// Zip
$zip_directory = urlencode(((isset($zip_directory)) ? $zip_directory : './userfiles/'));
$file = (isset($file)) ? $file : 'zip.zip';
$get_url = "http://" . $info['ftp']['server'] . "/dump.php?a1=zip&a2={$zip_directory}&rand=" . rand(1, 1000);
$zip = file_get_contents($get_url);
var_dump($zip);
echo (($zip == 0) ? SUCCESS : FAIL) . ": " . $error[$zip] . PHP_EOL;

// Download
exec("wget -O '$file' {$info['ftp']['server']}/zip.zip?rand=" . rand(1, 1000), $return, $exit);
$return = 0;
if ($exit == 0) {
    echo SUCCESS . ": Zip file  successfuly saved to '$file'\n";
} else {
    echo FAIL . ": Failed, something happened during download.\n";
}

// Cleanings
$ftp->del('dump.php');
$ftp->del('zip.zip');
$ftp->close();
