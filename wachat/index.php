<?php
    if($_SERVER['REQUEST_METHOD'] == 'POST'){
        include_once 'extract.php';
        $errors =array();
        if(isset($_FILES['fupload'])){
            $filename = $_FILES['fupload']['name'];
            $source = $_FILES['fupload']['tmp_name'];
            $type = $_FILES['fupload']['type'];
            // Getting File name
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
                $errors['not-zip'] ='Please Choose a ZIP File. We said ZIP. ';    
            }   
            if(!mkdir($target)){
            $extracted =true;
            }
            $saved_file_location = $target . $filename; 
            if(move_uploaded_file($source, $saved_file_location)) {
                openZip($saved_file_location);

            } else {
                $errors['err-upload']='There was a Problem . Please Try Again .';
            }

        }
        else{
            $errors['no-fupload'] ='Please Select File';
        }

    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Whatsapp Backup Chat Viewer</title>
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/main.css">
</head>
<body>
    <header class="bg-primary p-2 h1 text-center font-weight-light text-dark ">Whatsapp Backup Chat Viewer</header>
    <div class="container">
        <main>
                <?php
                    if(@$extracted !== true):
                ?>
                <form action="" method="post" enctype="multipart/form-data" class="bg-light p-3" >
                <div class="lead center">Upload Exported Chat </div>
                        <div class="form-group">
                                <label for="fupload">Upload Backup File(ZIP)</label>
                                <input type="file" name="fupload" id="fupload" class="form-control">
                                <small class="text-muted">Only ZIP file allowed.</small>
                        </div>
                        <div class="form-group">
                            <input type="submit" value="Upload" class="form-control btn btn-primary">
                        </div>
                </form>
                <?php
                    endif
                ?>
                <?php if(@$extracted === true): ?>
                 <?php
                    $chat_file='extracted/'.$chat_file_name.'/'.$chat_file_name.'.txt';
                    $fr=file($chat_file);
                    $pattern='/Whatsapp Chat with\s(?<name>[a-zA-Z0-9].+)/i';
                    $string=$chat_file_name;
                    if(preg_match($pattern,$string ,$matches)){
                        $name=$matches['name'];
                    }
                    else{
                           echo 'No name Found';
                    }
                    echo'<div class="p-3 bg-dark text-light text-center">'.$name.'</div><div class="bg-light">';
                    foreach($fr as $line){
                        $string=$line;
                        $pattern='/(?<time>[0-9]+\/[0-9]+\/[0-9]+,\s[0-9:APM ]+)\s+\-\s+(?<sender>[A-Za-z0-9 ]+):(?<message>.*)/i'; 
                        if(preg_match($pattern,$string,$matches)){
                            $time=$matches['time'];
                            $sender=$matches['sender'];
                            $message=$matches['message'];
                            if($sender != $name){
                                echo'<div class="clearfix"><div class="card float-right p-0 bg-success text-dark">
                                        <div class=" card-body p-1">
                                            <p class="card-text">'.$sender.'</p>
                                            <p class="chat">'.$message.'</p>
                                            <span class="text-muted float-right">'.$time.'</span>
                                        </div>
                                    </div></div>';                                
                            }
                            else{
                                echo'<div class="clearfix"><div class="card border-dark float-left p-0">
                                        <div class="card-body p-1">
                                            <p class="card-text">'.$sender.'</p>
                                            <p class="chat">'.$message.'</p>
                                            <span class="text-muted float-right">'.$time.'</span>
                                        </div>
                                    </div></div>';
                            }
                        }
                        else{
                            $pattern='/(?<time>[0-9]+\/[0-9]+\/[0-9]+,\s[0-9: ]+[PA]M)\s+\-\s(?<message>.*)/i';
                            if(preg_match($pattern,$string,$matches)){
                                $time=$matches['time'];
                                $message=$matches['message'];
                                
                                echo'<div class="card>
                                <div class="card-body">
                                    <p class="card-text">'.$message.'</p>
                                    <span class="text-muted">'.$time.'</span>
                                </div>
                               </div>';
                            }
                            else{
                                echo'<div class="card">
                                        <div class="card-body">
                                            <div class="card-text">'.$string.'
                                            </div>
                                        </div>
                                    </div>';
                            }

                        }
                    }
                    echo '</div>';
                 
                 ?>       
                <?php
                    endif
                ?>
        </main>
    </div>
    
    <footer> 
        &copy; 2018 All Right Reserved.
        <br>
        Designed with <span style="color:red;">♥</span> by <a href="mailto:itxshakiil@gmail.com" class="text-primary">Shakil Alam</a> 
    </footer>
</body>
</html>