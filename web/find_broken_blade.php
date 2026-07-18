<?php
$dir = new RecursiveDirectoryIterator('d:/bot_tele_jualan/web/resources/views');
$ite = new RecursiveIteratorIterator($dir);
foreach($ite as $file) {
    if($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $lines = explode("\n", $content);
        foreach ($lines as $num => $line) {
            // Find {{ __('...') }} where the inner string contains $ or === or -> or !== or )
            if (preg_match('/\{\{\s*__\([\'"](.*?(?:\$|===|!==|->|\b as \b|\)).*?)[\'"]\)\s*\}\}/', $line, $m)) {
                echo $file->getBasename() . ':' . ($num+1) . ' -> ' . trim($line) . "\n";
            }
        }
    }
}
