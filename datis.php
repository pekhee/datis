#!/usr/bin/env php
<?php
$help_all = "\n
    -c FILE               Saves to, or read from the FILE inseatd of 
    --config=FILE         default place.
    
    -x [FILE]             Override the Zend XML configuration file.
    --xml[=FILE]

    -i                    Ignores errors on Zend encoding.
    --ignore
    
    -v                    Displays all errors and warnings.
    --verbose

    -h                    Shows help text for each action and exits.
    --help

Other actions:
    [push]                Default, Push latest changes to server
    db                    Backup and restore SQL files to Mysql. 
    config                Creates the index.php config file and uploads.
    account               Creates new cPanel account and domain name. \n";

// Dependencies
require_once dirname(__FILE__) . "/inc/Lite.php";
require_once dirname(__FILE__) . "/inc/functions.php";

// Get Arguments
$args = parseArgs();
$action1 = @$args[0];
$action2 = @$args[1];
unset($args[0]);
unset($args[2]);

error_reporting(0);
echo "\033[37m"; // Changes color to white
                 
// Load Global Congiguratoin File

if (file_exists(dirname(__FILE__) . '/config.ini')) {
    $config = new Config_Lite(dirname(__FILE__) . '/config.ini');
} else {
    echo "Configuration file not found at " . dirname(__FILE__) . "/config.ini ! \n";
    bye();
}

// Know where we are, gives full path to the current directory
$pwd = getenv("PWD");

// Go to the current directory
chdir($pwd);

/**
 * GET GLOBAL OPTIONS
 */

foreach ($args as $key => $value) {
    switch ($key) {
        case 'c':
        case 'config':
            $config_file = $value;
            break;
        case 'v':
        case 'verbose':
            error_reporting(- 1);
            $verbose = true;
            break;
        case 'x':
        case 'xml':
            $xml_file = $value;
            break;
        case 'i':
        case 'ignore':
            $ignore_errors = true;
            break;
    }
}

// Override config
$config_file = (isset($config_file)) ? $config_file : $config['config_dir'] . '/' . $config['config'];

if (! file_exists($config_file) && $action1 != 'init' && $action1 != 'help' && $action1 != 'account') {
    echo "Config file was not found at '$config_file'\nTry using 'help' or 'init', or '-c' option to override conffile.\n";
    bye();
} else {
    $info = new config_lite($config_file);
}

// Create the temp directories
mkdir($pwd . '/' . $config['temp'], 0755, true);
mkdir($pwd . '/' . $config['temp'] . '/zend', 0755, true);
mkdir($pwd . '/' . $config['temp'] . '/main/', 0755, true);

// Modify zend xml config
$xml_conf = file_get_contents("$pwd/{$config['zend_conf']}");
$new_xml_conf = preg_replace(
        array(
                '/(targetDir=".*?)+(")/',
                '/(source path=".*?)+(")/',
                '/<ignoreErrors value="((true)|(false))"\/>/'
        ), 
        array(
                'targetDir="' . $pwd . '/' . $config['temp'] . '/zend"',
                'source path="' . $pwd . '/' . $config['temp'] . '/main"',
                ((isset($ignore_errors)) ? '<ignoreErrors value="true"/>' : '<ignoreErrors value="false"/>')
        ), $xml_conf);

file_put_contents("$pwd/{$config['zend_conf']}", $new_xml_conf);

/**
 * The switch for different actions of script,
 */
