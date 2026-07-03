<?php

namespace App\Helpers;

/**
 * Construye el mensaje del OTP incluyendo el ORIGEN (nombre de la SOFOM), para
 * que el usuario sepa quién le envía el código (clave en white-label: cada
 * tenant tiene su propia marca).
 *
 * El texto de SMS va blindado: sin acentos (evita SMS UCS-2, que reduce el
 * límite de 160 a 70 caracteres) y acotado a 160 caracteres.
 *
 * `$code` puede ser el código real o un placeholder (#code# para proveedores
 * administrados como Nubarium, que sustituyen el código ellos mismos).
 */
class OtpMessage
{
    /** Mensaje de SMS/OTP con origen, sin acentos y ≤160 caracteres. */
    public static function sms(?string $origin, string $code): string
    {
        $origin = self::sanitizeSms($origin);
        $prefix = $origin !== '' ? "{$origin}: " : '';
        $msg = "{$prefix}{$code} es tu codigo de verificacion. No lo compartas con nadie. Vence en 10 min.";

        return mb_substr($msg, 0, 160);
    }

    /** Asunto del OTP por correo (con origen). Acentos permitidos. */
    public static function emailSubject(?string $origin): string
    {
        $origin = trim((string) $origin);

        return $origin !== ''
            ? "Tu código de verificación · {$origin}"
            : 'Tu código de verificación';
    }

    /** Cuerpo (texto plano) del OTP por correo, con origen. */
    public static function emailBody(?string $origin, string $code): string
    {
        $origin = trim((string) $origin);
        $intro = $origin !== '' ? "{$origin} — " : '';

        return "{$intro}Tu código de verificación es: {$code}\n\n"
            . 'No compartas este código con nadie. Expira en 10 minutos.';
    }

    /** Quita acentos y cualquier carácter no ASCII para no caer en SMS UCS-2. */
    private static function sanitizeSms(?string $s): string
    {
        $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U',
        ];
        $s = strtr(trim((string) $s), $map);

        return preg_replace('/[^\x20-\x7E]/', '', $s) ?? '';
    }
}
