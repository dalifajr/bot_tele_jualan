<?php

$dirs = [
    'd:/bot_tele_jualan/web/app/Http/Controllers',
    'd:/bot_tele_jualan/web/resources/views'
];

$keys = [];

foreach ($dirs as $dir) {
    $ite = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach($ite as $file) {
        if($file->isFile() && in_array($file->getExtension(), ['php'])) {
            $content = file_get_contents($file->getPathname());
            // Match __('string') or __("string")
            preg_match_all('/__\(\s*([\'"])(.*?)\1\s*\)/s', $content, $matches);
            foreach ($matches[2] as $match) {
                $keys[$match] = true;
            }
        }
    }
}

$enJsonPath = 'd:/bot_tele_jualan/web/lang/en.json';
$existing = file_exists($enJsonPath) ? json_decode(file_get_contents($enJsonPath), true) : [];

$missing = [];
foreach (array_keys($keys) as $key) {
    if (!isset($existing[$key])) {
        $missing[] = $key;
    }
}

echo "Total keys found in code: " . count($keys) . "\n";
echo "Total missing from en.json: " . count($missing) . "\n";
file_put_contents('d:/bot_tele_jualan/web/missing_keys.json', json_encode($missing, JSON_PRETTY_PRINT));
echo "Missing keys saved to missing_keys.json\n";
