<?php

namespace App\Services\ExternalApi;

use App\Models\TenantApiConfig;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

/**
 * SMTP email service for tenant-configured SMTP servers.
 *
 * Uses Symfony Mailer to create dynamic SMTP transports per tenant,
 * allowing each tenant to use their own mail server (Office 365, Gmail, etc.).
 */
class SmtpService
{
    protected Mailer $mailer;
    protected TenantApiConfig $config;
    protected string $fromEmail;
    protected string $fromName;

    /**
     * Create a new SmtpService from a TenantApiConfig.
     */
    public static function createFromConfig(TenantApiConfig $config): self
    {
        $instance = new self();
        $instance->config = $config;

        $extraConfig = $config->extra_config ?? [];
        $host = $extraConfig['host'] ?? '';
        $port = $extraConfig['port'] ?? 587;
        $encryption = $extraConfig['encryption'] ?? 'tls';

        $username = $config->api_key;
        $password = $config->api_secret;

        $instance->fromEmail = $config->from_email ?? $username ?? '';
        $instance->fromName = $extraConfig['from_name'] ?? '';

        // Build DSN: smtp://user:pass@host:port?encryption=tls
        $scheme = match ($encryption) {
            'ssl' => 'smtps',
            'tls' => 'smtp',
            default => 'smtp',
        };

        $dsn = "{$scheme}://";
        if ($username) {
            $dsn .= urlencode($username);
            if ($password) {
                $dsn .= ':' . urlencode($password);
            }
            $dsn .= '@';
        }
        $dsn .= "{$host}:{$port}";

        // For TLS (STARTTLS), we need to add verify_peer option
        if ($encryption === 'none') {
            $dsn .= '?verify_peer=0';
        }

        $transport = Transport::fromDsn($dsn);
        $instance->mailer = new Mailer($transport);

        return $instance;
    }

    /**
     * Test SMTP connection by sending EHLO.
     */
    public function testConnection(): array
    {
        try {
            $extraConfig = $this->config->extra_config ?? [];
            $host = $extraConfig['host'] ?? '';
            $port = (int) ($extraConfig['port'] ?? 587);
            $encryption = $extraConfig['encryption'] ?? 'tls';

            // Test basic TCP connectivity first
            $context = stream_context_create();
            if ($encryption === 'ssl') {
                $connectHost = "ssl://{$host}";
            } else {
                $connectHost = $host;
            }

            $socket = @stream_socket_client(
                "{$connectHost}:{$port}",
                $errno,
                $errstr,
                10,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                return [
                    'success' => false,
                    'message' => $this->humanizeConnectionError($errno, $errstr, $host, $port),
                    'error' => "Connection failed: {$errstr} (code: {$errno})",
                ];
            }

            // Read server greeting
            $greeting = fgets($socket, 1024);
            fclose($socket);

            if (!$greeting || !str_starts_with(trim($greeting), '220')) {
                return [
                    'success' => false,
                    'message' => 'El servidor no respondió correctamente. Verifique host y puerto.',
                    'error' => "Unexpected server response: {$greeting}",
                ];
            }

            return [
                'success' => true,
                'message' => 'Conexión SMTP verificada correctamente',
                'details' => [
                    'server_greeting' => trim($greeting),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al verificar la conexión SMTP',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send a test email.
     */
    public function sendTestEmail(string $toEmail): array
    {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName ?: $this->fromEmail))
                ->to($toEmail)
                ->subject('Prueba de correo - LendusFind')
                ->html($this->getTestEmailHtml())
                ->text('Este es un correo de prueba enviado desde LendusFind para verificar la configuración SMTP.');

            $this->mailer->send($email);

            return [
                'success' => true,
                'message' => 'Email de prueba enviado correctamente',
            ];
        } catch (\Exception $e) {
            Log::error('SMTP test email failed', [
                'config_id' => $this->config->id,
                'to' => $toEmail,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $this->humanizeSmtpError($e->getMessage()),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send an email using the configured SMTP.
     */
    public function sendEmail(string $to, string $subject, string $body, ?string $htmlBody = null): array
    {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName ?: $this->fromEmail))
                ->to($to)
                ->subject($subject);

            if ($htmlBody) {
                $email->html($htmlBody)->text($body);
            } else {
                $email->text($body);
            }

            $this->mailer->send($email);

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('SMTP send failed', [
                'config_id' => $this->config->id,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $this->humanizeSmtpError($e->getMessage()),
            ];
        }
    }

    /**
     * Provide human-readable error messages for connection errors.
     */
    protected function humanizeConnectionError(int $errno, string $errstr, string $host, int $port): string
    {
        if ($errno === 110 || str_contains($errstr, 'timed out')) {
            return "No se pudo conectar a {$host}:{$port}. Verifique que el host y puerto sean correctos y que el firewall permita la conexión.";
        }

        if ($errno === 111 || str_contains($errstr, 'Connection refused')) {
            return "Conexión rechazada en {$host}:{$port}. Verifique que el servidor SMTP esté activo y el puerto sea correcto.";
        }

        if (str_contains($errstr, 'getaddrinfo') || str_contains($errstr, 'Name or service not known')) {
            return "No se pudo resolver el host '{$host}'. Verifique que el nombre del servidor sea correcto.";
        }

        return "Error de conexión a {$host}:{$port}: {$errstr}";
    }

    /**
     * Provide human-readable error messages for SMTP errors.
     */
    protected function humanizeSmtpError(string $error): string
    {
        if (str_contains($error, '535') || str_contains($error, 'authentication') || str_contains($error, 'Authentication')) {
            return 'Error de autenticación. Verifique usuario y contraseña. Si usa Gmail, necesita una "Contraseña de aplicación".';
        }

        if (str_contains($error, '534') || str_contains($error, 'less secure')) {
            return 'El servidor requiere autenticación más segura. Si usa Gmail, habilite "Contraseñas de aplicación" en la configuración de seguridad.';
        }

        if (str_contains($error, '550')) {
            return 'El servidor rechazó el envío. Verifique que el email de origen esté autorizado.';
        }

        if (str_contains($error, 'Connection refused') || str_contains($error, 'connection')) {
            return 'No se pudo conectar al servidor SMTP. Verifique host, puerto y tipo de encriptación.';
        }

        if (str_contains($error, 'SSL') || str_contains($error, 'TLS') || str_contains($error, 'crypto')) {
            return 'Error de encriptación. Pruebe cambiando entre TLS y SSL, o verifique que el puerto corresponda al tipo de encriptación.';
        }

        return "Error SMTP: {$error}";
    }

    /**
     * HTML template for test emails.
     */
    protected function getTestEmailHtml(): string
    {
        $date = now()->format('d/m/Y H:i:s');

        return <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 24px;">
                <h2 style="color: #1e293b; margin-top: 0;">Prueba de correo exitosa</h2>
                <p style="color: #475569;">Este es un correo de prueba enviado desde <strong>LendusFind</strong> para verificar que la configuración SMTP funciona correctamente.</p>
                <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 16px 0;">
                <p style="color: #64748b; font-size: 14px; margin-bottom: 0;">Enviado el: {$date}</p>
            </div>
        </div>
        HTML;
    }
}
