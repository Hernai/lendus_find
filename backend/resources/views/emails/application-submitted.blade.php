<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud Recibida</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; text-align: center; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
        .highlight { background: #e0e7ff; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 12px; }
        .btn { display: inline-block; background: #6366f1; color: white; padding: 12px 30px; border-radius: 6px; text-decoration: none; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Solicitud Recibida</h1>
    </div>
    <div class="content">
        <p>Hola <strong>{{ $name }}</strong>,</p>

        <p>Hemos recibido tu solicitud de crédito. Nuestro equipo la revisará a la brevedad.</p>

        <div class="highlight">
            <p><strong>Folio:</strong> {{ $folio }}</p>
            <p><strong>Producto:</strong> {{ $product }}</p>
            <p><strong>Monto solicitado:</strong> ${{ $amount }} MXN</p>
        </div>

        <p>Te notificaremos por este medio cuando tengamos una resolución. El proceso normalmente toma de 1 a 3 días hábiles.</p>

        <p>Si tienes alguna duda, no dudes en contactarnos.</p>

        <p>Saludos,<br>El equipo de Créditos</p>
    </div>
    <div class="footer">
        <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
    </div>
</body>
</html>
