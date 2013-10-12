<?php
/**
 * FUNCTIONS NEEDED
 */

// Colors for bash
define('SUCCESS', "\033[32mSUCCESS\033[37m");
define('FAIL', "\033[31m   FAIL\033[37m");
define('NOTICE', "\033[33m NOTICE\033[37m");
define('WARNING', "\033[31mWARNING\033[37m");
define('IGNORED', "\033[33mIGNORED\033[37m");

/*** nullify any existing autoloads ***/
spl_autoload_register(null, false);

/*** specify extensions that may be loaded ***/
spl_autoload_extensions('.php, .class.php');

/*** class Loader ***/
function classLoader($class)
{
    $filename = strtolower($class) . '.class.php';
    $file = dirname(__FILE__) .'/../class/' . $filename;
    if (!file_exists($file)) {
        return false;
    }
    include $file;
}
spl_autoload_register('classLoader');

/**
 * parseArgs Command Line Interface (CLI) utility function.
 *
 * @author Patrick Fisher <patrick@pwfisher.com>
 * @see https://github.com/pwfisher/CommandLine.php
 */
function parseArgs ($argv = null)
{
    $argv = $argv ? $argv : $_SERVER['argv'];
    array_shift($argv);
    $o = array();
    for ($i = 0, $j = count($argv); $i < $j; $i ++) {
        $a = $argv[$i];
        if (substr($a, 0, 2) == '--') {
            $eq = strpos($a, '=');
            if ($eq !== false) {
                $o[substr($a, 2, $eq - 2)] = substr($a, $eq + 1);
            } else {
                $k = substr($a, 2);
                if ($i + 1 < $j && $argv[$i + 1][0] !== '-') {
                    $o[$k] = $argv[$i + 1];
                    $i ++;
                } else
                    if (! isset($o[$k])) {
                        $o[$k] = true;
                    }
            }
        } else
            if (substr($a, 0, 1) == '-') {
                if (substr($a, 2, 1) == '=') {
                    $o[substr($a, 1, 1)] = substr($a, 3);
                } else {
                    foreach (str_split(substr($a, 1)) as $k) {
                        if (! isset($o[$k])) {
                            $o[$k] = true;
                        }
                    }
                    if ($i + 1 < $j && $argv[$i + 1][0] !== '-') {
                        $o[$k] = $argv[$i + 1];
                        $i ++;
                    }
                }
            } else {
                $o[] = $a;
            }
    }
    return $o;
}

// Functions
function generateRandomString ($length = 10)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i ++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

function bye ()
{
    global $pwd, $config;
    echo "\033[0m"; // Changes color to defult
    Files::del_tree($pwd . '/' . $config['temp']);
    die();
}
