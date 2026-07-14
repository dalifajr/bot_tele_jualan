<?php
$files = glob(__DIR__ . '/app/Notifications/*.php');
foreach($files as $file) {
    $content = file_get_contents($file);
    $content = str_replace("return ['mail'];", "return ['database'];", $content);
    file_put_contents($file, $content);
}
echo "Updated " . count($files) . " files.\n";
