<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $errors = array();

    if (isset($_FILES['fupload'])) {
        $uploadFilename = explode('.', $_FILES['fupload']['name']);

        $extractDirectory = 'extracted/' . $uploadFilename[0] . time() . '/';

        $accepted_types = array('application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/s-compressed');

        if (array_search($_FILES['fupload']['type'], $accepted_types)) {
            if (mkdir($extractDirectory, true)) {
                $chatPath =  $extractDirectory . $uploadFilename[0];

                if (move_uploaded_file($_FILES['fupload']['tmp_name'], $chatPath)) {
                    include_once 'extract.php';
                    $extracted = extractZIP($chatPath, $extractDirectory);
                } else {
                    $errors['err-upload'] = 'There was a Problem . Please Try Again .';
                }
            } else {
                $errors['server'] = 'Server Error. Its our fault.';
            }
        } else {
            $errors['file_type_mismatch'] = 'Please choose a ZIP file. we said ZIP.';
        }
    } else {
        $errors['file_not_found'] = 'Please choose a file';
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
    <link rel="stylesheet" href="css/main.css">
</head>

<body>
    <header class="brand">
        <h1>Whatsapp Backup Chat Viewer</h1>
    </header>
    <main class="container h-100 border rounded">
        <?php
        if (isset($extracted) && $extracted) : ?>
            <?php
            $chat_file = $chatPath . '.txt';
            $filearray = file($chat_file);
            $pattern = '/Whatsapp Chat with\s(?<name>[a-zA-Z0-9].+)/i';
            $string = $uploadFilename[0];
            if (preg_match($pattern, $string, $matches)) {
                $name = $matches['name'];
            } else {
                echo 'No name Found';
            }
            echo "<section><h3 class='chat-title'>$name</h3>";
            foreach ($filearray as $line) {
                $string = $line;
                $pattern = '/(?<time>[0-9]+\/[0-9]+\/[0-9]+,\s[0-9:APM ]+)\s+\-\s+(?<sender>[A-Za-z0-9 ]+):(?<message>.*)/i';
                if (preg_match($pattern, $string, $matches)) {
                    $time = $matches['time'];
                    $sender = $matches['sender'];
                    $message = $matches['message'];
                    if ($sender != $name) {
                        echo "<div class='message left'>
                                <div class='flex-justify-between'>
                                    <p class='sender'>$sender</p>
                                    <span class='time'>$time</span>
                                </div>
                                <p class='chat'>$message</p>
                            </div>";
                    } else {
                        echo "<div class='message right'>
                                <div class='flex-justify-between'>
                                    <p class='sender'>$sender</p>
                                    <span class='time'>$time</span>
                                </div>
                                <p class='chat'>$message</p>
                            </div>";
                    }
                } else {
                    $pattern = '/(?<time>[0-9]+\/[0-9]+\/[0-9]+,\s[0-9: ]+[PA]M)\s+\-\s(?<message>.*)/i';
                    if (preg_match($pattern, $string, $matches)) {
                        $time = $matches['time'];
                        $message = $matches['message'];

                        echo "<div class='message center'>
                                <div class='flex-justify-between'>
                                    <span class='text-white time'>$time</span>
                                </div>
                                <p class='chat'>$message</p>
                            </div>";
                    } else {
                        echo "<div class='message center'>
                                <p class='chat'>$string</p>
                            </div>";
                    }
                }
            }
            echo '</section>';

            ?>
        <?php else : ?>
            <form action="" method="post" enctype="multipart/form-data" class="bg-dark text-white border rounded p-3">
                <h2 class="lead center mb-2">Upload Exported Chat </h2>
                <?php if(isset($errors) && $errors):?>
                <?php foreach ($errors as $key => $value) : ?>
                    <p class="alert"><?= $value; ?></p>
                <?php endforeach; ?>
                <?php endif; ?>
                <div class="flex-col mt-2">
                    <label for="fupload">Upload Backup File(ZIP)</label>
                    <input type="file" name="fupload" id="fupload" class="form-control">
                    <small class="text-muted">Only ZIP file allowed.</small>
                </div>
                <input type="submit" value="Upload" class="form-control btn btn-primary">
            </form>
        <?php endif; ?>
    </main>

    <footer>
        &copy; 2020 All Right Reserved.
        <br>
        Designed with <span style="color:red;">♥</span> by <a href="mailto:itxshakil@gmail.com" class="text-primary">Shakil Alam</a>
    </footer>
</body>

</html>