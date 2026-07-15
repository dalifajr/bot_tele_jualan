<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasSystemTimezone;

class AuditLog extends Model
{
    use HasSystemTimezone;
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'action',
        'entity_type',
        'entity_id',
        'detail',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function maskSensitiveData($text)
    {
        if (empty($text)) {
            return $text;
        }

        // Patterns to match sensitive keys like password, token, raw_text, secret, key
        $patterns = [
            '/(password=)([^;\s]+)/i',
            '/(token=)([^;\s]+)/i',
            '/(raw_text=)([^;\s]+)/i',
            '/(secret=)([^;\s]+)/i',
            '/(key=)([^;\s]+)/i',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace_callback($pattern, function ($matches) {
                $prefix = $matches[1];
                $val = $matches[2];
                $len = strlen($val);
                if ($len <= 4) {
                    return $prefix . str_repeat('*', $len);
                }
                return $prefix . substr($val, 0, 2) . str_repeat('*', $len - 4) . substr($val, -2);
            }, $text);
        }

        return $text;
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
            if (!empty($model->detail)) {
                $model->detail = static::maskSensitiveData($model->detail);
            }
        });
    }
}
