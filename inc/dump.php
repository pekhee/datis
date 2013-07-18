<?php
// Errors
// 1 Cannot connect to MySQL
// 2 Cannot connect to DB

    include 'inc/index.php';
    $config=unserialize(base64_decode($config));
    error_reporting(1);
    $error = 0;

    function _mysql_test($mysql_host,$mysql_database, $mysql_username, $mysql_password)
{
    global $output_messages, $error;
    $link = mysql_connect($mysql_host, $mysql_username, $mysql_password);
    if (!$link)
    {
        $error = 1;
    }
    else
    {
        $db_selected = mysql_select_db($mysql_database, $link);
        if (!$db_selected)
        {
        
        $error = 2;
        }
    }

}

function _mysqldump($mysql_database)
{   
    $return = '';
    mysql_query("SET NAMES 'utf8'");
    $sql="show tables;";
    $result= mysql_query($sql);
    if( $result)
    {
        while( $row= mysql_fetch_row($result))
        {
            $return .= _mysqldump_table_structure($row[0]);

            $return .=   _mysqldump_table_data($row[0]);

        }
    }
    else
    {
        $return .= "/* no tables in $mysql_database */\n";
    }
    mysql_free_result($result);
return $return;
}

function _mysqldump_table_structure($table)
{
    $return = "/* Table structure for table `$table` */\n";
        $return .= "DROP TABLE IF EXISTS `$table`;\n\n";
        $sql="show create table `$table`; ";
        $result=mysql_query($sql);
        if( $result)
        {
            if($row= mysql_fetch_assoc($result))
            {
                $return .= $row['Create Table'].";\n\n";
            }
        }
        mysql_free_result($result);

    return $return;
}

function _mysqldump_table_data($table)
{
    $return = '';
    $sql="select * from `$table`;";
    $result=mysql_query($sql);
    if( $result)
    {
        $num_rows= mysql_num_rows($result);
        $num_fields= mysql_num_fields($result);

        if( $num_rows > 0)
        {
            $return .= "/* dumping data for table `$table` */\n";

            $field_type=array();
            $i=0;
            while( $i < $num_fields)
            {
                $meta= mysql_fetch_field($result, $i);
                array_push($field_type, $meta->type);
                $i++;
            }

            //print_r( $field_type);
            $return .= "insert into `$table` values\n";
            $index=0;
            while( $row= mysql_fetch_row($result))
            {
                $return .= "(";
                for( $i=0; $i < $num_fields; $i++)
                {
                    if( is_null( $row[$i]))
                        $return .= "null";
                    else
                    {
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
                    if( $i < $num_fields-1)
                        $return .= ",";
                }
                $return .= ")";

                if( $index < $num_rows-1)
                    $return .= ",";
                else
                    $return .= ";";
                $return .= "\n";

                $index++;
            }
        }
    }
    mysql_free_result($result);
    $return .= "\n";
return $return;
}
    

    function pmd_mysql_dump_import($db_host, $db_username, $db_password, $db_name, $sql_dump, $last_result = NULL) {
        global $error;
        $db_connection = @new mysqli($db_host, $db_username, $db_password);
        $db_connection->query("SET NAMES 'utf8'");
        if(mysqli_connect_error()) {
            $last_result = mysqli_connect_error();
            $error = 1;
            return NULL;
        }
        
        if(!$db_connection->select_db($db_name)) {
           if($db_connection)
                $last_result = $db_connection->error;
           else 
                $last_result = "Unable to connect to mysql database";
           $db_connection->close();
           $error = 2;
           return NULL;
        }
        
        $contents = explode("\n",$sql_dump);
        $templine = '';
        foreach($contents as $line) {
            if (substr($line, 0, 2) == '/*' || substr($line, 0, 2) == '--' || $line == '')
                continue;
            
            $templine .= $line;
            if (substr(trim($line), -1, 1) == ';') {
                if(!$db_connection->query($templine)) {
                    $last_result .= $db_connection->error."\n";
                }
                $templine = '';
            }
        }
        $db_connection->close();
        return true;
    }

    
    
$mysql_error = '';    
$result = array();

$action = (isset($_REQUEST['fn'])) ? $_REQUEST['fn'] : $argv[1];

switch ($action) {
    case 'backup':
           _mysql_test($config['server'],$config['dbname'], $config['user'], $config['password']);
           $result['result'] = implode(',', $output_messages);
           $result['response'] = _mysqldump($config['dbname']);
           $gz = gzopen('sql.gz','w9');
           gzwrite($gz, $result['response']);
           gzclose($gz);
           break;
    case 'restore':
            $filename = (isset($argv[2]))? $argv[2] :"sql.gz";
            if (!file_exists($filename)) { $error = 3;break;}
            ob_start(); 
            readgzfile($filename);
            $dump = ob_get_contents();
            ob_end_clean();
        if ( !pmd_mysql_dump_import($config['server'], $config['user'], $config['password'], $config['dbname'], $dump, $mysql_error) ) {
            $result['result'] = $mysql_error;
            }
            break;
} 

echo $error;
exit($error);