<?php

$newKeys = json_decode(file_get_contents('d:/bot_tele_jualan/web/new_blade_keys.json'), true);
$enJsonPath = 'd:/bot_tele_jualan/web/lang/en.json';
$existing = file_exists($enJsonPath) ? json_decode(file_get_contents($enJsonPath), true) : [];

echo "Found " . count($newKeys) . " new keys.\n";

$translatedCount = 0;

$toTranslate = [];
foreach ($newKeys as $key) {
    if (!isset($existing[$key])) {
        $toTranslate[] = $key;
    }
}
echo "Need to translate: " . count($toTranslate) . "\n";

$options = [
    "http" => [
        "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n"
    ]
];
$context = stream_context_create($options);

foreach ($toTranslate as $i => $text) {
    $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=id&tl=en&dt=t&q=" . urlencode($text);
    
    try {
        $response = @file_get_contents($url, false, $context);
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data[0][0][0])) {
                $english = $data[0][0][0];
                $existing[$text] = $english;
                $translatedCount++;
            } else {
                $existing[$text] = $text;
            }
        } else {
            $existing[$text] = $text;
        }
    } catch (\Exception $e) {
        $existing[$text] = $text;
    }
    
    if (($i + 1) % 50 === 0) {
        echo "Translated " . ($i + 1) . " / " . count($toTranslate) . "\n";
        file_put_contents($enJsonPath, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

file_put_contents($enJsonPath, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Successfully translated $translatedCount strings and updated en.json!\n";
