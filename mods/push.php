<?php
/*
 * =======================================================================
 * PUSH
 * =======================================================================
 */

// Default case is uploading latest changes to server

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

-d <DIR>              Upload all files in givern directory.
--directory=<DIR>

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
    case 'u':
    case 'update':
        $update = true;
        $revision_update = (isset($value)) ? $value : '';
        break;
    case 'f':
    case 'file':
        $file_override = $value;
        break;
    case 'z':
    case 'zip':
        $zip = $value;
        break;
    case 'd':
    case 'directory':
        $directory_override = $value;
        break;
    }
}

/**
 * START
 */

// If result is false to end of script, lastest revision is not updated
$result = true;
$head = Version::get_head();

$ftp = new Ftp($info);
if (!$ftp->connect()) {
	$result == false;
	bye();
}

// Option -u or --update, updates the revision number
if (isset($update)) {
    $revision_to_upload = (($revision_update === true) ? $head : $revision_update);
    $ftp->set_revision_server($revision_to_upload);
    bye();
}

// Get the latest uploaded commit from file or -r argument
if (isset($revision_override)) {
    $last_revision = $revision_override;
    echo "         Revision number " . substr($last_revision, 0, 7) . " \n";
} elseif (isset($file_override) || isset($directory_override)) {
    // Get latest revision and read from file
} else {
	$last_revision = $ftp->get_revision_server();
	if (!$last_revision) {
		bye();
	}
}

/**
 * MAIN PART FOR PUSH
 */

// If everything is up to date, exit
if ($head == $last_revision && ! isset($file_override) && ! isset($directory_override)) {
    echo "Everything is up to date to the latest revision number " . substr($last_revision, 0, 7) . " \n";
    bye();
}

// If file is given, upload that.
if (! isset($file_override) && ! isset($directory_override)) {
	$files = Version::get_diff($last_revision);
} elseif (isset($file_override) && ! isset($directory_override)) {
    $files['added'] = array(
        $file_override
    );
} elseif (isset($directory_override) && ! isset($file_override)) {
    $files['modified'] = Files::find_all_files($directory_override);
}

// Copy the modified files
if (count($files['modified']) != 0) {
    echo "\n Files modified: \n";
}
foreach ($files['modified'] as $file) {
    // If it should be ignored, ignore it
    if (Files::is_ignored($file)) {
        echo IGNORED . ": $file \n";
        continue;
    }
    // Make the relative path and copy the files
    $new_files['modified'][] = Files::copy_to_temp($file);
    echo $file . "\n";
}

// Copy the added files
if (count($files['added']) != 0) {
    echo "\n Files added: \n";
}
foreach ($files['added'] as $file) {
    // If it should be ignored, ignore it
    if ( Files::is_ignored($file)) {
        echo IGNORED . ": $file \n";
        continue;
    }
    // Make the relative path and copy the files
    $new_files['added'][] = Files::copy_to_temp($file);
    echo $file . "\n";
}

// Display the deleted files
if (count($files['deleted']) != 0) {
    echo "\n Files deleted: \n";
}
foreach ($files['deleted'] as $file) {
    if ( Files::is_ignored($file)) {
        echo IGNORED . ": $file \n";
        continue;
    }
    echo $file . "\n";
}

echo "\n Files OK? [y/*]";
$approve = str_replace("\n", '', fgets(STDIN));
if ($approve != 'y') {
    bye();
}

// Zend Guard
if (!Zend::zend_guard()) {
	bye();
}

if (isset($zip)) {
    $error = array(
        'Unzip was done succcessfully.',
        'Cannot unzip!'
    );

    // Zip the files
    chdir("{$pwd}/{$config['temp']}/zend/main/");
    exec("zip -r ../../zip.zip .", $return, $exit); // Saves the zip file to
    if ($exit != 0) {
        echo FAIL . ": Zip process failed!\n";
        bye();
    } else {
		echo SUCCESS . ": Zip process is done.\n";
	}
    chdir($pwd);

    // Delete files
    FIles::del_temp();

    // Set zip file
    $zip = ($zip !== true) ? $zip : $pwd . '/' . $config['temp'] . '/zip.zip';
    copy(dirname(__FILE__) . "/../inc/dump.php", $pwd . '/' . $config['temp'] . '/main/dump.php');

	// Zend Guard
    if (!Zend::zend_guard()) {
        break;
    }

    // Upload dump.php
	if (!$ftp->put($config['temp'] . '/zend/main/dump.php', '/dump.php')) {
		bye();
	}

	 //Upload zip.zip
	if (!$ftp->put($zip, '/zip.zip', false)) {
		bye();
	}
    // Unzip
    $unzip = file_get_contents("http://" . $info['ftp']['server'] . "/dump.php?a1=unzip&" . rand(1, 1000));
    echo (($unzip == 0) ? SUCCESS : FAIL) . ": " . $error[$unzip] . PHP_EOL;
	$ftp->del('dump.php');
	$ftp->del('zip.zip');
	$ftp->close();

} else {
    // Upload the encoded files using FTP
    // Upload modified files
    foreach ($new_files['modified'] as $file) {
        // Rename the old file
        $ftp->create_old($file);
		$ftp->put_rel($file);
    }

    // Upload added files
    foreach ($new_files['added'] as $file) {
        // TODO: following line makes the upload slow
        $ftp->ftp_mksubdirs($file);
		$ftp->put_rel($file);
    }
}

// Remove the deleted files on FTP
foreach ($files['deleted'] as $file) {
    // If it should be ignored, ignore it
    if ( Files::is_ignored($file)) {
        continue;
    }
    $dir = (string) '/' . dirname($file);
    $dir = str_replace('/.', '', $dir);
    $relative_dir = str_replace($pwd . '/', '', $dir);
	$ftp->del($relative_dir . '/' . basename($file));
}

// Write the latest revision to the file
if ($result === true && ! isset($revision_override) && ! isset($file_override) && ! isset($directory_override)) {
	$ftp->set_revision_server($head);
}

// close the FTP stream
$ftp->close();
