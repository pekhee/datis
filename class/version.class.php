<?php
/**
 * Version Class
 */
class Version
{
	public static function get_head()
	{
		global $info, $result;
    	if ($info['global']['git'] == true) {
        	$head = exec("git rev-parse --verify HEAD");
        	$origin = exec("git remote");
        	exec("git log {$origin}..", $diff, $exit);
			$exit='';
        	if (count($diff) != 0) {
            	echo WARNING . ": You have unpushed commits!\n         Revision will not be updated.\n";
            	$result = false;
        	}
    	} else {
        	exec('svn update -q');
        	// latest revision from svn
        	preg_match("/[0-9]+/", exec("svnversion"), $matches);
        	$head = $matches[0];
    	}
		return $head;
	}
}
