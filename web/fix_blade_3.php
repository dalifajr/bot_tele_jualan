<?php

$dir = new RecursiveDirectoryIterator('d:/bot_tele_jualan/web/resources/views');
$ite = new RecursiveIteratorIterator($dir);
$fixedFiles = 0;

foreach($ite as $file) {
    if($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $original = $content;

        $fixes = [
            "{{ __('any())') }}" => "any())",
            "{{ __('all() as \$err)') }}" => "all() as \$err)",
            "{{ __('telegram_id)') }}" => "telegram_id)",
            "{{ __('status == \\'new\\')') }}" => "status == 'new')",
            "{{ __('status == \\'review\\')') }}" => "status == 'review')",
            "{{ __('status == \\'done\\')') }}" => "status == 'done')",
            "{{ __('attachment_path)') }}" => "attachment_path)",
            "{{ __('order)') }}" => "order)",
            "{{ __('stockUnits as \$unit)') }}" => "stockUnits as \$unit)",
            "{{ __('ip_address))') }}" => "ip_address))",
        ];

        foreach ($fixes as $bad => $good) {
            $content = str_replace($bad, $good, $content);
        }

        if ($content !== $original) {
            file_put_contents($file->getPathname(), $content);
            $fixedFiles++;
        }
    }
}

echo "Fixed Blade syntax safely in $fixedFiles files.\n";
