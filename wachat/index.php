<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $errors = array();

    if (isset($_FILES['fupload'])) {
        $uploadFilename = explode('.', $_FILES['fupload']['name']);

        $extractDirectory = 'extracted/' . $uploadFilename[0] . time() . '/';

        $accepted_types = array('application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/s-compressed');

        if (array_search($_FILES['fupload']['type'], $accepted_types)) {
        } else {
            $errors['file_type_mismatch'] = 'Please choose a ZIP file. we said ZIP.';
        }

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
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/main.css">
</head>

<body>
    <header class="bg-dark text-white text-center">Whatsapp Backup Chat Viewer</header>
    <main class="container h-100 border rounded">
        <?php
        if (isset($extracted) && $extracted) : ?>
            <?php
            $chat_file = $chatPath . '.txt';
            $fr = file($chat_file);
            $pattern = '/Whatsapp Chat with\s(?<name>[a-zA-Z0-9].+)/i';
            $string = $uploadFilename[0];
            if (preg_match($pattern, $string, $matches)) {
                $name = $matches['name'];
            } else {
                echo 'No name Found';
            }
            echo "<div class='p-3 bg-dark text-light text-center font-bold h4'>$name</div>";
            foreach ($fr as $line) {
                $string = $line;
                $pattern = '/(?<time>[0-9]+\/[0-9]+\/[0-9]+,\s[0-9:APM ]+)\s+\-\s+(?<sender>[A-Za-z0-9 ]+):(?<message>.*)/i';
                if (preg_match($pattern, $string, $matches)) {
                    $time = $matches['time'];
                    $sender = $matches['sender'];
                    $message = $matches['message'];
                    if ($sender != $name) {
                        echo "<div class='block border mr-auto w-75 m-2 p-2 bg-white text-dark rounded'>
        <div class='flex d-flex justify-content-between'>
            <p class=''>$sender</p>
            <span class='text-dark'>$time</span>
        </div>
        <p class='chat'>$message</p>
    </div>";
                    } else {
                        echo "<div class='block ml-auto w-75 m-2 p-2 bg-success text-dark rounded'>
        <div class='flex d-flex justify-content-between'>
            <p class=''>$sender</p>
            <span class='text-white'>$time</span>
        </div>
        <p class='chat'>$message</p>
    </div>";
                    }
                } else {
                    $pattern = '/(?<time>[0-9]+\/[0-9]+\/[0-9]+,\s[0-9: ]+[PA]M)\s+\-\s(?<message>.*)/i';
                    if (preg_match($pattern, $string, $matches)) {
                        $time = $matches['time'];
                        $message = $matches['message'];

                        echo "<div class='block mr-auto w-75 m-2 p-2 bg-success text-dark rounded'>
        <div class='flex d-flex justify-content-between'>
            <span class='text-white'>$time</span>
        </div>
        <p class='chat'>$message</p>
    </div>";
                    } else {
                        echo "<div class='block mr-auto w-75 m-2 p-2 bg-success text-dark rounded'>
        <p class='chat'>$string</p>
    </div>";
                    }
                }
            }
            echo '</div>';

            ?>
        <?php else : ?>
            <form action="" method="post" enctype="multipart/form-data" class="bg-dark text-white border rounded p-3">
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
        <?php endif; ?>
    </main>

    <footer>
        &copy; 2018 All Right Reserved.
        <br>
        Designed with <span style="color:red;">♥</span> by <a href="mailto:itxshakil@gmail.com" class="text-primary">Shakil Alam</a>
    </footer>
</body>

</html>