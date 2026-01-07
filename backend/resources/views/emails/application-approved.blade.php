<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud Aprobada</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; text-align: center; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
        .highlight { background: #d1fae5; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #10b981; }
        .details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .details table { width: 100%; border-collapse: collapse; }
        .details td { padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        .details td:last-child { text-align: right; font-weight: bold; }
        .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 12px; }
        .btn { display: inline-block; background: #10b981; color: white; padding: 12px 30px; border-radius: 6px; text-decoration: none; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>¡Felicidades!</h1>
        <p>Tu solicitud ha sido aprobada</p>
    </div>
    <div class="content">
        <p>Hola <strong>{{ $name }}</strong>,</p>

        <div class="highlight">
            <p style="margin: 0; font-size: 18px;">Tu solicitud de crédito <strong>#{{ $folio }}</strong> ha sido <strong>APROBADA</strong>.</p>
        </div>

        <div class="details">
            <h3>Detalles de tu crédito:</h3>
            <table>
                <tr>
                    <td>Producto</td>
                    <td>{{ $product }}</td>
                </tr>
                <tr>
                    <td>Monto aprobado</td>
                    <td>${{ $approved_amount }} MXN</td>
                </tr>
                <tr>
                    <td>Plazo</td>
                    <td>{{ $term_months }} meses</td>
                </tr>
                <tr>
                    <td>Pago mensual</td>
                    <td>${{ $monthly_payment }} MXN</td>
                </tr>
            </table>
        </div>

        <p>El siguiente paso es la firma de tu contrato y la dispersión de los fondos a tu cuenta bancaria registrada.</p>

        <p style="text-align: center;">
            <a href="#" class="btn">Ver mi solicitud</a>
        </p>

        <p>Si tienes alguna pregunta, no dudes en contactarnos.</p>

        <p>Saludos,<br>El equipo de Créditos</p>
    </div>
    <div class="footer">
        <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
    </div>
</body>
</html>
