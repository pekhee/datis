<?php
// Get datis database info
include 'inc/index.php';
$config=unserialize(base64_decode($config));
error_reporting(0);
$error = 0;

/**
 * Functions needed for actions below
 */

function mysql_test($mysql_host,$mysql_database, $mysql_username, $mysql_password)
{
    global $output_messages, $error;
    $link = mysql_connect($mysql_host, $mysql_username, $mysql_password);
    if (!$link) {
        $error = 1;
    } else {
        $db_selected = mysql_select_db($mysql_database, $link);
        if (!$db_selected) {
        	$error = 2;
        }
    }

}

function mysql_dump($mysql_database, $table = 'all' , $structure = 0)
{
    global $error;
    $return = '';
    mysql_query("SET NAMES 'utf8'");
    if ($table == 'all') {
        $sql="show tables;";
        $result= mysql_query($sql);
        if ($result) {
            while ($row= mysql_fetch_row($result)) {
                $return .= mysqldump_table_structure($row[0]);
                if ($structure==0) {
					$return .=   mysqldump_table_data($row[0]);
				}

            }
        } else {
            $return .= "/* no tables in $mysql_database */\n";
        }
    } else {
        // Check if table exists
        if ( !mysql_query("SELECT 1 FROM `$table` LIMIT 1") ) {
            $error = 4;
            return;
        }
        $return .= mysqldump_table_structure($table);
        if ($structure==0) {
			$return .=   mysqldump_table_data($table);
		}
    }
    mysql_free_result($result);
	return $return;
}

function mysqldump_table_structure($table)
{
    $return = "/* Table structure for table `$table` */\n";
    $return .= "DROP TABLE IF EXISTS `$table`;\n\n";
    $sql="show create table `$table`; ";
    $result=mysql_query($sql);
    if ($result) {
        if ($row= mysql_fetch_assoc($result)) {
            $return .= $row['Create Table'].";\n\n";
        }
    }
    mysql_free_result($result);

    return $return;
}

function mysqldump_table_data($table)
{
    $return = '';
    $sql="select * from `$table`;";
    $result=mysql_query($sql);
    if ($result) {
        $num_rows= mysql_num_rows($result);
        $num_fields= mysql_num_fields($result);

        if ( $num_rows > 0) {
            $return .= "/* dumping data for table `$table` */\n";

            $field_type=array();
            $i=0;
            while ( $i < $num_fields) {
                $meta= mysql_fetch_field($result, $i);
                array_push($field_type, $meta->type);
                $i++;
            }

            //print_r( $field_type);
            $return .= "insert into `$table` values\n";
            $index=0;
            while ($row= mysql_fetch_row($result)) {
                $return .= "(";
                for ( $i=0; $i < $num_fields; $i++) {
                    if (is_null($row[$i])) {
                        $return .= "null";
                    } else {
                        switch( $field_type[$i])
                        {
                        case 'int':
                            $return .= $row[$i];
                            break;
                        case 'string':
                        case 'blob' :
                        default:
                            $return .= "'".mysql_real_escape_string($row[$i])."'";

                        }
                    }
                    if ( $i < $num_fields-1)
                        $return .= ",";
                }
                $return .= ")";

                if ( $index < $num_rows-1) {
                    $return .= ",";
                } else {
                    $return .= ";";
				}
                $return .= "\n";

                $index++;
            }
        }
    }
    mysql_free_result($result);
    $return .= "\n";
	return $return;
}


function pmd_mysql_dump_import($db_host, $db_username, $db_password, $db_name, $sql_dump, $last_result = NULL)
{
    global $error;
    $db_connection = @new mysqli($db_host, $db_username, $db_password);
    $db_connection->query("SET NAMES 'utf8'");
    if (mysqli_connect_error()) {
        $last_result = mysqli_connect_error();
        $error = 1;
        return NULL;
    }

    if (!$db_connection->select_db($db_name)) {
        if($db_connection)
            $last_result = $db_connection->error;
        else
            $last_result = "Unable to connect to mysql database";
        $db_connection->close();
        $error = 2;
        return NULL;
    }

    $contents = explode("\n", $sql_dump);
    $templine = '';
    foreach ($contents as $line) {
        if (substr($line, 0, 2) == '/*' || substr($line, 0, 2) == '--' || $line == '')
            continue;

        $templine .= $line;
        if (substr(trim($line), -1, 1) == ';') {
            if (!$db_connection->query($templine)) {
                $last_result .= $db_connection->error."\n";
            }
            $templine = '';
        }
    }
    $db_connection->close();
    return true;
}

function zip($source, $destination)
{
    if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));

    if (is_dir($source) === true) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file) {
            $file = str_replace('\\', '/', $file);

            // Ignore "." and ".." folders
            if (in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) )
                continue;

            $file = realpath($file);

            if (is_dir($file) === true) {
                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            } elseif (is_file($file) === true) {
                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
        }
    } elseif (is_file($source) === true) {
        $zip->addFromString(basename($source), file_get_contents($source));
    }

    return $zip->close();
}

/**
 * Remote actions
 */
$mysql_error = '';
$result = array();
$action1 = (isset($_REQUEST['a1'])) ? $_REQUEST['a1'] : $argv[1];
$action2 = (isset($_REQUEST['a2'])) ? $_REQUEST['a2'] : $argv[2];
$action3 = (isset($_REQUEST['a3'])) ? $_REQUEST['a3'] : $argv[3];

switch ($action1) {
case 'backup':
	// Errors
	// 1 Cannot connect to MySQL
	// 2 Cannot connect to DB
    mysql_test($config['server'], $config['dbname'], $config['user'], $config['password']);
    $result['result'] = implode(',', $output_messages);
    $result['response'] = mysql_dump($config['dbname'], $action2, $action3);
    if ($error==0) {
        $gz = gzopen((isset($argv[4]))? $argv[4] :"sql.gz", 'w9');
        gzwrite($gz, $result['response']);
        gzclose($gz);
    }
    break;
case 'restore':
	// Errors
	// 1 Cannot connect to MySQL
	// 2 Cannot connect to DB
	// 3 File does not exist
    $filename = (isset($argv[2]))? $argv[2] : "sql.gz";
    if (!file_exists($filename)) {
		$error = 3;
		break;
	}
    ob_start();
    readgzfile($filename);
    $dump = ob_get_contents();
    ob_end_clean();
    if ( !pmd_mysql_dump_import($config['server'], $config['user'], $config['password'], $config['dbname'], $dump, $mysql_error) ) {
        //if ($mysql_error!==NULL) { $error=4;} ;
    }
    break;
case 'unzip':
    $file = 'zip.zip';
    // Get the absolute path to $file
    $path = pathinfo(realpath($file), PATHINFO_DIRNAME);
    $zip = new ZipArchive;
    $res = $zip->open($file);
    if ($res === TRUE) {
        // extract it to the path we determined above
        $zip->extractTo($path);
        $zip->close();
        $error = 0;
    } else {
        $error = 1;
    }
    break;
case 'zip':
	$error = zip($action2, './zip.zip');
	break;
case 'dbinfo':
	echo base64_encode(serialize($config));
	exit();
	break;
}
echo $error;
exit($error);
