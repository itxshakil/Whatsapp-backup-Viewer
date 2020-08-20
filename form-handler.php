<?php
include 'extract.php';
// echo 'Submitted';

 if(isset($_FILES['fupload'])) {
     $filename = $_FILES['fupload']['name'];
     $source = $_FILES['fupload']['tmp_name'];
     $type = $_FILES['fupload']['type']; 
      
     $name = explode('.', $filename); 
     $chat_file_name=$name[0];
     $target = 'extracted/' . $name[0] .'/';  
      
     // Ensures that the correct file was chosen
     $accepted_types = array('application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/s-compressed');
  
     foreach($accepted_types as $mime_type) {
         if($mime_type == $type) {
             $okay = true;
             break;
         } 
     }
        
   //Safari and Chrome don't register zip mime types. Something better could be used here.
     $okay = strtolower($name[1]) == 'zip' ? true: false;
  
     if(!$okay) {
           die("Please choose a zip file, dummy!");       
     }
    
     mkdir($target);
     $saved_file_location = $target . $filename;
      
     if(move_uploaded_file($source, $saved_file_location)) {
         openZip($saved_file_location);
     } else {
         die("There was a problem. Sorry!");
     }


     /**
      * Extract messges from txt file
      */

      
      /**
       * end Extracting
       */
      
//      echo $target;
//      echo $target . $name[0];
//  // This last part is for example only. It can be deleted. 
//        $scan = scandir($target);

      
    //  print '<ul>';
    //  for ($i = 0; $i<count($scan); $i++) {
    //      if(strlen($scan[$i]) >= 3) {
    //          $check_for_html_doc = strpos($scan[$i], 'html');
    //          $check_for_php = strpos($scan[$i], 'php');
              
    //          if($check_for_html_doc === false && $check_for_php === false) {
    //              echo '<li>' . $scan[$i] . '</li>';
    //          } else {
    //              echo '<li><a href="' . $target . $name[0] . '/' . $scan[$i] . '">' . $scan[$i] . '</a></li>';
    //          }
    //      }
    //  }
    //  print '</ul>';
 }