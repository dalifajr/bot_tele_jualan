<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DashboardPromo extends Model
{
    use HasFactory;

    protected $table = 'dashboard_promos';

    protected $fillable = [
        'title',
        'badge_text',
        'badge_color',
        'description',
        'icon',
        'image',
        'button_text',
        'button_url',
        'gradient_theme',
        'order_index',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order_index' => 'integer',
    ];

    /**
     * Scope for active promos ordered by priority
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_index', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Get accessible image URL
     */
    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }

        return asset('storage/' . $this->image);
    }

    /**
     * Helper to get CSS gradient style based on gradient_theme
     */
    public function getGradientStyleAttribute(): string
    {
        return match ($this->gradient_theme) {
            'green'  => 'linear-gradient(135deg, rgba(56, 106, 33, 0.08), var(--surface-container))',
            'cyan'   => 'linear-gradient(135deg, rgba(34, 158, 217, 0.1), var(--surface-container))',
            'purple' => 'linear-gradient(135deg, rgba(142, 68, 173, 0.08), var(--surface-container))',
            'amber'  => 'linear-gradient(135deg, rgba(230, 81, 0, 0.08), var(--surface-container))',
            'red'    => 'linear-gradient(135deg, rgba(179, 38, 30, 0.08), var(--surface-container))',
            default  => 'linear-gradient(135deg, rgba(11, 87, 208, 0.08), var(--surface-container))',
        };
    }

    /**
     * Helper to get theme solid / tint rgb for wide gradient transitions with images
     */
    public function getThemeRgbAttribute(): string
    {
        return match ($this->gradient_theme) {
            'green'  => '56, 106, 33',
            'cyan'   => '34, 158, 217',
            'purple' => '142, 68, 173',
            'amber'  => '230, 81, 0',
            'red'    => '179, 38, 30',
            default  => '11, 87, 208',
        };
    }
}
