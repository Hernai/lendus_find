<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualización de Solicitud</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; text-align: center; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
        .highlight { background: #fef3c7; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b; }
        .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Actualización de tu Solicitud</h1>
    </div>
    <div class="content">
        <p>Hola <strong>{{ $name }}</strong>,</p>

        <p>Después de revisar cuidadosamente tu solicitud <strong>#{{ $folio }}</strong> para el producto <strong>{{ $product }}</strong>, lamentamos informarte que en esta ocasión no ha sido posible aprobarla.</p>

        @if($reason)
        <div class="highlight">
            <p><strong>Motivo:</strong> {{ $reason }}</p>
        </div>
        @endif

        <p>Esta decisión se basa en nuestras políticas de crédito actuales y no significa que no puedas volver a solicitar en el futuro.</p>

        <h3>¿Qué puedes hacer?</h3>
        <ul>
            <li>Revisar y mejorar tu historial crediticio</li>
            <li>Asegurarte de que tus documentos estén actualizados</li>
            <li>Intentar nuevamente en 3-6 meses</li>
        </ul>

        <p>Si tienes alguna pregunta sobre esta decisión, no dudes en contactarnos.</p>

        <p>Saludos,<br>El equipo de Créditos</p>
    </div>
    <div class="footer">
        <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
    </div>
</body>
</html>
