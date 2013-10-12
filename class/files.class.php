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

	public static function copy_to_temp($file)
	{
		global $config, $pwd;
        $dir = (string) '/' . dirname($file);
        $dir = str_replace('/.', '', $dir);
        mkdir($pwd . '/' . $config['temp'] . '/main' . $dir, 0755, true);
        copy($file, $pwd . '/' . $config['temp'] . '/main' . $dir . '/' . basename($file));
        return $pwd . '/' . $config['temp'] . '/zend/main' . $dir . '/' . basename($file);
	}

	public static function del_tree($dir)
	{
    	$files = array_diff(
			scandir($dir), array(
            	'.',
            	'..'
    		)
		);
    	foreach ($files as $file) {
        	(is_dir("$dir/$file")) ? self::del_tree("$dir/$file") : unlink("$dir/$file");
    	}
    	return rmdir($dir);
	}

	public static function find_all_files ($dir)
	{
    	if (substr($dir, - 1) == '/') {
        	$dir = substr($dir, 0, -1);
    	}

    	$root = scandir($dir);
    	foreach ($root as $value) {
        	if ($value === '.' || $value === '..') {
            	continue;
        	}
        	if (is_file("$dir/$value")) {
            	$result[] = "$dir/$value";
            	continue;
        	}
        	foreach (find_all_files("$dir/$value") as $value) {
            	$result[] = $value;
        	}
    	}
    	return $result;
	}
}
