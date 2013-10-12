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

	public static function get_diff($last_revision)
	{
		global $info;
        if ($info['global']['git'] == true) {
            exec("git diff --name-only --diff-filter=[M] $last_revision HEAD", $modified, $exit);
            exec("git diff --name-only --diff-filter=[A] $last_revision HEAD", $added, $exit);
            exec("git diff --name-only --diff-filter=[D] $last_revision HEAD", $deleted, $exit);
            exec("git diff --name-only --diff-filter=[R] $last_revision HEAD", $renamed, $exit);
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
		return $files;
	}
}
