// Professional HTML Email Templates

export interface EmailTemplate {
    id: string
    name: string
    description: string
    thumbnail: string
    html: string
}

export const emailTemplates: EmailTemplate[] = [
    {
        id: 'hero-banner',
        name: 'Banner Hero',
        description: 'Diseño con banner superior colorido y secciones definidas',
        thumbnail: '🎯',
        html: `<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{tenant.name}}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f5f5f5;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                    <!-- Hero Banner -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); padding: 48px 40px; text-align: center;">
                            <div style="background-color: rgba(255,255,255,0.2); display: inline-block; padding: 12px 24px; border-radius: 50px; margin-bottom: 16px;">
                                <span style="color: #ffffff; font-size: 13px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase;">{{tenant.name}}</span>
                            </div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 32px; font-weight: 700; line-height: 1.2;">
                                ¡Hola {{applicant.first_name}}!
                            </h1>
                        </td>
                    </tr>
                    <!-- Content Section -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 24px 0; color: #374151; font-size: 16px; line-height: 1.7;">
                                Tu contenido va aquí.
                            </p>
                            <!-- Info Cards -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 32px 0;">
                                <tr>
                                    <td width="48%" style="vertical-align: top;">
                                        <div style="background-color: #eff6ff; border-radius: 12px; padding: 24px; text-align: center;">
                                            <div style="color: #3b82f6; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Folio</div>
                                            <div style="color: #1e40af; font-size: 20px; font-weight: 700;">{{application.folio}}</div>
                                        </div>
                                    </td>
                                    <td width="4%"></td>
                                    <td width="48%" style="vertical-align: top;">
                                        <div style="background-color: #f0fdf4; border-radius: 12px; padding: 24px; text-align: center;">
                                            <div style="color: #10b981; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Monto</div>
                                            <div style="color: #047857; font-size: 24px; font-weight: 700;">{{application.amount}}</div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <!-- CTA Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center">
                                        <a href="#" style="display: inline-block; background-color: #3b82f6; color: #ffffff; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-weight: 600; font-size: 15px; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);">
                                            Ver Mi Solicitud
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 24px 40px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; color: #9ca3af; font-size: 12px; line-height: 1.6;">
                                Este es un correo automático de {{tenant.name}}<br>
                                Por favor no respondas a este mensaje
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>`,
    },

    {
        id: 'circles-badge',
        name: 'Círculos con Badge',
        description: 'Diseño moderno con íconos circulares y badge central',
        thumbnail: '⭕',
        html: `<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{tenant.name}}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 20px; overflow: hidden;">
                    <!-- Header with Circles -->
                    <tr>
                        <td align="center" style="padding: 48px 40px; position: relative;">
                            <!-- Background Circles -->
                            <div style="position: absolute; top: -40px; right: -40px; width: 120px; height: 120px; background-color: #dbeafe; border-radius: 50%; opacity: 0.4;"></div>
                            <div style="position: absolute; bottom: -20px; left: -30px; width: 80px; height: 80px; background-color: #fef3c7; border-radius: 50%; opacity: 0.5;"></div>
                            <!-- Success Icon -->
                            <div style="position: relative; display: inline-block; margin-bottom: 24px;">
                                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);">
                                    <span style="color: #ffffff; font-size: 40px; line-height: 1;">✓</span>
                                </div>
                            </div>
                            <h1 style="margin: 0 0 8px 0; color: #111827; font-size: 26px; font-weight: 700;">
                                {{tenant.name}}
                            </h1>
                            <p style="margin: 0; color: #6b7280; font-size: 14px;">
                                Hola <strong>{{applicant.first_name}}</strong>
                            </p>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 0 40px 40px 40px;">
                            <p style="margin: 0 0 32px 0; color: #4b5563; font-size: 15px; line-height: 1.7; text-align: center;">
                                Tu contenido va aquí.
                            </p>
                            <!-- Badge Info Box -->
                            <div style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 2px solid #3b82f6; border-radius: 16px; padding: 28px; margin: 0 0 32px 0;">
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td width="50%" style="padding: 8px; text-align: center; border-right: 1px solid #93c5fd;">
                                            <div style="color: #1e40af; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Folio</div>
                                            <div style="color: #1e3a8a; font-size: 18px; font-weight: 700;">{{application.folio}}</div>
                                        </td>
                                        <td width="50%" style="padding: 8px; text-align: center;">
                                            <div style="color: #1e40af; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Monto</div>
                                            <div style="color: #1e3a8a; font-size: 22px; font-weight: 700;">{{application.amount}}</div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center">
                                        <a href="#" style="display: inline-block; background-color: #3b82f6; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600; font-size: 15px;">
                                            Acceder →
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px 40px; text-align: center;">
                            <p style="margin: 0; color: #9ca3af; font-size: 11px; line-height: 1.6;">
                                Correo automático de {{tenant.name}} • No responder
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>`,
    },

    {
        id: 'split-color',
        name: 'División de Color',
        description: 'Diseño split con sección de color y sección blanca',
        thumbnail: '🎨',
        html: `<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{tenant.name}}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #ffffff;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="box-shadow: 0 10px 40px rgba(0,0,0,0.1); border-radius: 12px; overflow: hidden;">
                    <!-- Color Section -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); padding: 48px 40px;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td>
                                        <div style="background-color: rgba(255,255,255,0.15); display: inline-block; padding: 8px 16px; border-radius: 20px; margin-bottom: 16px;">
                                            <span style="color: #ffffff; font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;">{{tenant.name}}</span>
                                        </div>
                                        <h1 style="margin: 0 0 12px 0; color: #ffffff; font-size: 28px; font-weight: 700; line-height: 1.2;">
                                            Hola {{applicant.first_name}},
                                        </h1>
                                        <p style="margin: 0; color: rgba(255,255,255,0.9); font-size: 15px; line-height: 1.6;">
                                            Tu contenido va aquí.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- White Section -->
                    <tr>
                        <td style="background-color: #ffffff; padding: 40px;">
                            <!-- Info Grid -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 32px 0;">
                                <tr>
                                    <td style="background-color: #f8fafc; border-left: 4px solid #6366f1; border-radius: 8px; padding: 20px 24px; margin-bottom: 16px;">
                                        <div style="color: #6366f1; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Número de Folio</div>
                                        <div style="color: #1e293b; font-size: 20px; font-weight: 700;">{{application.folio}}</div>
                                    </td>
                                </tr>
                            </table>
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 32px 0;">
                                <tr>
                                    <td style="background-color: #f0fdf4; border-left: 4px solid #10b981; border-radius: 8px; padding: 20px 24px;">
                                        <div style="color: #10b981; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Monto Solicitado</div>
                                        <div style="color: #065f46; font-size: 26px; font-weight: 700;">{{application.amount}}</div>
                                    </td>
                                </tr>
                            </table>
                            <!-- Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td>
                                        <a href="#" style="display: inline-block; background-color: #6366f1; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px;">
                                            Ver Detalles →
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 24px 40px; border-top: 1px solid #e2e8f0; text-align: center;">
                            <p style="margin: 0; color: #94a3b8; font-size: 12px; line-height: 1.5;">
                                {{tenant.name}} • Mensaje automático
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>`,
    },

    {
        id: 'minimal-elegant',
        name: 'Minimalista Elegante',
        description: 'Diseño limpio y elegante con tipografía destacada',
        thumbnail: '✨',
        html: `<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{tenant.name}}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #fafafa;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #fafafa;">
        <tr>
            <td align="center" style="padding: 60px 20px;">
                <table width="560" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 2px;">
                    <!-- Accent Line -->
                    <tr>
                        <td style="background-color: #3b82f6; height: 4px;"></td>
                    </tr>
                    <!-- Header -->
                    <tr>
                        <td style="padding: 48px 48px 32px 48px; border-bottom: 1px solid #f3f4f6;">
                            <h1 style="margin: 0 0 4px 0; color: #111827; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 2px;">
                                {{tenant.name}}
                            </h1>
                            <div style="width: 32px; height: 2px; background-color: #3b82f6; margin-top: 12px;"></div>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 48px;">
                            <p style="margin: 0 0 8px 0; color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">
                                Estimado/a
                            </p>
                            <h2 style="margin: 0 0 32px 0; color: #111827; font-size: 32px; font-weight: 300; letter-spacing: -0.5px;">
                                {{applicant.first_name}}
                            </h2>
                            <p style="margin: 0 0 32px 0; color: #4b5563; font-size: 15px; line-height: 1.8;">
                                Tu contenido va aquí.
                            </p>
                            <!-- Info Table -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; margin: 0 0 32px 0;">
                                <tr>
                                    <td style="padding: 20px 0;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="padding: 8px 0; color: #9ca3af; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; width: 40%;">
                                                    Folio
                                                </td>
                                                <td style="padding: 8px 0; color: #111827; font-size: 18px; font-weight: 600; text-align: right;">
                                                    {{application.folio}}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 0 8px 0; color: #9ca3af; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                                                    Monto
                                                </td>
                                                <td style="padding: 12px 0 8px 0; color: #3b82f6; font-size: 24px; font-weight: 600; text-align: right;">
                                                    {{application.amount}}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td>
                                        <a href="#" style="display: inline-block; background-color: #111827; color: #ffffff; text-decoration: none; padding: 12px 28px; font-weight: 500; font-size: 14px; letter-spacing: 0.3px;">
                                            ACCEDER →
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 32px 48px; background-color: #fafafa; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; color: #9ca3af; font-size: 11px; line-height: 1.6; letter-spacing: 0.3px;">
                                {{tenant.name}}<br>
                                CORREO AUTOMÁTICO • NO RESPONDER
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>`,
    },
]
