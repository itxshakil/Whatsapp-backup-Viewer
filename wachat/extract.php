<?php

function extractZIP($fileToExtract, $targetDirectory)
{

    $zip = new ZipArchive();
    if ($zip->open($fileToExtract)) {
        $zip->extractTo($targetDirectory);
        $zip->close();
        // Delete ZIP File
        unlink($fileToExtract);

        return true;
    }
    return false;
}
