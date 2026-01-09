<?php

namespace App\Enums;

enum DocumentType: string
{
    case INE_FRONT = 'INE_FRONT';
    case INE_BACK = 'INE_BACK';
    case PROOF_ADDRESS = 'PROOF_ADDRESS';
    case PROOF_INCOME = 'PROOF_INCOME';
    case BANK_STATEMENT = 'BANK_STATEMENT';
    case RFC_CONSTANCIA = 'RFC_CONSTANCIA';
    case SIGNATURE = 'SIGNATURE';
    case PAYSLIP_1 = 'PAYSLIP_1';
    case PAYSLIP_2 = 'PAYSLIP_2';
    case PAYSLIP_3 = 'PAYSLIP_3';
    case VEHICLE_INVOICE = 'VEHICLE_INVOICE';
    case SELFIE = 'SELFIE';

    /**
     * Get human-readable description in Spanish.
     */
    public function description(): string
    {
        return match ($this) {
            self::INE_FRONT => 'Identificación oficial (frente)',
            self::INE_BACK => 'Identificación oficial (reverso)',
            self::PROOF_ADDRESS => 'Comprobante de domicilio',
            self::PROOF_INCOME => 'Comprobante de ingresos',
            self::BANK_STATEMENT => 'Estado de cuenta bancario',
            self::RFC_CONSTANCIA => 'Constancia de situación fiscal',
            self::SIGNATURE => 'Firma',
            self::PAYSLIP_1 => 'Recibo de nómina 1',
            self::PAYSLIP_2 => 'Recibo de nómina 2',
            self::PAYSLIP_3 => 'Recibo de nómina 3',
            self::VEHICLE_INVOICE => 'Factura del vehículo',
            self::SELFIE => 'Foto de perfil (Selfie)',
        };
    }

    /**
     * Check if document is always required.
     */
    public function isRequired(): bool
    {
        return in_array($this, [
            self::INE_FRONT,
            self::INE_BACK,
            self::PROOF_ADDRESS,
        ]);
    }

    /**
     * Get allowed MIME types.
     */
    public function allowedMimeTypes(): array
    {
        return match ($this) {
            self::SIGNATURE => ['image/png', 'image/svg+xml'],
            self::SELFIE => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
            default => [
                'image/jpeg',
                'image/jpg',
                'image/png',
                'application/pdf',
            ],
        };
    }

    /**
     * Get maximum file size in bytes.
     */
    public function maxFileSize(): int
    {
        return 5 * 1024 * 1024; // 5MB
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
