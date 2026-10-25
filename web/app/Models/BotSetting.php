<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotSetting extends Model
{
    use HasFactory;

    protected $table = 'bot_settings';
    public $timestamps = false;
    
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
        'updated_at'
    ];

    /**
     * Pastikan updated_at selalu terisi timestamp saat menyimpan setting
     * untuk mencegah error MySQL strict mode (SQLSTATE 1364).
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if (empty($model->updated_at)) {
                $model->updated_at = now();
            }
        });
    }

    /**
     * Helper untuk mengambil nilai setting dengan default fallback.
     */
    public static function getSetting(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Helper untuk menyimpan nilai setting secara aman.
     */
    public static function setSetting(string $key, $value)
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now()]
        );
    }
}
