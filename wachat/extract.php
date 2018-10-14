<?php
 
function openZip($file_to_open) {
    global $target;
    global $extracted;
     
    $zip = new ZipArchive();
    $x = $zip->open($file_to_open);
    if($x === true) {
        $zip->extractTo($target);
        $zip->close();
         // Delete ZIP File
        unlink($file_to_open);

        $extracted = true;
    } else {
        $errors['err-extract']='Error During Extraction';
        $extracted =false;
    }
}
?>