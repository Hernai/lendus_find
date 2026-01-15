<?php

namespace App\Enums;

enum VerificationMethod: string
{
    case MANUAL = 'MANUAL';
    case OTP = 'OTP';
    case API = 'API';
    case DOCUMENT = 'DOCUMENT';
    case BUREAU = 'BUREAU';
    case KYC_INE_OCR = 'KYC_INE_OCR';
    case KYC_INE_LIST = 'KYC_INE_LIST';
    case KYC_CURP_RENAPO = 'KYC_CURP_RENAPO';
    case KYC_RFC_SAT = 'KYC_RFC_SAT';
    case RENAPO = 'RENAPO'; // Alias for CURP validation via RENAPO
    case SAT = 'SAT'; // Alias for RFC validation via SAT
    case KYC_FACE_MATCH = 'KYC_FACE_MATCH';
    case KYC_LIVENESS = 'KYC_LIVENESS';
    case KYC_OFAC = 'KYC_OFAC';
    case KYC_PLD = 'KYC_PLD';
    case NUBARIUM = 'NUBARIUM';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Manual',
            self::OTP => 'OTP',
            self::API => 'API',
            self::DOCUMENT => 'Documento',
            self::BUREAU => 'Buró de crédito',
            self::KYC_INE_OCR => 'OCR de INE',
            self::KYC_INE_LIST => 'Lista Nominal INE',
            self::KYC_CURP_RENAPO => 'CURP RENAPO',
            self::KYC_RFC_SAT => 'RFC SAT',
            self::KYC_FACE_MATCH => 'Reconocimiento facial',
            self::KYC_LIVENESS => 'Prueba de vida',
            self::KYC_OFAC => 'Lista OFAC',
            self::KYC_PLD => 'Listas PLD',
            self::NUBARIUM => 'Nubarium',
            self::RENAPO => 'RENAPO',
            self::SAT => 'SAT',
        };
    }

    /**
     * Check if this is a KYC/automated method.
     */
    public function isAutomated(): bool
    {
        return in_array($this, [
            self::OTP, // Phone verified by OTP should be locked
            self::KYC_INE_OCR,
            self::KYC_INE_LIST,
            self::KYC_CURP_RENAPO,
            self::KYC_RFC_SAT,
            self::KYC_FACE_MATCH,
            self::KYC_LIVENESS,
            self::KYC_OFAC,
            self::KYC_PLD,
            self::NUBARIUM,
            self::API,
            self::BUREAU,
            self::RENAPO,
            self::SAT,
        ]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
