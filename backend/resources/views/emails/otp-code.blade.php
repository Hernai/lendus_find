<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de Verificación</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; text-align: center; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; text-align: center; }
        .code-box { background: white; padding: 30px; border-radius: 8px; margin: 30px 0; border: 2px dashed #6366f1; }
        .code { font-size: 36px; font-weight: bold; letter-spacing: 8px; color: #6366f1; font-family: monospace; }
        .expires { color: #6b7280; font-size: 14px; margin-top: 10px; }
        .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 12px; }
        .warning { background: #fef3c7; padding: 15px; border-radius: 8px; margin-top: 20px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Código de Verificación</h1>
    </div>
    <div class="content">
        <p>Tu código de verificación es:</p>

        <div class="code-box">
            <div class="code">{{ $code }}</div>
            <p class="expires">Válido por {{ $expires_in }}</p>
        </div>

        <p>Ingresa este código en la aplicación para continuar.</p>

        <div class="warning">
            <strong>Importante:</strong> Nunca compartas este código con nadie. Nuestro equipo nunca te pedirá este código por teléfono o mensaje.
        </div>
    </div>
    <div class="footer">
        <p>Si no solicitaste este código, puedes ignorar este mensaje.</p>
    </div>
</body>
</html>
