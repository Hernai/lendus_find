<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

enum DocumentType: string
{
    use HasOptions;
    // Identification documents
    case INE_FRONT = 'INE_FRONT';
    case INE_BACK = 'INE_BACK';
    case PASSPORT = 'PASSPORT';
    case CURP = 'CURP';
    case CURP_DOC = 'CURP_DOC';
    case DRIVER_LICENSE_FRONT = 'DRIVER_LICENSE_FRONT';
    case DRIVER_LICENSE_BACK = 'DRIVER_LICENSE_BACK';
    case SELFIE = 'SELFIE';
    case SIGNATURE = 'SIGNATURE';

    // Address documents
    case PROOF_OF_ADDRESS = 'PROOF_OF_ADDRESS';
    case UTILITY_BILL = 'UTILITY_BILL';
    case BANK_STATEMENT_ADDRESS = 'BANK_STATEMENT_ADDRESS';
    case LEASE_AGREEMENT = 'LEASE_AGREEMENT';
    case PROPERTY_DEED = 'PROPERTY_DEED';

    // Income documents
    case PAYSLIP = 'PAYSLIP';
    case PAYSLIP_1 = 'PAYSLIP_1';
    case PAYSLIP_2 = 'PAYSLIP_2';
    case PAYSLIP_3 = 'PAYSLIP_3';
    case BANK_STATEMENT = 'BANK_STATEMENT';
    case IMSS_STATEMENT = 'IMSS_STATEMENT';
    case EMPLOYMENT_LETTER = 'EMPLOYMENT_LETTER';
    case INCOME_AFFIDAVIT = 'INCOME_AFFIDAVIT';

    // Tax documents
    case RFC_CONSTANCIA = 'RFC_CONSTANCIA';
    case RFC = 'RFC';
    case TAX_RETURN = 'TAX_RETURN';

    // Vehicle/Leasing
    case VEHICLE_INVOICE = 'VEHICLE_INVOICE';

    // Civil documents
    case BIRTH_CERTIFICATE = 'BIRTH_CERTIFICATE';
    case MARRIAGE_CERTIFICATE = 'MARRIAGE_CERTIFICATE';

    // Business documents (PyME)
    case BUSINESS_LICENSE = 'BUSINESS_LICENSE';
    case CONSTITUTIVE_ACT = 'CONSTITUTIVE_ACT';
    case POWER_OF_ATTORNEY = 'POWER_OF_ATTORNEY';
    case TAX_ID_COMPANY = 'TAX_ID_COMPANY';
    case FISCAL_SITUATION = 'FISCAL_SITUATION';
    case LEGAL_REP_ID = 'LEGAL_REP_ID';
    case SHAREHOLDER_STRUCTURE = 'SHAREHOLDER_STRUCTURE';

    // Other
    case OTHER = 'OTHER';

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
            self::INE_FRONT => 'INE (Frente)',
            self::INE_BACK => 'INE (Reverso)',
            self::PASSPORT => 'Pasaporte',
            self::CURP, self::CURP_DOC => 'CURP',
            self::DRIVER_LICENSE_FRONT => 'Licencia de Conducir (Frente)',
            self::DRIVER_LICENSE_BACK => 'Licencia de Conducir (Reverso)',
            self::SELFIE => 'Foto de perfil (Selfie)',
            self::SIGNATURE => 'Firma',
            // Address
            self::PROOF_OF_ADDRESS => 'Comprobante de domicilio',
            self::UTILITY_BILL => 'Recibo de servicios',
            self::BANK_STATEMENT_ADDRESS => 'Estado de cuenta (Domicilio)',
            self::LEASE_AGREEMENT => 'Contrato de arrendamiento',
            self::PROPERTY_DEED => 'Escrituras',
            // Income
            self::PAYSLIP => 'Recibo de nómina',
            self::PAYSLIP_1 => 'Recibo de nómina 1',
            self::PAYSLIP_2 => 'Recibo de nómina 2',
            self::PAYSLIP_3 => 'Recibo de nómina 3',
            self::BANK_STATEMENT => 'Estado de cuenta bancario',
            self::IMSS_STATEMENT => 'Estado de cuenta IMSS',
            self::EMPLOYMENT_LETTER => 'Carta laboral',
            self::INCOME_AFFIDAVIT => 'Declaración de ingresos',
            // Tax
            self::RFC_CONSTANCIA, self::RFC => 'Constancia de situación fiscal',
            self::TAX_RETURN => 'Declaración de impuestos',
            // Vehicle
            self::VEHICLE_INVOICE => 'Factura del vehículo',
            // Civil
            self::BIRTH_CERTIFICATE => 'Acta de nacimiento',
            self::MARRIAGE_CERTIFICATE => 'Acta de matrimonio',
            // Business
            self::BUSINESS_LICENSE => 'Licencia comercial',
            self::CONSTITUTIVE_ACT => 'Acta constitutiva',
            self::POWER_OF_ATTORNEY => 'Poder notarial',
            self::TAX_ID_COMPANY => 'RFC de empresa',
            self::FISCAL_SITUATION => 'Situación fiscal',
            self::LEGAL_REP_ID => 'Identificación del representante legal',
            self::SHAREHOLDER_STRUCTURE => 'Estructura accionaria',
            // Other
            self::OTHER => 'Otro documento',
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
            self::PROOF_OF_ADDRESS,
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
