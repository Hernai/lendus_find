<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cliente de Google reCAPTCHA v3 (invisible, score-based).
 *
 * Resultado de `verify($token, $action)`:
 *   - `null`  → validacion skipeada porque no hay secret_key configurada
 *               (local/testing, behaves como si fuera valido).
 *   - `true`  → token valido + score >= min_score + action coincide.
 *   - `false` → token invalido, score muy bajo, action no coincide, o
 *               request fallo (errores de red, timeout, secret invalida).
 *
 * El controller decide si tratar `null` como "permitir" (default) o
 * "rechazar" segun politica. En este proyecto: `null` permite, asumiendo
 * que si nadie configuro keys es porque el endpoint no requiere captcha
 * o es entorno de testing.
 *
 * Logs en `Log::warning` con razon de rechazo para debugging — NO
 * logueamos el token completo, solo prefijo + razon de Google.
 */
class RecaptchaService
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
    private const HTTP_TIMEOUT_SEC = 4;

    /**
     * Verifica un token de reCAPTCHA v3 contra Google.
     *
     * @param  string|null  $token  Token recibido del frontend (g-recaptcha-response)
     * @param  string  $expectedAction  Accion esperada (ej. 'login', 'register')
     * @return bool|null  true=valido, false=rechazado, null=skipeado (no configurado)
     */
    public function verify(?string $token, string $expectedAction): ?bool
    {
        $secret = config('services.recaptcha.secret_key');

        // Sin secret configurada → skipeamos validacion (local/testing).
        if (! $secret) {
            return null;
        }

        // Con secret configurada, el token es OBLIGATORIO.
        if (! $token) {
            Log::warning('reCAPTCHA: token vacio en request', ['action' => $expectedAction]);
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(self::HTTP_TIMEOUT_SEC)
                ->post(self::VERIFY_URL, [
                    'secret' => $secret,
                    'response' => $token,
                ]);
        } catch (Throwable $e) {
            // Fallo de red: tratamos como rechazo defensivo. Si Google esta
            // caido, mejor rechazar logins que dejar pasar bots.
            Log::warning('reCAPTCHA: error de red al verificar', [
                'action' => $expectedAction,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        if (! $response->successful()) {
            Log::warning('reCAPTCHA: HTTP non-2xx de Google', [
                'action' => $expectedAction,
                'status' => $response->status(),
            ]);
            return false;
        }

        $data = $response->json();

        if (! ($data['success'] ?? false)) {
            Log::warning('reCAPTCHA: success=false', [
                'action' => $expectedAction,
                'error_codes' => $data['error-codes'] ?? [],
            ]);
            return false;
        }

        $score = (float) ($data['score'] ?? 0);
        $minScore = (float) config('services.recaptcha.min_score', 0.5);
        if ($score < $minScore) {
            Log::warning('reCAPTCHA: score bajo', [
                'action' => $expectedAction,
                'score' => $score,
                'min' => $minScore,
            ]);
            return false;
        }

        if (config('services.recaptcha.verify_action', true)) {
            $returnedAction = $data['action'] ?? null;
            if ($returnedAction !== $expectedAction) {
                Log::warning('reCAPTCHA: action mismatch', [
                    'expected' => $expectedAction,
                    'returned' => $returnedAction,
                ]);
                return false;
            }
        }

        return true;
    }
}
