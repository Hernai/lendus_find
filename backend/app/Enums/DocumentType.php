<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

enum DocumentType: string
{
    use HasOptions;
    // Identification documents
    case INE_FRONT = 'INE_FRONT';
    case INE_BACK = 'INE_BACK';
    case CURP = 'CURP';
    case SELFIE = 'SELFIE';
    case SIGNATURE = 'SIGNATURE';

    // Address & Income
    case PROOF_ADDRESS = 'PROOF_ADDRESS';
    case PROOF_INCOME = 'PROOF_INCOME';
    case BANK_STATEMENT = 'BANK_STATEMENT';

    // Tax documents
    case RFC_CONSTANCIA = 'RFC_CONSTANCIA';
    case RFC = 'RFC';  // Alias for RFC_CONSTANCIA (legacy support)
    case TAX_RETURN = 'TAX_RETURN';

    // Employment documents
    case PAYSLIP_1 = 'PAYSLIP_1';
    case PAYSLIP_2 = 'PAYSLIP_2';
    case PAYSLIP_3 = 'PAYSLIP_3';

    // Vehicle/Leasing
    case VEHICLE_INVOICE = 'VEHICLE_INVOICE';

    // Civil documents
    case BIRTH_CERTIFICATE = 'BIRTH_CERTIFICATE';
    case MARRIAGE_CERTIFICATE = 'MARRIAGE_CERTIFICATE';

    // Business documents (PyME)
    case BUSINESS_LICENSE = 'BUSINESS_LICENSE';
    case CONSTITUTIVE_ACT = 'CONSTITUTIVE_ACT';
    case POWER_OF_ATTORNEY = 'POWER_OF_ATTORNEY';

    /**
     * Get human-readable label (alias for description).
     */
    public function label(): string
    {
        return $this->description();
    }

    /**
     * Get human-readable description in Spanish.
     */
    public function description(): string
    {
        return match ($this) {
            // Identification
            self::INE_FRONT => 'Identificación oficial (frente)',
            self::INE_BACK => 'Identificación oficial (reverso)',
            self::CURP => 'CURP',
            self::SELFIE => 'Foto de perfil (Selfie)',
            self::SIGNATURE => 'Firma',
            // Address & Income
            self::PROOF_ADDRESS => 'Comprobante de domicilio',
            self::PROOF_INCOME => 'Comprobante de ingresos',
            self::BANK_STATEMENT => 'Estado de cuenta bancario',
            // Tax
            self::RFC_CONSTANCIA, self::RFC => 'Constancia de situación fiscal',
            self::TAX_RETURN => 'Declaración de impuestos',
            // Employment
            self::PAYSLIP_1 => 'Recibo de nómina 1',
            self::PAYSLIP_2 => 'Recibo de nómina 2',
            self::PAYSLIP_3 => 'Recibo de nómina 3',
            // Vehicle
            self::VEHICLE_INVOICE => 'Factura del vehículo',
            // Civil
            self::BIRTH_CERTIFICATE => 'Acta de nacimiento',
            self::MARRIAGE_CERTIFICATE => 'Acta de matrimonio',
            // Business
            self::BUSINESS_LICENSE => 'Licencia comercial',
            self::CONSTITUTIVE_ACT => 'Acta constitutiva',
            self::POWER_OF_ATTORNEY => 'Poder notarial',
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
