<?php

$publicPath = $_SERVER['DOCUMENT_ROOT']; // .../httpdocs/public
$basePath   = dirname($publicPath);      // .../httpdocs

$targetFolder = $basePath . '/storage/app/public';
$linkFolder   = $publicPath . '/storage';

if (!file_exists($targetFolder)) {
    die("Le dossier source n'existe pas : $targetFolder");
}

if (file_exists($linkFolder)) {
    die("Le lien existe déjà : $linkFolder");
}

if (symlink($targetFolder, $linkFolder)) {
    echo 'Symlink créé avec succès';
} else {
    echo 'Erreur symlink : ' . error_get_last()['message'];
}
