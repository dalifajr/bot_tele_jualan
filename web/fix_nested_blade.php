<?php

$dir = new RecursiveDirectoryIterator('d:/bot_tele_jualan/web/resources/views');
$ite = new RecursiveIteratorIterator($dir);

foreach($ite as $file) {
    if($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $original = $content;
        
        // Find nested bad translations in {!! __('...') !!}
        $content = preg_replace_callback('/\{!!\s*__\([\'"](.*?)[\'"]\)\s*!!\}/s', function($m) {
            $inner = $m[1];
            if (strpos($inner, '{{') !== false || strpos($inner, '}}') !== false) {
                // If the inner string contains Blade tags, we unwrap the outer one completely
                // Wait, if it has `{{ __('...') }}`, then the outer one is `{!! __('Anda dapat... <strong>{{ __('Produk Saya') }}</strong> ...') !!}`
                return "{!! '" . addslashes($inner) . "' !!}";
            }
            return $m[0];
        }, $content);

        if ($content !== $original) {
            file_put_contents($file->getPathname(), $content);
            echo "Fixed nested translations in: " . $file->getPathname() . "\n";
        }
    }
}
