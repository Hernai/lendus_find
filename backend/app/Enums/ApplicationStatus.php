<?php

namespace App\Enums;

/**
 * Application status enum.
 *
 * Uses English values as canonical for consistency across all enums.
 * Includes normalize() method for compatibility with legacy Spanish values.
 */
enum ApplicationStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case IN_REVIEW = 'IN_REVIEW';
    case DOCS_PENDING = 'DOCS_PENDING';
    case CORRECTIONS_PENDING = 'CORRECTIONS_PENDING';
    case COUNTER_OFFERED = 'COUNTER_OFFERED';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';
    case DISBURSED = 'DISBURSED';
    case ACTIVE = 'ACTIVE';
    case COMPLETED = 'COMPLETED';
    case DEFAULT = 'DEFAULT';

    /**
     * Get human-readable label in Spanish.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::SUBMITTED => 'Enviada',
            self::IN_REVIEW => 'En revisión',
            self::DOCS_PENDING => 'Documentos pendientes',
            self::CORRECTIONS_PENDING => 'Correcciones pendientes',
            self::COUNTER_OFFERED => 'Contraoferta',
            self::APPROVED => 'Aprobada',
            self::REJECTED => 'Rechazada',
            self::CANCELLED => 'Cancelada',
            self::DISBURSED => 'Desembolsada',
            self::ACTIVE => 'Activa',
            self::COMPLETED => 'Completada',
            self::DEFAULT => 'En mora',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SUBMITTED => 'blue',
            self::IN_REVIEW => 'yellow',
            self::DOCS_PENDING, self::CORRECTIONS_PENDING => 'orange',
            self::COUNTER_OFFERED => 'purple',
            self::APPROVED => 'green',
            self::REJECTED, self::CANCELLED => 'red',
            self::DISBURSED, self::ACTIVE => 'teal',
            self::COMPLETED => 'green',
            self::DEFAULT => 'red',
        };
    }

    /**
     * Get icon name for UI display.
     */
    public function icon(): string
    {
        return match ($this) {
            self::DRAFT => 'edit',
            self::SUBMITTED => 'clock',
            self::IN_REVIEW => 'search',
            self::DOCS_PENDING => 'document',
            self::CORRECTIONS_PENDING => 'edit',
            self::COUNTER_OFFERED => 'refresh',
            self::APPROVED => 'check',
            self::REJECTED => 'x',
            self::CANCELLED => 'ban',
            self::DISBURSED => 'cash',
            self::ACTIVE => 'activity',
            self::COMPLETED => 'check-circle',
            self::DEFAULT => 'alert',
        };
    }

    /**
     * Check if status is final (no more changes allowed).
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::REJECTED,
            self::CANCELLED,
            self::COMPLETED,
            self::DEFAULT,
        ]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Normalize a status value to the canonical enum.
     * Handles both English and legacy Spanish values.
     */
    public static function normalize(string $value): ?self
    {
        $normalized = strtoupper(trim($value));

        // Direct match
        $direct = self::tryFrom($normalized);
        if ($direct !== null) {
            return $direct;
        }

        // Map legacy Spanish values to English equivalents
        return match ($normalized) {
            'BORRADOR' => self::DRAFT,
            'ENVIADA' => self::SUBMITTED,
            'EN_REVISION' => self::IN_REVIEW,
            'DOCS_PENDIENTES' => self::DOCS_PENDING,
            'CORRECCIONES_PENDIENTES' => self::CORRECTIONS_PENDING,
            'CONTRAOFERTA' => self::COUNTER_OFFERED,
            'APROBADA' => self::APPROVED,
            'RECHAZADA' => self::REJECTED,
            'CANCELADA' => self::CANCELLED,
            'DESEMBOLSADA' => self::DISBURSED,
            'ACTIVA' => self::ACTIVE,
            'COMPLETADA' => self::COMPLETED,
            'EN_MORA' => self::DEFAULT,
            default => null,
        };
    }
}
