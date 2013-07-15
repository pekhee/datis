#!/usr/bin/env php
<?php

// TODO:
// [] Do something about modified, deleted or new directories 
// [] Save into tmp directory
// [] 'FTP connected successfuly' message is not needed
// [] Option to clear .old files
// [] Changing on error option of Zend XML
// [] Ignore option for Zend Errors

// Dependencies
require_once dirname( __FILE__ )."/inc/Lite.php";
require_once dirname( __FILE__ )."/inc/functions.php";

// Get Arguments
$args = parseArgs();
$action = @$args[0];
unset($args[0]);

error_reporting(0);
echo "\033[37m"; // Changes color to white

// Load Global Congiguratoin File

if (file_exists(dirname( __FILE__ ).'/config.ini')) {
        $config = new Config_Lite(dirname( __FILE__ ).'/config.ini');
        } else { 
        echo "Configuration file not found at ".dirname( __FILE__ )."/config.ini ! \n"; die(); };

/** 
/* The switch for different actions of script,
*/
switch ($action) {

    
    
    
/*=======================================================================
/*  PUSH
/*=======================================================================*/

    // Default case is uploading latest changes to server
    case 'push':
    default:


/**
 *  HELP
 */

$help = 
"
    -v                    Displays all errors and warnings.
    --verbose

    -h                    Shows this text and exits.
    --help

    -r NUMBER             Overrides the uploaded revision number in the log file.
    --revision=NUMBER

    -u [NUMBER]           Update the lastest uploaded revision to the latest local commited revision.
    --update[=NUMBER]     If [NUMBER] is provided, latest uploaded revision will be updated to [NUMBER].

    -i                    Creates the config directory and asks for FTP credentials.
    --init

    -c FILE               Overrides the config file.
    --config=FILE

    -x [FILE]             Override the Zend XML configuration file.
    --xml[=FILE]
    
Other actions:
    database              Backup and restore SQL files to Mysql
    account               Create new cPanel account, with its database and domain name 
\n";


/**
 * GET OPTIONS FOR PUSH
 */

foreach (  $args as $key => $value) {
  switch ($key) {
    case 'h':
    case 'help':
          echo $help;
          die(); 
        break;
    case 'v':
    case 'verbose':
        error_reporting(-1);
        break;
    case 'r':
    case 'revision':
        $revision_override = $value;
        break;
    case 'i':
    case 'init':
        $init = true;
        break;
    case 'u':
    case 'update':
        $update = true;
        $revision_update = ( isset($value) ) ? $value : '' ;
        break;
    case 'c':
    case 'config':
        $config_file = $value;
        break;
    case 'x':
    case 'xml':
        $xml_file = $value;
        break;
  }
}



/**
 * START
 */

// Update SVN
exec('svn update -q');

// Know where we are, gives full path to the current directory
$pwd = getenv("PWD");

// Go to the current directory
chdir($pwd);

// latest revision from svn
preg_match("/[0-9]+/", exec("svnversion") , $matches) ;
$head = $matches[0];

// Colors for bash
define('SUCCESS', "\033[32mSUCCESS\033[37m" );
define('FAIL', "\033[31m   FAIL\033[37m" );
define('NOTICE', "\033[33m NOTICE\033[37m");
define('WARNING', "\033[31mWARNING\033[37m");
define('IGNORED', "\033[33mIGNORED\033[37m");

// If it is the first time,, make the config directory, and the files in it
if ( isset($init) ) {
          // Create directory
          mkdir( $pwd . '/' .$config['config_dir'], 0755, true);
          echo 'Directory created: '.$pwd . '/' .$config['config_dir'] . "\n";

          // Get ftp credentials and save it
          echo "FTP SERVER: \n";
          $ftp_server =  str_replace("\n", '', fgets(STDIN) );
          echo "FTP USERNAME: \n";
          $ftp_username = str_replace("\n", '', fgets(STDIN) );
          echo "FTP PASSWORD: \n";
          $ftp_password =  str_replace("\n", '', fgets(STDIN) );
          echo "FTP PATH (eg /public_html): \n";
          $ftp_path =  str_replace("\n", '', fgets(STDIN) );
          
          // Save it to file 
          $data = new Config_Lite();
          $data->setFilename($config['config_dir'].'/'.$config['config']);
          
          
          $data['ftp']= array( 
          'server' => $ftp_server,
          'username' => $ftp_username,
          'path' => $ftp_path,
          'password' => $ftp_password
          );

          // Regex for files to ignore,
          // Paths are relative,
          // If it is empty, it matches everything
          $data['global'] = array( 'ignore' =>  "/(^{$config['config_dir']}\/)|(\.sql\$)/" );


          $data->save();
          
          echo NOTICE . ": FTP configurations saved in " . $pwd."{$config['config_dir']}/{$config['config']} \n";

          // Set the head to the latest revision 
          file_put_contents( $pwd.'/'.$config['latest'], $head);

          // set up basic connection
          $conn_id = ftp_connect( $ftp_server ); 

          // Login with username and password
          $login_result = ftp_login($conn_id, $ftp_username , $ftp_password ); 

          // Check connection
          if ((!$conn_id) || (!$login_result)) { 
              echo FAIL . ": FTP connection has failed! \n";
              echo FAIL . ": Attempted to connect to ".$ftp_server." for user ".$ftp_username . "\n";
              $result = false;
          } else {
              echo SUCCESS . ": Connected to ".$ftp_server.", for user ".$ftp_username ."\n";
          }

          // Upload the file
          $upload = ftp_put($conn_id, $ftp_path . '/' . $config['revision_file'] , $pwd.'/'.$config['latest'] , FTP_BINARY);
          // check upload status
          if (!$upload) {
              echo FAIL . ": Revision could not be saved on server, check if path exists. \n";
          } else {
              echo NOTICE . ": Latest revision was set to revision $head \n";
          }

          echo NOTICE . ": *** Put Zend xml file in {$pwd}/{$config[config_dir]} \n";

          die();
} else {
  if (isset($config_file)) {
    $info = new config_lite($config_file);
  } else {
  if (file_exists("{$config[config_dir]}/{$config['config']}")) {
        $info = new config_lite("{$config[config_dir]}/{$config['config']}");
        } else { 
        echo "Configuration file not found at {$config[config_dir]}/{$config['config']} ! \nTry using --init or --config \n"; die(); };

  }
}

    // set up basic connection
    $conn_id = ftp_connect( $info['ftp']['server'] ); 

    // Login with username and password
    $login_result = ftp_login($conn_id, $info['ftp']['username'] , $info['ftp']['password'] ); 

    // Check connection
    if ((!$conn_id) || (!$login_result)) { 
        echo FAIL . ": FTP connection has failed! \n";
        echo FAIL . ": Attempted to connect to ".$info['ftp']['server']." for user ".$info['ftp']['username'] . "\n";
        $result = false;
    } else {
        echo SUCCESS . ": Connected to ".$info['ftp']['server'].", for user ".$info['ftp']['username'] ."\n";
    }

    // Option -u or --update, updates the revision number
if ( isset($update) ) {
    $revision_to_upload = ( ($revision_update=='')? $head : $revision_update ) ;
    // Put the revision into the file
    file_put_contents( $pwd.'/'.$config['latest'],  $revision_to_upload );
    // Upload the file
    $upload = ftp_put($conn_id, $info['ftp']['path'] . '/' . $config['revision_file'] , $pwd.'/'.$config['latest'] , FTP_BINARY);
    // check upload status
    if (!$upload) {
        echo FAIL . "Revision was not updated. \n";
    } else {
        echo NOTICE . ": Latest revision was set to revision $revision_to_upload \n";
    }
    die();
  }

  // Get latest revision and save it to the file
  if (ftp_get($conn_id, $pwd.'/'.$config['latest'] , $info['ftp']['path'] . '/' . $config['revision_file'] , FTP_BINARY)) {
  } else {
      echo FAIL . ": Cannot find {$config['revision_file']} file on the server. \n";
      echo "         Use -u option to set the revision number to current revision number $head. \n";
      die();
  }

  $last_revision_from_file = file_get_contents( $pwd .'/'. $config['latest'] );



/**
 * MAIN PART FOR PUSH
 */

// If result is false to end of script, lastest revision is not updated
$result = true;

// Get the latest uploaded commit from file or -r argument
$last_revision = isset($revision_override)  ? $revision_override : $last_revision_from_file ;
echo "         Revision number $last_revision \n"; // Indent is OK!!

// If everything is up to date, exit
if ($head == $last_revision) { echo "Everything is up to date to the latest revision number $last_revision \n";die();}

// Get the list of changed files as XML
$files_as_xml =  exec('echo $(svn diff --summarize --xml -r '.$last_revision.':HEAD) ');

// Convert the XML into array
$xml = new SimpleXMLElement($files_as_xml);
  $files = array (
      'modified' =>  $xml -> xpath("//path[@item='modified' and @kind='file']") ,
      'added' =>  $xml -> xpath("//path[@item='added' and @kind='file']") ,
      'deleted' =>  $xml -> xpath("//path[@item='deleted' and @kind='file']") ,
    );

// Create the temp directories
mkdir( $pwd . '/' .$config['temp'], 0755, true);
mkdir( $pwd . '/' .$config['temp'] . '/zend', 0755, true);
mkdir( $pwd . '/' .$config['temp'] . '/main/', 0755, true);

// Copy the modified files
if ( count($files['modified']) != 0  ) { echo "\n Files modified:: \n"; }
foreach ($files['modified'] as $file) {
    // If it should be ignored, ignore it
    if ( preg_grep($info['global']['ignore'], array( (string) $file)) ) { 
        echo IGNORED .": $file \n";
        continue; }
    // Make the relative path and copy the files
    $dir = (string) '/'.dirname($file);
    $dir =  str_replace('/.' , '', $dir);
    mkdir( $pwd .  '/' . $config['temp']  .'/main' . $dir , 0755, true);
    copy( $file,  $pwd. '/' . $config['temp']  .'/main'. $dir . '/' . basename($file) );
    $new_files['modified'][] = $pwd. '/' . $config['temp']  .'/zend/main'. $dir . '/' . basename($file);
    echo $file . "\n";
}

// Copy the added files
 if ( count($files['added']) != 0 )  {echo "\n Files added:: \n";}
foreach ($files['added'] as $file) {
   // If it should be ignored, ignore it
    if ( preg_grep($info['global']['ignore'], array( (string) $file)) ) { 
        echo IGNORED . ": $file \n";
        continue; }
        // Make the relative path and copy the files
    $dir = (string) '/'.dirname($file);
    $dir =  str_replace('/.' , '', $dir);
    mkdir( $pwd . '/' . $config['temp']  .'/main' . $dir , 0755, true);
    copy( $file,  $pwd.'/' . $config['temp']  .'/main'. $dir . '/' . basename($file) );
    $new_files['added'][] = $pwd.'/' . $config['temp']  .'/zend/main'. $dir . '/' . basename($file);
    echo $file . "\n";
}

// Display the deleted files
 if ( count($files['deleted']) != 0 )  {echo "\n Files deleted: \n";}
foreach ($files['deleted'] as $file) {
  echo $file . "\n";
}

  echo "\n Files OK? [y/*]";
  $approve =  str_replace("\n", '', fgets(STDIN) );
  if ($approve != 'y') { delTree( $pwd . '/' . $config['temp'] );die();} 

// Modify zend xml config
$xml_conf = file_get_contents("$pwd/{$config['zend_conf']}");
$new_xml_conf = preg_replace(
  array('/(targetDir=".*?)+(")/','/(source path=".*?)+(")/'),
  array( 'targetDir="' . $pwd .'/'.$config['temp'].'/zend"','source path="' . $pwd .'/'.$config['temp'].'/main"') ,
  $xml_conf);

file_put_contents("$pwd/{$config['zend_conf']}", $new_xml_conf);


// Encode the files using Zend somewhere in the tmp folder
exec( 'sudo date --set="$(date -d \'last year\')"' );
echo exec( $config['zend_guard'].' --xml-file "'.( (isset($xml_file) ? $xml_file : $pwd.'/'.$config['zend_conf'] )).'"' );
exec( 'sudo date --set="$(date -d \'next year\')"' );

// Upload the encoded files using FTP

// Upload modified files
foreach ( $new_files['modified'] as $file ) {
    $dir = dirname($file);
    $relative_dir =  str_replace($pwd . '/' . $config['temp'] . "/zend/main", '', $dir);
  // Rename the old file
    if (ftp_rename($conn_id, $info['ftp']['path'] . $relative_dir . '/' . basename($file) , $info['ftp']['path']. $relative_dir . '/' . basename($file) . ".old")) {
   echo NOTICE . ": .old file was created for $file \n";
  } else {
   echo WARNING . ": .old file was not created for $file \n";
  }
  $upload = ftp_put($conn_id, $info['ftp']['path'] . $relative_dir . '/' . basename($file) , $file, FTP_BINARY);
    // check upload status
    if (!$upload) { 
        echo FAIL . ": FTP upload has failed!: $file \n";
       $result = false;
    } else {
        echo SUCCESS . ": Uploaded $file to ".$info['ftp']['path']. $relative_dir . '/' . basename($file)." \n";
    }
}

// Upload added files
foreach ( $new_files['added'] as $file ) {
  $dir = dirname($file);
  $relative_dir =  str_replace($pwd . '/' .$config['temp'] . "/zend/main", '', $dir);
  // TODO: following line makes the upload slow
  ftp_mksubdirs($conn_id,'/',$info['ftp']['path'] . $relative_dir);
  $upload = ftp_put($conn_id, $info['ftp']['path'] . $relative_dir . '/' . basename($file) , $file, FTP_BINARY);
  // check upload status
  if (!$upload) { 
      echo FAIL . ": FTP upload has failed!: $file \n";
      $result = false;
  } else {
      echo SUCCESS . ": Uploaded $file to ".$info['ftp']['path'] . $relative_dir . '/' . basename($file)." \n";
  }
}

// Remove the deleted files on FTP
foreach ( $files['deleted'] as $file ) {
  // If it should be ignored, ignore it
    if ( count( preg_grep($info['global']['ignore'], array( (string) $file) ) )  !=0 ) { continue; }
  $dir = dirname($file);
  $relative_dir =  str_replace($pwd , '', $dir);
  $delete = ftp_delete($conn_id, $info['ftp']['path'] . $relative_dir . '/' . basename($file) );
  // check delete status
  if (!$delete) { 
      echo FAIL . ": FTP delete has failed!: $file \n";
      $result = false;
  } else {
      echo SUCCESS . ": Deleted, $file in ".$info['ftp']['path'] . $relative_dir . '/' . basename($file)." \n";
  }
}

// Write the latest revision to the file
if($result == true && !isset($revision_override)) {
   file_put_contents( $pwd.'/'.$config['latest'], $head);
  // Upload the file
  $upload = ftp_put($conn_id, $info['ftp']['path'] . '/' . $config['revision_file'] , $pwd.'/'.$config['latest'] , FTP_BINARY);
  // check upload status
  if (!$upload) {
      echo WARNING . ": Revision could not updated. \n";
  } else {
      echo NOTICE . ": Latest revision was set to revision $head \n";
  }
}

// close the FTP stream 
ftp_close($conn_id);

// Remove the temp directory
delTree( $pwd . '/' .$config['temp'] );

    
    case 'database':
        break;
    case 'account':
        break;
}
