<?php
/**
 * Database Class
 */
class Db
{
    private static $error = array(
        'Done succcessfully.',
        'Cannot connect to MySQL.',
        'Cannot connect to the database.',
        'File does not exist.',
        'Table not found'
    );

	public static function backup($is_local)
	{
        global $table, $structure;
        $table = (isset($table)) ? $table : 'all';
        $structure = (isset($structure)) ? 1 : 0;

		// Warning for server restore
        if ($table == 'all' && isset($structure)) {
            echo WARNING . ": DATA WILL BE LOST UPON RESTORE!! \n         Are you sure to get ONLY schema of all tables? (yes/no))\n";
            if (str_replace("\n", '', fgets(STDIN)) != 'yes') {
                bye();
            }
        }

        if ($is_local) {
			self::local_backup();
        } else {
			self::server_backup();
        }
	}

	public static function local_backup()
	{
        global $pwd, $file, $table, $structure;
        exec("php '{$pwd}/dump.php' backup {$table} {$structure} " . ((isset($file)) ? $file : ''), $return, $exit);
		$return = '';
        echo (($exit == 0) ? SUCCESS : FAIL) . ": " . self::$error[$exit] . PHP_EOL;
	}

	public static function server_backup()
	{
        global $file, $info, $table, $structure;
        $result = file_get_contents("http://" . $info['ftp']['server'] . "/dump.php?a1=backup&a2={$table}&a3={$structure}&m=" . rand(1, 1000));
        echo (($result == 0) ? SUCCESS : FAIL) . ": " . self::$error[$result] . "\n";
        if ($result == 0) {
            exec("wget -O '$file' {$info['ftp']['server']}/sql.gz?rand=" . rand(1, 1000), $return, $exit);
			$return = 0;
            if ($exit == 0) {
                echo SUCCESS . ": Backup successfuly saved to '$file'\n";
            } else {
                echo FAIL . ": Failed, something happened during download.\n";
            }
        }
	}

    public static function restore ($is_local)
    {
        // TODO: Temporary
        // if ($is_local === false) {
        // echo NOTICE . ": Restore to server is not supported yet :) \n";
        // return
        // }
        if ($is_local) {
			self::local_restore();
        } else {
			self::server_restore();
        }
    }

	public static function local_restore()
	{
        global $pwd, $file;
        copy(dirname(__FILE__) . "/../inc/dump.php", $pwd . '/dump.php');
        exec("php '{$pwd}/dump.php' restore " . $file, $return, $exit) . "";
		$return = '';
        echo (($exit == 0) ? SUCCESS : FAIL) . ": " . self::$error[$exit] . PHP_EOL;
	}

	public static function server_restore()
	{
        global $file, $info, $ftp;
        if (!$ftp->put($file, '/sql.gz', false)) {
			return false;
		}
        $result = file_get_contents("http://" . $info['ftp']['server'] . "/dump.php?a1=restore&" . rand(1, 1000));
        echo (($result == 0) ? SUCCESS : FAIL) . ": " . self::$error[$result] . PHP_EOL;
	}

}
