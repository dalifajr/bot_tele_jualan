<?php

$dir = new RecursiveDirectoryIterator('d:/bot_tele_jualan/web/resources/views');
$ite = new RecursiveIteratorIterator($dir);
$fixedFiles = 0;

foreach($ite as $file) {
    if($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $original = $content;

        // 1. Exact string replacements for known broken patterns
        $fixes = [
            "{{ __('items as \$item)') }}" => "items as \$item",
            "{{ __('closed_at)') }}" => "closed_at",
            "{{ __('isEmpty())') }}" => "isEmpty()",
            "{{ __('status === \\'pending\\')') }}" => "status === 'pending')",
            "{{ __('status === \\'approved\\')') }}" => "status === 'approved')",
            "{{ __('status === \\'rejected\\')') }}" => "status === 'rejected')",
            "{{ __('status === \\'held\\')') }}" => "status === 'held')",
            "{{ __('status === \\'released\\')') }}" => "status === 'released')",
            "{{ __('status === \\'delivered\\')') }}" => "status === 'delivered')",
            "{{ __('status === \\'paid\\')') }}" => "status === 'paid')",
            "{{ __('status === \\'pending_payment\\')') }}" => "status === 'pending_payment')",
            "{{ __('status, [\\'cancelled\\', \\'expired\\']))') }}" => "status, ['cancelled', 'expired']))",
            "{{ __('proof_image_path)') }}" => "proof_image_path",
            "{{ __('\$label)') }}" => "\$label",
            "{{ __('paid_at)') }}" => "paid_at",
            "{{ __('delivered_at)') }}" => "delivered_at",
            "{{ __('cancelled_at)') }}" => "cancelled_at",
            "{{ __('cancel_reason)') }}" => "cancel_reason",
            "{{ __('workers as \$worker)') }}" => "workers as \$worker",
            "{{ __('github_joined_at)') }}" => "github_joined_at",
            "{{ __('username)') }}" => "username",
            "{{ __('customer)') }}" => "customer",
            "{{ __('is_sold)') }}" => "is_sold",
            "{{ __('isPast())') }}" => "isPast()",
            "{{ __('vpnAccounts as \$vpn)') }}" => "vpnAccounts as \$vpn",
            "{{ __('complaintCase)') }}" => "complaintCase",
            "{{ __('rejected_reason)') }}" => "rejected_reason",
            "{{ __('refund_note)') }}" => "refund_note",
            "{{ __('is_warranty_active)') }}" => "is_warranty_active",
            "{{ __('warranty_expires_at)') }}" => "warranty_expires_at",
            "{{ __('id !== Auth::id())') }}" => "id !== Auth::id())",
            "{{ __('comment)') }}" => "comment"
        ];

        foreach ($fixes as $bad => $good) {
            $content = str_replace($bad, $good, $content);
        }

        // 2. Fix the nested translations in {!! !!}
        $content = str_replace([
            "{!! __('. Akun yang baru diunggah akan masuk ke status') !!}",
            "{!! __(', atau ditambahkan sebagai') !!}",
            "{!! __('setelah jam cooldown karantina habis. Anda dapat mengatur jam karantina tersendiri di menu') !!}"
        ], [
            ". Akun yang baru diunggah akan masuk ke status",
            ", atau ditambahkan sebagai",
            "setelah jam cooldown karantina habis. Anda dapat mengatur jam karantina tersendiri di menu"
        ], $content);

        // Also fix any {!! __('Anda dapat... {{ __('Produk Saya') }}... ') !!} automatically
        $content = preg_replace_callback('/\{!!\s*__\([\'"](.*?)[\'"]\)\s*!!\}/s', function($m) {
            $inner = $m[1];
            if (strpos($inner, '{{') !== false || strpos($inner, '}}') !== false) {
                return "{!! '" . addslashes($inner) . "' !!}";
            }
            return $m[0];
        }, $content);

        if ($content !== $original) {
            file_put_contents($file->getPathname(), $content);
            $fixedFiles++;
        }
    }
}

echo "Fixed Blade syntax safely in $fixedFiles files.\n";
