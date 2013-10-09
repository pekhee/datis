<?php
/**
 * Files Operations
 */
class Files
{
	public static function is_ignored($file, $pattern)
	{
		return preg_grep($pattern, array((string) $file));
	}

	public static function relative_file($file)
	{
		global $config, $pwd;
        $dir = dirname($file);
        $relative_dir = str_replace($pwd . '/' . $config['temp'] . "/zend/main/", '', $dir);
		return $relative_dir . '/' . basename($file);
	}

	public static function relative_path($file)
	{
		global $config, $pwd;
        $dir = dirname($file);
        $relative_dir = str_replace($pwd . '/' . $config['temp'] . "/zend/main/", '', $dir);
		return $relative_dir;
	}
}
