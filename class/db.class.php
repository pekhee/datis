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
        global $pwd, $file, $info, $table, $structure;
        $table = (isset($table)) ? $table : 'all';
        if ($table == 'all' && isset($structure)) {
            echo WARNING . ": DATA WILL BE LOST UPON RESTORE!! \n         Are you sure to get ONLY schema of all tables? (enter fuckme to continue)\n";
            if (str_replace("\n", '', fgets(STDIN)) != 'fuckme') {
                bye();
            }
        }

        $structure = (isset($structure)) ? 1 : 0;

        if ($is_local) {
            exec("php '{$pwd}/dump.php' backup {$table} {$structure} " . ((isset($file)) ? $file : ''), $return, $exit);
			$return = '';
            echo (($exit == 0) ? SUCCESS : FAIL) . ": " . $this->error[$exit] . PHP_EOL;
        } else {
            $result = file_get_contents("http://" . $info['ftp']['server'] . "/dump.php?a1=backup&a2={$table}&a3={$structure}&m=" . rand(1, 1000));
            echo (($result == 0) ? SUCCESS : FAIL) . ": " . $this->error[$result] . "\n";
            if ($result == 0) {
                exec("wget -O '$file' {$info['ftp']['server']}/sql.gz?rand=" . rand(1, 1000), $return, $exit);
                if ($exit == 0) {
                    echo SUCCESS . ": Backup successfuly saved to '$file'\n";
                } else {
                    echo FAIL . ": Failed, something happened during download.\n";
                }
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
        global $pwd, $file, $info;
        if ($is_local) {
            copy(dirname(__FILE__) . "/../inc/dump.php", $pwd . '/dump.php');
            exec("php '{$pwd}/dump.php' restore " . $file, $return, $exit) . "";
			$return = '';
            echo (($exit == 0) ? SUCCESS : FAIL) . ": " . self::$error[$exit] . PHP_EOL;
        } else {
			echo NOTICE . ": Uploading SQL file.";
            if (!Ftp::put($file, 'sql.gz', false)) {
				return false;
			}
            $result = file_get_contents("http://" . $info['ftp']['server'] . "/dump.php?a1=restore&" . rand(1, 1000));
            echo (($result == 0) ? SUCCESS : FAIL) . ": " . self::$error[$result] . PHP_EOL;
        }
    }

}
