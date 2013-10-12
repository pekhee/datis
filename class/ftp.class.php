<?php
/**
 * FTP Class
 */
class Ftp
{
	public $ftp_server;
	public $ftp_username;
	public $ftp_password;
	public $ftp_path;
	private $conn_id;

	public  function __construct($info)
	{
		$this->ftp_password = $info['ftp']['password'];
		$this->ftp_username = $info['ftp']['username'];
		$this->ftp_server = $info['ftp']['server'];
		$this->ftp_path = $info['ftp']['path'];
	}

	public function connect()
	{
    	// set up basic connection
    	$this->conn_id = ftp_connect($this->ftp_server);

    	// Login with username and password
    	$login_result = ftp_login($this->conn_id, $this->ftp_username, $this->ftp_password);
    	ftp_pasv($this->conn_id, true);

    	// Check connection
    	if ((! $this->conn_id) || (! $login_result)) {
        	echo FAIL . ": FTP connection has failed! \n";
        	echo FAIL . ": Attempted to connect to " . $this->ftp_server . " for user " . $this->ftp_username . "\n";
        	return false;
    	} else {
        	echo SUCCESS . ": Connected to " . $this->ftp_server . ", for user " . $this->ftp_username . "\n";
			return true;
    	}
	}

	public function close()
	{
    	ftp_close($this->conn_id);
	}

	public  function put($from, $dest, $rel_path=true)
	{
		global $pwd;
        $upload = ftp_put($this->conn_id, $this->ftp_path . $dest, ($rel_path ? ($pwd . '/' . $from) : ($from) ), FTP_BINARY);
        // check upload status
        if (! $upload) {
            echo FAIL . ": Cannot upload file '{$from}' dest '{$this->ftp_path}{$dest}' \n";
			return false;
        } else {
            echo SUCCESS . ": '{$from}' uploaded to '{$this->ftp_path}{$dest}' \n";
			return true;
        }
	}

	public function put_rel($file)
	{
		return $this->put($file, Files::relative_file($file), false);
	}


	public  function get($from, $dest)
	{
		global $pwd;
        $download = ftp_get($this->conn_id, $pwd . '/' . $dest, $this->ftp_path . '/' . $from, FTP_BINARY);
		if (! $download) {
			echo FAIL . ": Cannot download file from '{$from}' to '{$dest}' \n";
			return false;
		} else {
			echo SUCCESS . ": File '{$from}' downloaded to '{$dest}' \n";
			return true;
		}
	}

	public function del($file)
	{
        $delete = ftp_delete($this->conn_id, $this->ftp_path . '/' . $file);
        if (! $delete) {
            echo WARNING . ": '{$file}' could not be deleted, delete manually.\n";
			return false;
        }
		return true;
	}

	public function create_old($file)
	{
		$file = Files::relative_file($file);
        if (ftp_rename(
			$this->conn_id, $this->ftp_path .  $file,
            $this->ftp_path .  $file . ".old"
		)) {
            echo NOTICE . ": .old file was created for '{$file}'\n";
			return true;
		} else {
            echo WARNING . ": .old file was not created for '{$file}' \n";
			return false;
		}

	}

	public function ftp_mksubdirs ($file)
	{
    	@ftp_chdir($this->conn_id, '/');
    	$parts = explode('/', Files::relative_path($file));
    	foreach ($parts as $part) {
        	if (! @ftp_chdir($this->conn_id, $part)) {
            	ftp_mkdir($this->conn_id, $part);
            	ftp_chdir($this->conn_id, $part);
        	}
    	}
	}

	public function set_revision_server($revision_to_upload)
	{
		global $config, $pwd;
        file_put_contents($pwd . '/' . $config['latest'], $revision_to_upload);
        if (!$this->put($config['latest'], '/' . $config['revision_file'])) {
            echo FAIL . ": Revision was not updated. \n";
			return false;
        } else {
            echo NOTICE . ": Latest revision was set to revision " . substr($revision_to_upload, 0, 7) . "\n";
			return true;
        }
	}

	public  function get_revision_server()
	{
		global $config, $pwd;
		if ($this->get($config['revision_file'], $config['latest'])) {
        	$last_revision = file_get_contents($pwd . '/' . $config['latest']);
        	echo "         Server revision number: " . substr($last_revision, 0, 7) . " \n"; // Indent is OK!!
			return $last_revision;
        } else {
            echo FAIL . ": Cannot find {$config['revision_file']} file on the server. \n";
            echo "         Use -u option to set the revision number to current revision number. \n";
			return false;
        }

	}
}
