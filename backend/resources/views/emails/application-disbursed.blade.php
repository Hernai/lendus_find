<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crédito Dispersado</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; text-align: center; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
        .highlight { background: #d1fae5; padding: 25px; border-radius: 8px; margin: 20px 0; text-align: center; }
        .amount { font-size: 32px; font-weight: bold; color: #059669; }
        .details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .details table { width: 100%; border-collapse: collapse; }
        .details td { padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        .details td:last-child { text-align: right; font-weight: bold; }
        .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 12px; }
        .warning { background: #fef3c7; padding: 15px; border-radius: 8px; margin-top: 20px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>¡Tu crédito ha sido dispersado!</h1>
    </div>
    <div class="content">
        <p>Hola <strong>{{ $name }}</strong>,</p>

        <p>Nos complace informarte que tu crédito ha sido depositado exitosamente.</p>

        <div class="highlight">
            <p style="margin: 0; color: #6b7280;">Monto depositado</p>
            <div class="amount">${{ $amount }} MXN</div>
        </div>

        <div class="details">
            <h3>Detalles de la dispersión:</h3>
            <table>
                <tr>
                    <td>Folio de solicitud</td>
                    <td>#{{ $folio }}</td>
                </tr>
                <tr>
                    <td>Banco destino</td>
                    <td>{{ $bank_name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>CLABE (últimos 4 dígitos)</td>
                    <td>****{{ $clabe_last4 }}</td>
                </tr>
                <tr>
                    <td>Referencia de dispersión</td>
                    <td>{{ $disbursement_reference }}</td>
                </tr>
            </table>
        </div>

        <p>El depósito puede tardar hasta 24 horas hábiles en reflejarse en tu cuenta, dependiendo de tu banco.</p>

        <div class="warning">
            <strong>Recuerda:</strong> Tu primera fecha de pago será indicada en tu contrato. Asegúrate de tener los fondos disponibles para evitar cargos por mora.
        </div>

        <p>Si tienes alguna pregunta, no dudes en contactarnos.</p>

        <p>Saludos,<br>El equipo de Créditos</p>
    </div>
    <div class="footer">
        <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
    </div>
</body>
</html>
