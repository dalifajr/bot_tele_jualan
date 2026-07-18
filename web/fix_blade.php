<?php

$dir = new RecursiveDirectoryIterator('d:/bot_tele_jualan/web/resources/views');
$ite = new RecursiveIteratorIterator($dir);
$fixedFiles = 0;

foreach($ite as $file) {
    if($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $original = $content;
        
        // Find {{ __('...') }} or {!! __('...') !!}
        $content = preg_replace_callback('/\{\{!?\s*__\([\'"](.*?)[\'"]\)\s*\}?\}\}/', function($m) {
            $inner = $m[1];
            // If the inner string looks like PHP code that was accidentally matched
            // because of -> or it contains PHP operators
            if (preg_match('/\$|===|!==|->|\b as \b|\)\)/', $inner) || preg_match('/^[a-zA-Z0-9_]+\(/', $inner)) {
                return $inner; // Return just the inner string, stripping {{ __('...') }}
            }
            // Some strings got incorrectly split, like "Anda dapat membuat... <strong>{{ __('Produk Saya') }}</strong>{{ __(', atau ditambahkan sebagai') }} <strong>"
            // Wait, if it's just text like ", atau ditambahkan sebagai", it's fine.
            return $m[0];
        }, $content);
        
        // Let's also handle {!! __('...') !!} properly if the above didn't catch the !
        $content = preg_replace_callback('/\{!!\s*__\([\'"](.*?)[\'"]\)\s*!!\}/', function($m) {
            $inner = $m[1];
            if (preg_match('/\$|===|!==|->|\b as \b|\)\)/', $inner) || preg_match('/^[a-zA-Z0-9_]+\(/', $inner)) {
                return $inner;
            }
            return $m[0];
        }, $content);

        // Fix the specific parse errors mentioned:
        // `orders/show.blade.php:96`: `items as $item)` -> `items as $item)`
        
        if ($content !== $original) {
            file_put_contents($file->getPathname(), $content);
            $fixedFiles++;
        }
    }
}

echo "Fixed PHP syntax inside Blade in $fixedFiles files.\n";
