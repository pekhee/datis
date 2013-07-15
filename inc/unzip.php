<?php
function unzip($file, $path) {
        $zip = zip_open($file);
        if ($zip) {
          while ($zip_entry = zip_read($zip)) {
           $fp = fopen($path."/".zip_entry_name($zip_entry), "w");
            if (zip_entry_open($zip, $zip_entry, "r")) {
              $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
              fwrite($fp,"$buf");
              zip_entry_close($zip_entry);
              fclose($fp);
            }
          }
          zip_close($zip);
        }
}

unzip( $argv[1] , $argv[2]  );

?>


