<?php
$viewsDir = "d:/bot_tele_jualan/web/resources/views";
$langEnFile = "d:/bot_tele_jualan/web/lang/en.json";
$langIdFile = "d:/bot_tele_jualan/web/lang/id.json";

$dir = new RecursiveDirectoryIterator($viewsDir);
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, "/^.+\.php$/i", RecursiveRegexIterator::GET_MATCH);

$strings = [];
foreach($files as $file) {
    $content = file_get_contents($file[0]);
    preg_match_all("/__\([\047\"](.*?)[\047\"](?:.*?)\)/", $content, $matches);
    if (!empty($matches[1])) {
        foreach($matches[1] as $match) {
            $strings[$match] = "";
        }
    }
}

// Update EN
$enData = json_decode(file_get_contents($langEnFile), true) ?? [];
$idData = json_decode(file_get_contents($langIdFile), true) ?? [];

foreach ($strings as $key => $val) {
    if (!isset($enData[$key])) {
        $enData[$key] = $key; // Placeholder, or translate later
    }
    if (!isset($idData[$key])) {
        $idData[$key] = $key; // ID usually defaults to the key since the key is in Indonesian
    }
}

file_put_contents($langEnFile, json_encode($enData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($langIdFile, json_encode($idData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Updated translation files with ".count($strings)." keys found.";

