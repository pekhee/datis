<?php

/**
 * FUNCTIONS NEEDED
 */

 // Makes directory recursively on FTP
function ftp_mksubdirs($ftpcon,$ftpbasedir,$ftpath){
   @ftp_chdir($ftpcon, $ftpbasedir); // /var/www/uploads
   $parts = explode('/',$ftpath); // 2013/06/11/username
   foreach($parts as $part){
      if(!@ftp_chdir($ftpcon, $part)){
         ftp_mkdir($ftpcon, $part);
         ftp_chdir($ftpcon, $part);
         //ftp_chmod($ftpcon, 0777, $part);
      }
   }
}

// Removes Directories Recursively locally
function delTree($dir) { 
   $files = array_diff(scandir($dir), array('.','..')); 
    foreach ($files as $file) { 
      (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file"); 
    } 
    return rmdir($dir); 
  } 


