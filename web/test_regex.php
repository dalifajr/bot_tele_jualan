<?php

$content = file_get_contents('d:/bot_tele_jualan/web/resources/views/admin/products/index.blade.php');

// Match text between > and <
// Must contain at least one letter
// We exclude lines that are already inside {{ ... }} or {!! !!} or @...
preg_match_all('/(?<=>)\s*([A-Za-z0-9][A-Za-z0-9\s.,!?\'"()\-:&\/]*[A-Za-z0-9.!?])\s*(?=<)/', $content, $matches);

foreach ($matches[1] as $match) {
    echo "Found: " . trim($match) . "\n";
}

// Check placeholders
preg_match_all('/(placeholder|title)="([^"]*[A-Za-z][^"]*)"/', $content, $matches);
foreach ($matches[2] as $match) {
    echo "Attribute: " . trim($match) . "\n";
}
