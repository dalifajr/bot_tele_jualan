<?php

$dir = new RecursiveDirectoryIterator('d:/bot_tele_jualan/web/resources/views');
$ite = new RecursiveIteratorIterator($dir);
$countFiles = 0;
$totalReplaced = 0;
$extractedStrings = [];

foreach($ite as $file) {
    if($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $originalContent = $content;

        // 1. Replace text nodes between HTML tags
        // We match > followed by optional whitespace, then our text, then optional whitespace, then <
        // The text must contain at least one letter, and should not contain {{ or }} or @
        $content = preg_replace_callback('/(>[\s\n]*)([^<>{}\n\r@]+?[A-Za-z]+[^<>{}\n\r@]*?)([\s\n]*<)/', function($m) use (&$totalReplaced, &$extractedStrings) {
            $text = $m[2];
            // Only translate if it contains mostly letters (ignore pure punctuation or single letters if not words)
            if (preg_match('/[a-zA-Z]{3,}/', $text)) {
                $trimmed = trim($text);
                $extractedStrings[$trimmed] = true;
                $totalReplaced++;
                return $m[1] . "{{ __('" . addslashes($trimmed) . "') }}" . $m[3];
            }
            return $m[0];
        }, $content);

        // 2. Replace attributes: placeholder and title
        $content = preg_replace_callback('/(placeholder|title)="([^"{]*[A-Za-z]{3,}[^"{]*)"/', function($m) use (&$totalReplaced, &$extractedStrings) {
            $text = $m[2];
            $extractedStrings[$text] = true;
            $totalReplaced++;
            return $m[1] . '="{{ __(\'' . addslashes($text) . '\') }}"';
        }, $content);

        if ($content !== $originalContent) {
            file_put_contents($file->getPathname(), $content);
            $countFiles++;
        }
    }
}

echo "Modified $countFiles files.\n";
echo "Replaced $totalReplaced strings.\n";
file_put_contents('d:/bot_tele_jualan/web/new_blade_keys.json', json_encode(array_keys($extractedStrings), JSON_PRETTY_PRINT));
echo "Saved new keys to new_blade_keys.json\n";