switch ($action1) {
    
    /*
     * =======================================================================
     * HELP
     * =======================================================================
     */
    
    case 'help':
        $help = "Usage: <ACTION> [OPTION]
Options:";
        
        echo $help . $help_all;
        // End of action
        break;
    /*
     * =======================================================================
     * INIT
     * =======================================================================
     */
    
    case 'init':
        
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
        // Make the config directory, and the files in it
        // Update SVN
        exec('svn update -q');
        
        // latest revision from svn
        preg_match("/[0-9]+/", exec("svnversion"), $matches);
        $head = $matches[0];
        
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
        
        // Regex for files to ignore,
        // Paths are relative,
        
        echo "Git or SVN?[Git] \n";
        $vcs = (preg_match("/svn/i", str_replace("\n", '', fgets(STDIN)))) ? false : true;
        
        // If it is empty, it matches everything
        $data['global'] = array(
                'ignore' => "/(^{$config['config_dir']}\/)|(\.sql\$)|(.*sql\.gz)|(^\.gitignore$)/",
                'git' => $vcs
        );
        
        $data->save();
        
        echo NOTICE . ": FTP configurations saved in '$config_file' \n";
        
        // Set the head to the latest revision
        file_put_contents($pwd . '/' . $config['latest'], $head);
        
        // set up basic connection
        $conn_id = ftp_connect($ftp_server);
        
        // Login with username and password
        $login_result = ftp_login($conn_id, $ftp_username, $ftp_password);
        ftp_pasv($conn_id, true);
        
        // Check connection
        if ((! $conn_id) || (! $login_result)) {
            echo FAIL . ": FTP connection has failed! \n";
            echo FAIL . ": Attempted to connect to " . $ftp_server . " for user " . $ftp_username . "\n";
            $result = false;
        } else {
            echo SUCCESS . ": Connected to " . $ftp_server . ", for user " . $ftp_username . "\n";
        }
        
        // Upload the file
        $upload = ftp_put($conn_id, $ftp_path . '/' . $config['revision_file'], $pwd . '/' . $config['latest'], FTP_BINARY);
        // check upload status
        if (! $upload) {
            echo FAIL . ": Revision could not be saved on server, check if path exists. \n";
        } else {
            echo NOTICE . ": Latest revision was set to revision $head \n";
        }
        
        echo NOTICE . ": *** Put Zend xml file (guard.xml) in {$pwd}/{$config[config_dir]} \n";
        bye();
        
        // End of action
        break;
    /*
     * =======================================================================
     * PUSH
     * =======================================================================
     */
    
    // Default case is uploading latest changes to server
    case 'push':
    default:
        
        /**
         * HELP
         */
        
        $help = "Usage: [push][OPTIONS]
Options:
    -r NUMBER             Overrides the uploaded revision number in the log file.
    --revision=NUMBER

    -u [NUMBER]           Update the lastest uploaded revision to the latest local 
    --update[=NUMBER]     commited revision. If [NUMBER] is provided, latest 
                          uploaded revision will be updated to [NUMBER].
                          
    -f <FILE>             Upload the specified FILE. *Path must be relative*
    --file<=FILE>         

    -z [ZIP]              Zips the files, uploads them and unzips them, if ZIP
    --zip[=ZIP]           is given, it will be uploaded. *NO .old FILES*";
        
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
                case 'v':
                case 'verbose':
                    error_reporting(- 1);
                    break;
                case 'r':
                case 'revision':
                    $revision_override = $value;
                    break;
                case 'u':
                case 'update':
                    $update = true;
                    $revision_update = (isset($value)) ? $value : '';
                    break;
                case 'c':
                case 'config':
                    $config_file = $value;
                    break;
                case 'f':
                case 'file':
                    $file_override = $value;
                    break;
                case 'z':
                case 'zip':
                    $zip = $value;
                    break;
            }
        }
        
        /**
         * START
         */
        // Update SVN
        
        // Is it git or svn?
        if ($info['global']['git'] == true) {
            $head = exec("git rev-parse --verify HEAD");
        } else {
            exec('svn update -q');
            // latest revision from svn
            preg_match("/[0-9]+/", exec("svnversion"), $matches);
            $head = $matches[0];
        }
        
        // set up basic connection
        $conn_id = ftp_connect($info['ftp']['server']);
        
        // Login with username and password
        $login_result = ftp_login($conn_id, $info['ftp']['username'], $info['ftp']['password']);
        ftp_pasv($conn_id, true);
        
        // Check connection
        if ((! $conn_id) || (! $login_result)) {
            echo FAIL . ": FTP connection has failed! \n";
            echo FAIL . ": Attempted to connect to " . $info['ftp']['server'] . " for user " . $info['ftp']['username'] . "\n";
            $result = false;
        } else {
            echo SUCCESS . ": Connected to " . $info['ftp']['server'] . ", for user " . $info['ftp']['username'] . "\n";
        }
        
        // Option -u or --update, updates the revision number
        if (isset($update)) {
            $revision_to_upload = (($revision_update === true) ? $head : $revision_update);
            // Put the revision into the file
            file_put_contents($pwd . '/' . $config['latest'], $revision_to_upload);
            // Upload the file
            $upload = ftp_put($conn_id, $info['ftp']['path'] . '/' . $config['revision_file'], $pwd . '/' . $config['latest'], FTP_BINARY);
            // check upload status
            if (! $upload) {
                echo FAIL . ": Revision was not updated. \n";
            } else {
                echo NOTICE . ": Latest revision was set to revision " . substr($revision_to_upload, 0, 7) . "\n";
            }
            bye();
        }
        
        // Get the latest uploaded commit from file or -r argument
        if (isset($revision_override)) {
            $last_revision = $revision_override;
        }         // Get latest revision and read from file
        elseif (ftp_get($conn_id, $pwd . '/' . $config['latest'], $info['ftp']['path'] . '/' . $config['revision_file'], FTP_BINARY)) {
            $last_revision = file_get_contents($pwd . '/' . $config['latest']);
        } else {
            echo FAIL . ": Cannot find {$config['revision_file']} file on the server. \n";
            echo "         Use -u option to set the revision number to current revision number $head. \n";
            bye();
        }
        echo "         Revision number " . substr($last_revision, 0, 7) . " \n"; // Indent
                                                                                 // is
                                                                                 // OK!!
        
        /**
         * MAIN PART FOR PUSH
         */
        
        // If result is false to end of script, lastest revision is not updated
        $result = true;
        
        // If everything is up to date, exit
        if ($head == $last_revision && ! isset($file_override)) {
            echo "Everything is up to date to the latest revision number " . substr($last_revision, 0, 7) . " \n";
            bye();
        }
        
        // If file is given, upload that.
        if (! isset($file_override)) {
            if ($info['global']['git'] == true) {
                exec("git diff --name-only --diff-filter=[M] $last_revision HEAD", $modified, $e);
                exec("git diff --name-only --diff-filter=[A] $last_revision HEAD", $added, $e);
                exec("git diff --name-only --diff-filter=[D] $last_revision HEAD", $deleted, $e);
                exec("git diff --name-only --diff-filter=[R] $last_revision HEAD", $renamed, $e);
                $files = array(
                        'modified' => $modified,
                        'added' => $added,
                        'deleted' => $deleted,
                        'renamed' => $renamed
                );
            } else {
                // Get the list of changed files as XML
                $files_as_xml = exec('echo $(svn diff --summarize --xml -r ' . $last_revision . ':HEAD) ');
                
                // Convert the XML into array
                $xml = new SimpleXMLElement($files_as_xml);
                $files = array(
                        'modified' => $xml->xpath("//path[@item='modified' and @kind='file']"),
                        'added' => $xml->xpath("//path[@item='added' and @kind='file']"),
                        'deleted' => $xml->xpath("//path[@item='deleted' and @kind='file']")
                );
            }
        } else {
            $files['added'] = array(
                    $file_override
            );
        }
        
        // Copy the modified files
        if (count($files['modified']) != 0) {
            echo "\n Files modified: \n";
        }
        foreach ($files['modified'] as $file) {
            // If it should be ignored, ignore it
            if (preg_grep($info['global']['ignore'], array(
                    (string) $file
            ))) {
                echo IGNORED . ": $file \n";
                continue;
            }
            // Make the relative path and copy the files
            $dir = (string) '/' . dirname($file);
            $dir = str_replace('/.', '', $dir);
            mkdir($pwd . '/' . $config['temp'] . '/main' . $dir, 0755, true);
            copy($file, $pwd . '/' . $config['temp'] . '/main' . $dir . '/' . basename($file));
            $new_files['modified'][] = $pwd . '/' . $config['temp'] . '/zend/main' . $dir . '/' . basename($file);
            echo $file . "\n";
        }
        
        // Copy the added files
        if (count($files['added']) != 0) {
            echo "\n Files added: \n";
        }
        foreach ($files['added'] as $file) {
            // If it should be ignored, ignore it
            if (preg_grep($info['global']['ignore'], array(
                    (string) $file
            ))) {
                echo IGNORED . ": $file \n";
                continue;
            }
            // Make the relative path and copy the files
            $dir = (string) '/' . dirname($file);
            $dir = str_replace('/.', '', $dir);
            mkdir($pwd . '/' . $config['temp'] . '/main' . $dir, 0755, true);
            copy($file, $pwd . '/' . $config['temp'] . '/main' . $dir . '/' . basename($file));
            $new_files['added'][] = $pwd . '/' . $config['temp'] . '/zend/main' . $dir . '/' . basename($file);
            echo $file . "\n";
        }
        
        // Display the deleted files
        if (count($files['deleted']) != 0) {
            echo "\n Files deleted: \n";
        }
        foreach ($files['deleted'] as $file) {
            if (preg_grep($info['global']['ignore'], array(
                    (string) $file
            ))) {
                echo IGNORED . ": $file \n";
                continue;
            }
            echo $file . "\n";
        }
        
        echo "\n Files OK? [y/*]";
        $approve = str_replace("\n", '', fgets(STDIN));
        if ($approve != 'y') {
            delTree($pwd . '/' . $config['temp']);
            bye();
        }
        
        // Encode the files using Zend somewhere in the tmp folder
        exec('sudo date --set="$(date -d \'last year\')"');
        echo exec($config['zend_guard'] . ' --xml-file "' . ((isset($xml_file) ? $xml_file : $pwd . '/' . $config['zend_conf'])) . '"', $r, $e);
        exec('sudo date --set="$(date -d \'next year\')"');
        
        if ($e != 0) {
            echo FAIL . ": Zend encoding failed.\n         Use -i or --ignore to ignore Zend errors.\n"; // Spaces
                                                                                                         // are
                                                                                                         // OK
            $result = false;
            break;
        }
        
        if (isset($zip)) {
            $error = array(
                    'Unzip was done succcessfully.',
                    'Cannot unzip!'
            );
            
            // Zip the files
            chdir("{$pwd}/{$config['temp']}/zend/main/");
            exec("zip -r ../../zip.zip .", $r, $e); // Saves the zip file to
                                                    // $config['temp']
            if ($e != 0) {
                echo FAIL . ": Zip process failed!";
                bye();
            }
            chdir($pwd);
            
            // Delete files
            delTree($pwd . '/' . $config['temp'] . '/zend');
            delTree($pwd . '/' . $config['temp'] . '/main');
            mkdir($pwd . '/' . $config['temp'], 0755, true);
            mkdir($pwd . '/' . $config['temp'] . '/zend', 0755, true);
            mkdir($pwd . '/' . $config['temp'] . '/main/', 0755, true);
            
            // Set zip file
            $zip = ($zip !== true) ? $zip : $pwd . '/' . $config['temp'] . '/zip.zip';
            
            copy(dirname(__FILE__) . "/inc/dump.php", $pwd . '/' . $config['temp'] . '/main/dump.php');
            
            // Zend the file
            // Encode the files using Zend somewhere in the tmp folder
            exec('sudo date --set="$(date -d \'last year\')"');
            echo exec($config['zend_guard'] . ' --xml-file "' . ((isset($xml_file) ? $xml_file : $pwd . '/' . $config['zend_conf'])) . '"', $r, $e);
            exec('sudo date --set="$(date -d \'next year\')"');
            
            if ($e != 0) {
                echo FAIL . ": Zend encoding failed.\n         Use -i or --ignore to ignore Zend errors.\n"; // Spaces
                                                                                                             // are
                                                                                                             // OK
                $result = false;
                break;
            }
            
            // Upload dump.php
            $upload = ftp_put($conn_id, $info['ftp']['path'] . '/dump.php', $pwd . '/' . $config['temp'] . '/zend/main/dump.php', FTP_BINARY);
            
            // check upload status
            if (! $upload) {
                echo FAIL . ": Unable to upload dump.php \n";
                bye();
            }
            // Upload zip
            $upload = ftp_put($conn_id, $info['ftp']['path'] . '/zip.zip', $zip, FTP_BINARY);
            
            // check upload status
            if (! $upload) {
                echo FAIL . ": Unable to upload zipped file. \n";
                bye();
            } else {
                echo SUCCESS . ": Zipped file uploaded.\n";
            }
            
            // Unzip
            $unzip = file_get_contents("http://" . $info['ftp']['server'] . "/dump.php?a1=unzip&" . rand(1, 1000));
            echo (($unzip == 0) ? SUCCESS : FAIL) . ": " . $error[$unzip] . PHP_EOL;
            
            $delete = ftp_delete($conn_id, $info['ftp']['path'] . '/dump.php');
            if (! $delete) {
                echo WARNING . ": dump.php could not be deleted, delete manually.\n";
            }
            
            $delete = ftp_delete($conn_id, $info['ftp']['path'] . '/zip.zip');
            if (! $delete) {
                echo WARNING . ": Zipped file could not be deleted, delete manually.\n";
            }
        } else {
            // Upload the encoded files using FTP
            // Upload modified files
            foreach ($new_files['modified'] as $file) {
                $dir = dirname($file);
                $relative_dir = str_replace($pwd . '/' . $config['temp'] . "/zend/main", '', $dir);
                // Rename the old file
                if (ftp_rename($conn_id, $info['ftp']['path'] . $relative_dir . '/' . basename($file), 
                        $info['ftp']['path'] . $relative_dir . '/' . basename($file) . ".old")) {
                    echo NOTICE . ": .old file was created for " . $info['ftp']['path'] . $relative_dir . '/' . basename($file) . " \n";
                } else {
                    echo WARNING . ": .old file was not created for $file \n";
                }
                $upload = ftp_put($conn_id, $info['ftp']['path'] . $relative_dir . '/' . basename($file), $file, FTP_BINARY);
                // check upload status
                if (! $upload) {
                    echo FAIL . ": FTP upload has failed!: $file \n";
                    $result = false;
                } else {
                    echo SUCCESS . ": Uploaded $file to " . $info['ftp']['path'] . $relative_dir . '/' . basename($file) . " \n";
                }
            }
            
            // Upload added files
            foreach ($new_files['added'] as $file) {
                $dir = dirname($file);
                $relative_dir = str_replace($pwd . '/' . $config['temp'] . "/zend/main", '', $dir);
                // TODO: following line makes the upload slow
                ftp_mksubdirs($conn_id, '/', $info['ftp']['path'] . $relative_dir);
                $upload = ftp_put($conn_id, $info['ftp']['path'] . $relative_dir . '/' . basename($file), $file, FTP_BINARY);
                // check upload status
                if (! $upload) {
                    echo FAIL . ": FTP upload has failed!: $file \n";
                    $result = false;
                } else {
                    echo SUCCESS . ": Uploaded $file to " . $info['ftp']['path'] . $relative_dir . '/' . basename($file) . " \n";
                }
            }
        }
        
        // Remove the deleted files on FTP
        foreach ($files['deleted'] as $file) {
            // If it should be ignored, ignore it
            if (preg_grep($info['global']['ignore'], array(
                    (string) $file
            ))) {
                continue;
            }
            $dir = (string) '/' . dirname($file);
            $dir = str_replace('/.', '', $dir);
            $relative_dir = str_replace($pwd, '', $dir);
            $delete = ftp_delete($conn_id, $info['ftp']['path'] . $relative_dir . '/' . basename($file));
            // check delete status
            if (! $delete) {
                echo FAIL . ": FTP delete has failed! " . $info['ftp']['path'] . $relative_dir . '/' . basename($file) . "\n";
            } else {
                echo SUCCESS . ": Deleted, $file in " . $info['ftp']['path'] . $relative_dir . '/' . basename($file) . " \n";
            }
        }
        
        // Write the latest revision to the file
        if ($result === true && ! isset($revision_override) && ! isset($file_override)) {
            file_put_contents($pwd . '/' . $config['latest'], $head);
            // Upload the file
            $upload = ftp_put($conn_id, $info['ftp']['path'] . '/' . $config['revision_file'], $pwd . '/' . $config['latest'], FTP_BINARY);
            // check upload status
            if (! $upload) {
                echo WARNING . ": Revision could not updated. \n";
            } else {
                echo NOTICE . ": Latest revision was set to revision $head \n";
            }
        }
        
        // close the FTP stream
        ftp_close($conn_id);
        
        // End of action
        break;
    
    /*
     * =======================================================================
     * DATABASE
     * =======================================================================
     */
    case 'db':
        
        /**
         * HELP FOR DATABASE
         */
        
        $help = "Usage: backup|restore|sync|create [OPTION]

