<?php

$dir = new RecursiveDirectoryIterator('d:/bot_tele_jualan/web/resources/views');
$ite = new RecursiveIteratorIterator($dir);
$fixedFiles = 0;

foreach($ite as $file) {
    if($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $original = $content;
        
        $fixes = [
            "status === \\'delivered\\'" => "status === 'delivered'",
            "status === \\'paid\\'" => "status === 'paid'",
            "status === \\'pending_payment\\'" => "status === 'pending_payment'",
            "status === \\'pending\\'" => "status === 'pending'",
            "status === \\'approved\\'" => "status === 'approved'",
            "status === \\'rejected\\'" => "status === 'rejected'",
            "status === \\'held\\'" => "status === 'held'",
            "status === \\'released\\'" => "status === 'released'",
            "status, [\\'cancelled\\', \\'expired\\']" => "status, ['cancelled', 'expired']",
            "items as \$item)" => "items as \$item", // Fix lingering parenthesis if any
            "vpnAccounts as \$vpn)" => "vpnAccounts as \$vpn",
            "workers as \$worker)" => "workers as \$worker"
        ];

        foreach ($fixes as $bad => $good) {
            $content = str_replace($bad, $good, $content);
        }
        
        // Fix any remaining lingering `)` from `->xxx)` where it was unwrapped
        // Example: $order->closed_at)
        $content = preg_replace('/(\$[a-zA-Z0-9_->]+(?:_at|is_sold|isEmpty\(\)|username|customer|is_warranty_active|complaintCase|rejected_reason|refund_note|comment|proof_image_path|isPast\(\)))\)/', '$1', $content);

        // Also fix Auth::id())
        $content = str_replace("Auth::id()))", "Auth::id())", $content);
        $content = preg_replace('/(Auth::id\(\))\)/', '$1', $content);
        
        if ($content !== $original) {
            file_put_contents($file->getPathname(), $content);
            $fixedFiles++;
        }
    }
}

echo "Fixed Blade syntax in $fixedFiles files.\n";
