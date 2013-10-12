<?php
/**
 * Zend Class
 */
class Zend
{
	public static function modify_zend_xml($xml_file, $ignore_errors = false)
	{
		global $pwd, $config;
    	if (! file_exists($xml_file)) {
        	echo FAIL . ": Zend Guard XML file not found at $pwd/{$xml_file}
        	You can use -x or --xml option to override guard.xml. \n";
			return false;
    	} else {
        	$xml_conf = file_get_contents("$pwd/{$xml_file}");
    	}
    	$new_xml_file = preg_replace(
        	array(
            	'/(targetDir=".*?)+(")/',
            	'/(source path=".*?)+(")/',
            	'/<ignoreErrors value="((true)|(false))"\/>/'
        	),
        	array(
            	'targetDir="' . $pwd . '/' . $config['temp'] . '/zend"',
            	'source path="' . $pwd . '/' . $config['temp'] . '/main"',
            	(($ignore_errors==true) ? '<ignoreErrors value="true"/>' : '<ignoreErrors value="false"/>')),
			$xml_conf
		);

    	file_put_contents("$pwd/{$config['temp']}/guard.xml", $new_xml_file);
		return true;
	}

	public static function zend_guard()
	{
		global $config;
    	exec('sudo date --set="$(date -d \'last year\')"');
		echo $config['zend_guard'] . ' --xml-file "' . $config['temp'] . '/guard.xml' . '"';
    	echo exec($config['zend_guard'] . ' --xml-file "' . $config['temp'] . '/guard.xml' . '"', $return, $exit);
    	exec('sudo date --set="$(date -d \'next year\')"');
		$return = '';
    	if ($exit != 0) {
        	echo FAIL . ": Zend encoding failed.\n         Use -i or --ignore to ignore Zend errors.\n"; // Spaces are OK
        	return false;
    	} else {
			echo SUCCESS . ": Zend enconding successful. \n";
			return true;
		}
	}
}
