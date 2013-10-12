<?php
/*
 * =======================================================================
 * ERRORLOG
 * =======================================================================
 /**
  * HELP FOR ERRORLOG
 */

$help = "Usage: [OPTION]

Options:
-l                    clears the log
--clear";

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
    case 'l':
    case 'clear':
        $clear = true;
        break;
    }
}

/**
 * START FOR ERRORLOG
 */

// Login with username and password
$ftp = new Ftp($info);
if (!$ftp->connect()) {
	bye();
}

if ($clear === true) {
    $ftp->del('error_log');
    bye();
}

if ($ftp->get('error_log', 'error_log')) {
    echo SUCCESS . ": Error log saved at {$pwd}/error_log \n         LOG: \n";
    exec("cat '{$pwd}/error_log'", $r, $e);
    foreach ($r as $line) {
        echo $line . PHP_EOL;
    }
}