Options:

    --server              Does all backup and restore on server.

    -f FILE               Restore from FILE, or backup to FILE.
    --file=FILE

    -t <TABLE>            Backup only from TABLE
    --table<=TABLE>

    -s                    Only backup structure, not data
    --structure           *** UPON RESTORE DATA WILL BE LOST***";
        
        /**
         * GET OPTIONS FOR DATABASE
         */
        
        foreach ($args as $key => $value) {
            switch ($key) {
                case 'h':
                case 'help':
                    echo $help . $help_all;
                    bye();
                    break;
                case 'server':
                    $local = false;
                    break;
                case 'file':
                case 'f':
                    $file = $value;
                    break;
                case 's':
                case 'structure':
                    $structure = true;
                    break;
                case 't':
                case 'table':
                    $table = $value;
                    break;
            }
        }
        
        /**
         * START OF DATABASE
         */
        
        $file = (isset($file)) ? $file : $pwd . '/' . $config['sql'];
        if (! file_exists($file) && $action2 == 'restore') {
            echo FAIL . ": File '$file' does not exist.\nYou can use -f option to specify a file.\n";
            bye();
        }
        if (file_exists($file) && $action2 == 'backup') {
            echo WARNING . ": File '$file' exists.\n         You can use -f option to save to another file.\n         Overwrite?(y/*)\n";
            if (str_replace("\n", '', fgets(STDIN)) != 'y') {
                bye();
            }
        }
        
        $error = array(
                'Done succcessfully.',
                'Cannot connect to MySQL.',
                'Cannot connect to the database.',
                'File does not exist.',
                'Table not found'
        );
        
        // Workaround
        if ($local !== false) {
            $local = true;
        }
        
        if ($local === true && $action2 != 'create' && $action2 != NULL && $action2 != 'sync') {
            copy(dirname(__FILE__) . "/inc/dump.php", $pwd . '/dump.php');
        } elseif (($local !== true && $action2 != 'create' && $action2 != NULL) | $action2 == 'sync') {
            copy(dirname(__FILE__) . "/inc/dump.php", $pwd . '/' . $config['temp'] . '/main/dump.php');
            
            // Zend the file
            // Encode the files using Zend somewhere in the tmp folder
            exec('sudo date --set="$(date -d \'last year\')"');
            echo exec($config['zend_guard'] . ' --xml-file "' . ((isset($xml_file) ? $xml_file : $pwd . '/' . $config['zend_conf'])) . '"', $r, $e);
            exec('sudo date --set="$(date -d \'next year\')"');
            
            if ($e != 0) {
                echo FAIL . ": Zend encoding failed.\n         Use -i or --ignore to ignore Zend errors.\n"; // Spaces
                                                                                                             // are
                                                                                                             // OK
                $result = false;
                break;
            }
            // set up basic connection
            $conn_id = ftp_connect($info['ftp']['server']);
            
            // Login with username and password
            $login_result = ftp_login($conn_id, $info['ftp']['username'], $info['ftp']['password']);
            ftp_pasv($conn_id, true);
            
            // Check connection
            if ((! $conn_id) || (! $login_result)) {
                echo FAIL . ": FTP connection has failed! \n";
                echo FAIL . ": Attempted to connect to " . $info['ftp']['server'] . " for user " . $info['ftp']['username'] . "\n";
                $result = false;
            } else {
                echo SUCCESS . ": Connected to " . $info['ftp']['server'] . ", for user " . $info['ftp']['username'] . "\n";
            }
            
            // Upload dump.php
            $upload = ftp_put($conn_id, $info['ftp']['path'] . '/dump.php', $pwd . '/' . $config['temp'] . '/zend/main/dump.php', FTP_BINARY);
            // check upload status
            if (! $upload) {
                echo FAIL . ": Unable to upload dump.php \n";
                break;
            }
        }
        
        function backup ($is_local)
        {
            global $pwd, $file, $conn_id, $info, $error, $table, $structure;
            $table = (isset($table)) ? $table : 'all';
            if ($table == 'all' && isset($structure)) {
                echo WARNING . ": DATA WILL BE LOST UPON RESTORE!! \n         Are you sure to get ONLY schema of all tables? (enter fuckme to continue)\n";
                if (str_replace("\n", '', fgets(STDIN)) != 'fuckme') {
                    bye();
                }
            }
            $structure = (isset($structure)) ? 1 : 0;
            if ($is_local) {
                exec("php '{$pwd}/dump.php' backup {$table} {$structure} " . ((isset($file)) ? $file : ''), $return, $st);
                echo (($st == 0) ? SUCCESS : FAIL) . ": " . $error[$st] . PHP_EOL;
            } else {
                $result = file_get_contents("http://" . $info['ftp']['server'] . "/dump.php?a1=backup&a2={$table}&a3={$structure}&m=" . rand(1, 1000));
                echo (($result == 0) ? SUCCESS : FAIL) . ": " . $error[$result] . "\n";
                if ($result == 0) {
                    exec("wget -O '$file' {$info['ftp']['server']}/sql.gz?rand=" . rand(1, 1000), $r, $e);
                    if ($e == 0) {
                        echo SUCCESS . ": Backup successfuly saved to '$file'\n";
                    } else {
                        echo FAIL . ": Failed, something happened during download.\n";
                    }
                }
            }
        }
        
        function restore ($is_local)
        {
            // TODO: Temporary
            // if ($is_local === false) {
            // echo NOTICE . ": Restore to server is not supported yet :) \n";
            // bye();
            // }
            global $pwd, $file, $conn_id, $info, $error, $table, $structure;
            if ($is_local) {
                copy(dirname(__FILE__) . "/inc/dump.php", $pwd . '/dump.php');
                exec("php '{$pwd}/dump.php' restore " . $file, $return, $st) . "";
                echo (($st == 0) ? SUCCESS : FAIL) . ": " . $error[$st] . PHP_EOL;
            } else {
                // Upload dump.php
                $upload = ftp_put($conn_id, $info['ftp']['path'] . '/sql.gz', $file, FTP_BINARY);
                // check upload status
                if (! $upload) {
                    echo FAIL . ": Unable to upload $file \n";
                    break;
                }
                $result = file_get_contents("http://" . $info['ftp']['server'] . "/dump.php?a1=restore&" . rand(1, 1000));
                echo (($result == 0) ? SUCCESS : FAIL) . ": " . $error[$result] . PHP_EOL;
            }
        }
        
        switch ($action2) {
            case 'sync':
                backup(false); // Backup from server
                restore(true); // Restore locally
                break;
            case 'backup':
                backup($local);
                break;
            
            case 'restore':
                restore($local);
                break;
            
            case 'create':
                require dirname(__FILE__) . '/inc/xmlapi.php';
                $cpanel = new xmlapi($info['ftp']['server']);
                $cpanel->set_debug((isset($verbose)) ? 1 : 0);
                $cpanel->set_output("array");
                $cpanel->set_port(2083);
                $fail = false;
                
                // Set credentials
                $cpanel->password_auth($info['ftp']['username'], $info['ftp']['password']);
                
                // Create Database
                $result = $cpanel->api1_query($info['ftp']['username'], "Mysql", "adddb", 
                        array(
                                $config['dbname']
                        ));
                if (isset($result['error'])) {
                    echo FAIL . ": " . $result['error'] . "\n";
                    $fail = true;
                } else {
                    echo SUCCESS . ": Database {$info['ftp']['username']}_{$config['dbname']} created for {$info['ftp']['server']}. \n";
                }
                
                // Create A User
                $dbpassword = generateRandomString();
                $result = $cpanel->api1_query($info['ftp']['username'], "Mysql", "adduser", 
                        array(
                                $config['dbusername'],
                                $dbpassword
                        ));
                if (isset($result['error'])) {
                    echo FAIL . ": " . $result['error'];
                    $fail = true;
                } else {
                    echo SUCCESS . ": Mysql user {$info['ftp']['username']}_{$config['dbusername']} created for {$info['ftp']['server']}. \n";
                }
                
                // Give Permissions
                $result = $cpanel->api1_query($info['ftp']['username'], "Mysql", "adduserdb", 
                        array(
                                $config['dbname'],
                                $config['dbusername'],
                                'all'
                        ));
                if (isset($result['error'])) {
                    echo FAIL . ": " . $result['error'] . "\n";
                    $fail = true;
                } else {
                    echo SUCCESS . ": Mysql user {$info['ftp']['username']}_{$config['dbusername']} was given permissions for database. \n";
                }
                
                if (! $fail) {
                    $info->set('db', 'username', $info['ftp']['username'] . '_' . $config['dbusername']);
                    $info->set('db', 'password', $dbpassword);
                    $info->set('db', 'dbname', $info['ftp']['username'] . '_' . $config['dbname']);
                    $info->save();
                    echo NOTICE . ": Database credentials was saved to {$config_file}.\n";
                } else {
                    echo FAIL . ": Failed.\n";
                }
                break;
            
            default:
                echo $help . $help_all;
                break;
        }
        
        // Delete uneeded files
        if ($local !== true && $action2 != 'create' && $action2 != NULL) {
            // If is not connected, connect again.
            if (ftp_pwd($conn_id) === false) {
                echo FAIL . ": FTP connection lost, trying to reconnect.\n";
                ftp_close($conn_id);
                $conn_id = ftp_connect($info['ftp']['server']);
                // Login with username and password
                $login_result = ftp_login($conn_id, $info['ftp']['username'], $info['ftp']['password']);
                ftp_pasv($conn_id, true);
                // Check connection
                if ((! $conn_id) || (! $login_result)) {
                    echo FAIL . ": FTP connection has failed! \n";
                    echo FAIL . ": Attempted to connect to " . $info['ftp']['server'] . " for user " . $info['ftp']['username'] . "\n";
                } else {
                    echo SUCCESS . ": Connected to " . $info['ftp']['server'] . ", for user " . $info['ftp']['username'] . "\n";
                }
            }
            
            $delete = ftp_delete($conn_id, $info['ftp']['path'] . '/dump.php');
            if (! $delete) {
                echo WARNING . ": dump.php could not be deleted, delete manually.\n";
            }
            
            $delete = ftp_delete($conn_id, $info['ftp']['path'] . '/sql.gz');
            if (! $delete) {
                echo WARNING . ": sql.gz could not be deleted on server (if created), delete manually.\n";
            }
            
            ftp_close($conn_id);
        } elseif ($local === true && $action2 != 'create' && $action2 != NULL) {
            unlink($pwd . '/dump.php');
        }
        // End of action
        break;
    
    /*
     * =======================================================================
     * /* ACCOUNT
     * /*=======================================================================
     */
    case 'account':
        
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
        require dirname(__FILE__) . '/inc/xmlapi.php';
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
        }
        
        if ($config['whm']['password'] == '') {
            echo "WHM Admin Password: \n";
            $root_pass = str_replace("\n", '', fgets(STDIN));
        } else {
            $root_pass = $config['whm']['password'];
        }
        
        $xmlapi = new xmlapi($config['whm']['ip']);
        $xmlapi->set_debug((isset($verbose)) ? 1 : 0);
        $xmlapi->set_output("array");
        $xmlapi->password_auth($root_user, $root_pass);
        
        // Get User Info
        echo "Username: \n";
        $username = str_replace("\n", '', fgets(STDIN));
        echo "Domain: \n";
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
        
        // End of action
        break;
    /*
     * =======================================================================
     * /* CONFIG
     * /*=======================================================================
     */
    case 'config':
        
        /**
         * HELP FOR CONFIG
         */
        
        $help = "Usage: [OPTION]

