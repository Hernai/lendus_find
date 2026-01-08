<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case LOGIN = 'LOGIN';
    case VERIFY_PHONE = 'VERIFY_PHONE';
    case VERIFY_EMAIL = 'VERIFY_EMAIL';
    case RESET_PASSWORD = 'RESET_PASSWORD';

    public function label(): string
    {
        return match ($this) {
            self::LOGIN => 'Iniciar sesión',
            self::VERIFY_PHONE => 'Verificar teléfono',
            self::VERIFY_EMAIL => 'Verificar correo',
            self::RESET_PASSWORD => 'Restablecer contraseña',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
