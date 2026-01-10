<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBranding extends Model
{
    use HasUuid;

    protected $table = 'tenant_branding';

    protected $fillable = [
        'tenant_id',
        'primary_color',
        'secondary_color',
        'accent_color',
        'background_color',
        'text_color',
        'logo_url',
        'logo_dark_url',
        'favicon_url',
        'login_background_url',
        'font_family',
        'heading_font_family',
        'border_radius',
        'button_style',
        'custom_css',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get CSS variables for frontend.
     */
    public function toCssVariables(): array
    {
        return [
            '--tenant-primary' => $this->primary_color,
            '--tenant-secondary' => $this->secondary_color,
            '--tenant-accent' => $this->accent_color,
            '--tenant-background' => $this->background_color,
            '--tenant-text' => $this->text_color,
            '--tenant-font' => $this->font_family,
            '--tenant-heading-font' => $this->heading_font_family ?? $this->font_family,
            '--tenant-radius' => $this->border_radius,
        ];
    }

    /**
     * Get formatted for API response.
     */
    public function toApiArray(): array
    {
        return [
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'accent_color' => $this->accent_color,
            'background_color' => $this->background_color,
            'text_color' => $this->text_color,
            'logo_url' => $this->logo_url,
            'logo_dark_url' => $this->logo_dark_url,
            'favicon_url' => $this->favicon_url,
            'login_background_url' => $this->login_background_url,
            'font_family' => $this->font_family,
            'heading_font_family' => $this->heading_font_family,
            'border_radius' => $this->border_radius,
            'button_style' => $this->button_style,
            'custom_css' => $this->custom_css,
        ];
    }
}
