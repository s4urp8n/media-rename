<?php

use s4urp8n\MediaRename;

require "vendor/autoload.php";

$dirs = [

];

$rename = new MediaRename();
foreach ($dirs as $dir) {
    $rename->addPath($dir);
}
$rename->rename();
