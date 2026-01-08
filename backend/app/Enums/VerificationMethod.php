<?php

namespace App\Enums;

enum VerificationMethod: string
{
    case MANUAL = 'MANUAL';
    case OTP = 'OTP';
    case API = 'API';
    case DOCUMENT = 'DOCUMENT';
    case BUREAU = 'BUREAU';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Manual',
            self::OTP => 'OTP',
            self::API => 'API',
            self::DOCUMENT => 'Documento',
            self::BUREAU => 'Buró de crédito',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
