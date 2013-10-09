<?php
/**
 * FTP Class
 */
class Ftp
{
	public static $ftp_server;
	public static $ftp_username;
	public static $ftp_password;
	public static $ftp_path;

	public static function set_config($info)
	{
		self::$ftp_password = $info['ftp']['password'];
		self::$ftp_username = $info['ftp']['username'];
		self::$ftp_server = $info['ftp']['server'];
		self::$ftp_path = $info['ftp']['path'];
	}

	public static function connect()
	{
    	// set up basic connection
    	$conn_id = ftp_connect(self::$ftp_server);

    	// Login with username and password
    	$login_result = ftp_login($conn_id, self::$ftp_username, self::$ftp_password);
    	ftp_pasv($conn_id, true);

    	// Check connection
    	if ((! $conn_id) || (! $login_result)) {
        	echo FAIL . ": FTP connection has failed! \n";
        	echo FAIL . ": Attempted to connect to " . self::$ftp_server . " for user " . self::$ftp_username . "\n";
        	return false;
    	} else {
        	echo SUCCESS . ": Connected to " . self::$ftp_server . ", for user " . self::$ftp_username . "\n";
    	}
	}
}
