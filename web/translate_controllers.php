<?php

$dir = new RecursiveDirectoryIterator('d:/bot_tele_jualan/web/app/Http/Controllers');
$ite = new RecursiveIteratorIterator($dir);
$count = 0;
foreach($ite as $file) {
    if($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        
        // Regex explanation:
        // with('success', "String") -> with('success', __("String"))
        // with('error', 'String') -> with('error', __('String'))
        // We match with('type', 'message')
        // We ensure message doesn't already start with __
        $content = preg_replace_callback('/with\(([\'"])(success|error|info|warning)\1\s*,\s*(?!__\()([\'"])(.*?)\3\)/', function($m) {
            return 'with(' . $m[1] . $m[2] . $m[1] . ', __(' . $m[3] . $m[4] . $m[3] . '))';
        }, $content, -1, $countReplaced);
        
        if ($countReplaced > 0) {
            file_put_contents($file->getPathname(), $content);
            $count += $countReplaced;
        }
    }
}
echo "Replaced $count occurrences in controllers.\n";
