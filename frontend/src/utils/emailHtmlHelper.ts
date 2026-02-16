/**
 * Helper para generar plantillas HTML de email profesionales.
 * Genera un diseño de 6 secciones: barra acento, tenant header, hero con icono,
 * body (saludo, contenido, tarjeta detalles, CTA, despedida), y footer.
 */

export type EmailIcon =
  | 'wave'
  | 'check'
  | 'mail-sent'
  | 'search'
  | 'celebrate'
  | 'clipboard'
  | 'document'
  | 'edit'
  | 'warning'
  | 'lock'
  | 'clock'
  | 'user-edit'
  | 'key'
  | 'inbox'
  | 'star'

export interface EmailTemplateOptions {
  gradient: string
  heading: string
  body: string
  icon?: EmailIcon
  greeting?: string
  details?: string
  detailsTitle?: string
  detailsTint?: 'blue' | 'green' | 'amber' | 'red' | 'neutral' | 'purple'
  ctaText?: string
  ctaUrl?: string
}

/**
 * Caracteres Unicode tipograficos para iconos de email.
 * SOLO caracteres del bloque Geometric Shapes (U+25xx), Dingbats y Latin
 * que NO tienen variante emoji. Se agrega U+FE0E (text presentation selector)
 * para forzar renderizado de texto en todos los clientes.
 */
const iconChars: Record<EmailIcon, string> = {
  wave:        '&#10022;&#xFE0E;', // ✦ FOUR POINTED BLACK STAR
  check:       '&#10003;&#xFE0E;', // ✓ CHECK MARK
  'mail-sent': '&#10148;&#xFE0E;', // ➤ BLACK RIGHT-POINTING POINTER
  search:      '&#9678;&#xFE0E;',  // ◎ BULLSEYE
  celebrate:   '&#10022;&#xFE0E;', // ✦ FOUR POINTED BLACK STAR
  clipboard:   '&#9776;&#xFE0E;',  // ☰ TRIGRAM FOR HEAVEN
  document:    '&#9868;&#xFE0E;',  // ⚌ DIGRAM FOR GREATER YANG (lines)
  edit:        '&#9998;&#xFE0E;',  // ✎ LOWER RIGHT PENCIL
  warning:     '!',                 // ! EXCLAMATION MARK
  lock:        '&#9670;&#xFE0E;',  // ◆ BLACK DIAMOND
  clock:       '&#9684;&#xFE0E;',  // ◔ CIRCLE WITH UPPER RIGHT QUADRANT
  'user-edit': '&#9673;&#xFE0E;',  // ◉ FISHEYE
  key:         '&#9674;&#xFE0E;',  // ◊ LOZENGE
  inbox:       '&#8595;&#xFE0E;',  // ↓ DOWNWARDS ARROW
  star:        '&#10022;&#xFE0E;', // ✦ FOUR POINTED BLACK STAR
}

const tintColors: Record<string, { bg: string; border: string }> = {
  blue: { bg: '#eff6ff', border: '#bfdbfe' },
  green: { bg: '#f0fdf4', border: '#bbf7d0' },
  amber: { bg: '#fffbeb', border: '#fde68a' },
  red: { bg: '#fef2f2', border: '#fecaca' },
  neutral: { bg: '#f8fafc', border: '#e2e8f0' },
  purple: { bg: '#faf5ff', border: '#e9d5ff' },
}

/**
 * Genera filas de detalle para la tarjeta de detalles del email.
 * Aplica separadores automaticos entre filas (sin separador en la ultima).
 */
export const detailRows = (...pairs: [string, string][]): string =>
  pairs
    .map(
      ([label, value], i) =>
        `<tr>
  <td style="padding:10px 16px;color:#64748b;font-size:14px">${label}</td>
  <td align="right" style="padding:10px 16px;color:#1e293b;font-size:14px;font-weight:600">${value}</td>
</tr>${i < pairs.length - 1 ? '<tr><td colspan="2" style="padding:0"><div style="border-top:1px solid #e2e8f0"></div></td></tr>' : ''}`,
    )
    .join('')

/**
 * Genera una plantilla HTML de email profesional con secciones bien definidas.
 */
export const emailHtml = (opts: EmailTemplateOptions): string => {
  const tint = tintColors[opts.detailsTint || 'neutral']

  const detailsBlock = opts.details
    ? `<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:${tint.bg};border:1px solid ${tint.border};border-radius:12px;overflow:hidden;margin:24px 0">
${opts.detailsTitle ? `<tr><td colspan="2" style="padding:14px 16px 6px;font-size:13px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.5px">${opts.detailsTitle}</td></tr>` : ''}
${opts.details}
</table>`
    : ''

  const ctaBlock =
    opts.ctaText && opts.ctaUrl
      ? `<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 0">
<tr><td align="center">
  <a href="${opts.ctaUrl}" style="display:inline-block;background:linear-gradient(135deg,${opts.gradient});color:#fff;text-decoration:none;padding:14px 36px;border-radius:8px;font-weight:600;font-size:15px;box-shadow:0 4px 12px rgba(0,0,0,0.15)">${opts.ctaText}</a>
</td></tr>
</table>`
      : ''

  const greetingBlock = opts.greeting
    ? `<p style="margin:0 0 16px 0;color:#374151;font-size:16px;line-height:1.6">${opts.greeting}</p>`
    : ''

  const iconChar = opts.icon ? iconChars[opts.icon] : null
  const iconBlock = iconChar
    ? `<table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 16px" role="presentation"><tr><td align="center" valign="middle" width="64" height="64" style="width:64px;height:64px;border-radius:32px;background:linear-gradient(135deg,${opts.gradient});text-align:center;font-size:28px;color:#ffffff;font-family:Arial,sans-serif;line-height:64px;box-shadow:0 6px 16px rgba(0,0,0,0.12)">${iconChar}</td></tr></table>`
    : ''

  return `<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background-color:#f3f4f6">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6;padding:40px 0"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">

<!-- [1] BARRA ACENTO -->
<tr><td style="background:linear-gradient(135deg,${opts.gradient});height:4px"></td></tr>

<!-- [2] TENANT HEADER -->
<tr><td style="padding:24px 40px 0;text-align:center">
  <span style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:2px">{{tenant.name}}</span>
</td></tr>

<!-- [3] HERO: Icono + Heading -->
<tr><td style="padding:28px 40px 0;text-align:center">
${iconBlock}
  <h1 style="margin:0;color:#111827;font-size:24px;font-weight:700;line-height:1.3">${opts.heading}</h1>
</td></tr>

<!-- [4] BODY -->
<tr><td style="padding:28px 40px 40px">
  ${greetingBlock}
  ${opts.body}
  ${detailsBlock}
  ${ctaBlock}
  <p style="margin:28px 0 0;color:#6b7280;font-size:14px;line-height:1.5">Saludos,<br><strong>Equipo {{tenant.name}}</strong></p>
</td></tr>

<!-- [5] FOOTER -->
<tr><td style="background-color:#f8fafc;padding:20px 40px;text-align:center;border-top:1px solid #e5e7eb">
  <p style="margin:0 0 4px;color:#94a3b8;font-size:13px;font-weight:600">{{tenant.name}}</p>
  <p style="margin:0;color:#cbd5e1;font-size:11px">Correo automático · No responder</p>
</td></tr>

</table>
</td></tr></table>
</body>
</html>`
}