Options:";
        
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
            }
        }
        
        /**
         * START FOR CONFIG
         */
        
        if ($info['db']['username'] == '' || $info['db']['password'] == '' || $info['db']['dbname'] == '') {
            echo FAIL . ": Database credentials are not in {$config_file}.\n         Create a database first using 'db create'\n";
            break;
        }
        
        $new_config['user'] = $info['db']['username'];
        $new_config['password'] = $info['db']['password'];
        $new_config['server'] = 'localhost';
        $new_config['dbname'] = $info['db']['dbname'];
        $new_config['prefix'] = 'pre_';
        $serialize = base64_encode(serialize($new_config));
        
        $data = "<?php
  error_reporting(E_ALL);
  \$config = '{$serialize}';
  \$cid = 1;";
        
        if (file_put_contents($pwd . '/' . $config['temp'] . '/main/index.php', $data)) {
            echo SUCCESS . ": Config file generated.\n";
        } else {
            echo FAIL . ": Config file NOT generated !\n";
            bye();
        }
        // Zend the file
        // Encode the files using Zend somewhere in the tmp folder
        exec('sudo date --set="$(date -d \'last year\')"');
        echo exec($config['zend_guard'] . ' --xml-file "' . ((isset($xml_file) ? $xml_file : $pwd . '/' . $config['zend_conf'])) . '"', $r, $e);
        exec('sudo date --set="$(date -d \'next year\')"');
        
        if ($e != 0) {
            echo FAIL . ": Zend encoding failed.\n         Use -i or --ignore to ignore Zend errors.\n"; // Spaces
                                                                                                         // are
                                                                                                         // OK
            $result = false;
            break;
        }
        // set up basic connection
        $conn_id = ftp_connect($info['ftp']['server']);
        
        // Login with username and password
        $login_result = ftp_login($conn_id, $info['ftp']['username'], $info['ftp']['password']);
        ftp_pasv($conn_id, true);
        
        // Check connection
        if ((! $conn_id) || (! $login_result)) {
            echo FAIL . ": FTP connection has failed! \n";
            echo FAIL . ": Attempted to connect to " . $info['ftp']['server'] . " for user " . $info['ftp']['username'] . "\n";
            $result = false;
        } else {
            echo SUCCESS . ": Connected to " . $info['ftp']['server'] . ", for user " . $info['ftp']['username'] . "\n";
        }
        
        // Rename the old file
        if (ftp_rename($conn_id, $info['ftp']['path'] . '/inc/index.php', $info['ftp']['path'] . '/inc/index.php.old')) {
            echo NOTICE . ": .old file was created for inc/index.php \n";
        } else {
            echo WARNING . ": .old file was not created for {$info['ftp']['path']}/inc/index.php \n";
        }
        
        // Upload index.php
        $upload = ftp_put($conn_id, $info['ftp']['path'] . '/inc/index.php', $pwd . '/' . $config['temp'] . '/zend/main/index.php', FTP_BINARY);
        // check upload status
        if (! $upload) {
            echo FAIL . ": Unable to upload index.php \n";
            bye();
        }
        
        // End of action
        break;
    
    // End of switch
}

// Remove the temp directory
delTree($pwd . '/' . $config['temp']);

