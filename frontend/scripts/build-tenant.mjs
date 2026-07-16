#!/usr/bin/env node
/**
 * Orquestador de build per-tenant.
 *
 * Uso:
 *   npm run tenant:build -- <slug>
 *
 * Pasos:
 * 1. Carga `tenants/<slug>.tenant.ts` con jiti (TS en Node).
 * 2. Genera variables VITE_* per-tenant (slug, app name, color de marca) en
 *    un `.env.local` temporal. La URL del backend (`VITE_API_URL`) y los
 *    `VITE_REVERB_*` los toma Vite directamente de `.env`/`.env.production`
 *    porque son infraestructura compartida (arquitectura B: un solo backend
 *    en `apifind.lendus.app`).
 * 3. Ejecuta `vite build`.
 * 4. Copia assets PNG del tenant a `assets/` para que `@capacitor/assets` los
 *    use al regenerar iconos/splash (paso opcional, solo si la dep está
 *    instalada).
 * 5. Si los proyectos `ios/` y `android/` existen, hace `npx cap sync`.
 */

import { spawn } from 'node:child_process'
import { existsSync, mkdirSync, copyFileSync, writeFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { createJiti } from 'jiti'

const require = createRequire(import.meta.url)

const __dirname = dirname(fileURLToPath(import.meta.url))
const frontendRoot = resolve(__dirname, '..')

const slug = process.argv[2] || process.env.TENANT
if (!slug) {
  console.error('Uso: npm run tenant:build -- <slug>')
  console.error('Ejemplo: npm run tenant:build -- demo')
  process.exit(1)
}

const tenantFile = join(frontendRoot, 'tenants', `${slug}.tenant.ts`)
if (!existsSync(tenantFile)) {
  console.error(`No existe ${tenantFile}`)
  process.exit(1)
}

const jiti = createJiti(import.meta.url, { interopDefault: true })
const tenant = (await jiti.import(tenantFile)).default

if (tenant.slug !== slug) {
  console.warn(`⚠ El slug del archivo (${slug}) no coincide con el del config (${tenant.slug}).`)
}

console.log(`▶ Construyendo tenant: ${tenant.slug} (${tenant.appId})`)

// Snapshot que consume capacitor.config.ts y otros tooling al hacer `cap sync`.
writeFileSync(
  join(frontendRoot, 'capacitor.tenant.json'),
  JSON.stringify(
    {
      appId: tenant.appId,
      appName: tenant.appName,
      splashColor: tenant.assets.splashBackgroundColor,
      slug: tenant.slug,
    },
    null,
    2,
  ),
)

// Vite solo inyecta variables VITE_* desde archivos `.env*`. Escribimos un
// `.env.local` temporal con las vars per-tenant (slug, app name, color) y
// lo eliminamos al final. La URL del backend (`VITE_API_URL`) y los
// `VITE_REVERB_*` NO se generan acá: Vite los lee de `.env`/`.env.production`
// porque son infraestructura compartida en la arquitectura B (un solo backend
// que distingue tenants por el header `X-Tenant-ID`).
const envFile = join(frontendRoot, '.env.local')
const envLines = [
  `# Generado por scripts/build-tenant.mjs para tenant=${tenant.slug}. NO commitear.`,
  `VITE_TENANT_SLUG=${tenant.slug}`,
  `VITE_APP_NAME=${tenant.appName}`,
  // nativeTheme (ex `theme`): solo splash + status bar nativo. Fallback al
  // legacy `theme.primary` para builds en transición.
  `VITE_THEME_PRIMARY=${tenant.nativeTheme?.primary ?? tenant.theme?.primary ?? '#1E40AF'}`,
]
writeFileSync(envFile, envLines.join('\n') + '\n')

const env = { ...process.env, TENANT: tenant.slug }

// Vite build
try {
  await runCommand('npm', ['run', 'build-only'], { env })
} finally {
  // Limpiar el .env.local para no confundir builds posteriores manuales.
  try {
    if (existsSync(envFile)) {
      const { unlinkSync } = await import('node:fs')
      unlinkSync(envFile)
    }
  } catch {
    /* noop */
  }
}

// Copiar assets a la raíz para que @capacitor/assets los encuentre.
const assetsRoot = join(frontendRoot, 'assets')
if (!existsSync(assetsRoot)) mkdirSync(assetsRoot, { recursive: true })

copyIfExists(tenant.assets.icon, join(assetsRoot, 'icon.png'))
copyIfExists(tenant.assets.splash, join(assetsRoot, 'splash.png'))
if (tenant.assets.splashDark) {
  copyIfExists(tenant.assets.splashDark, join(assetsRoot, 'splash-dark.png'))
}

// Si existen proyectos nativos, generar iconos/splash con @capacitor/assets
// y luego sincronizar Capacitor.
const hasIos = existsSync(join(frontendRoot, 'ios'))
const hasAndroid = existsSync(join(frontendRoot, 'android'))

if (hasAndroid) {
  // Branding del splash de Android 12+ (windowSplashScreenBrandingImage,
  // referenciado por res/values-v31/styles.xml). Siempre se escribe: con el
  // wordmark del tenant si lo trae, o transparente para que el recurso exista.
  await writeSplashBranding(tenant.assets.branding)
}

if (hasIos || hasAndroid) {
  console.log('▶ Generando iconos y splash con @capacitor/assets...')
  const platforms = [
    ...(hasAndroid ? ['--android'] : []),
    ...(hasIos ? ['--ios'] : []),
  ]
  // Los PNG del tenant traen transparencia, pero los launchers no admiten
  // iconos ni splash transparentes: @capacitor/assets rellena la
  // transparencia con blanco salvo que se le pase un color explícito.
  const splashBg = tenant.assets.splashBackgroundColor ?? '#FFFFFF'
  const iconBg = tenant.assets.iconBackgroundColor ?? splashBg
  const colorFlags = [
    '--iconBackgroundColor', iconBg,
    '--iconBackgroundColorDark', iconBg,
    '--splashBackgroundColor', splashBg,
    '--splashBackgroundColorDark', splashBg,
  ]
  await runCommand('npx', ['@capacitor/assets', 'generate', ...platforms, ...colorFlags], { env })

  console.log('▶ Capacitor sync...')
  await runCommand('npx', ['cap', 'sync'], { env })
} else {
  console.log('ℹ Aún no se ejecutó `npx cap add ios/android`. Hazlo una vez para crear los proyectos nativos.')
}

console.log(`✓ Build de tenant ${slug} completado.`)

async function writeSplashBranding(brandingSrc) {
  const destDir = join(frontendRoot, 'android/app/src/main/res/drawable-xxhdpi')
  const dest = join(destDir, 'splash_branding.png')
  mkdirSync(destDir, { recursive: true })

  // sharp llega como dependencia transitiva de @capacitor/assets.
  const sharp = require('sharp')
  const canvas = sharp({
    create: { width: 600, height: 240, channels: 4, background: { r: 0, g: 0, b: 0, alpha: 0 } },
  })

  const full = brandingSrc ? resolve(frontendRoot, brandingSrc) : null
  if (full && existsSync(full)) {
    // Centrar el wordmark en el lienzo 600×240 (200×80dp @xxhdpi).
    const logo = await sharp(full).resize(560, 200, { fit: 'inside' }).png().toBuffer()
    const meta = await sharp(logo).metadata()
    await canvas
      .composite([
        {
          input: logo,
          left: Math.round((600 - meta.width) / 2),
          top: Math.round((240 - meta.height) / 2),
        },
      ])
      .png()
      .toFile(dest)
    console.log(`  · branding → drawable-xxhdpi/splash_branding.png`)
  } else {
    if (brandingSrc) console.warn(`  · Branding faltante: ${brandingSrc} (usando placeholder transparente)`)
    await canvas.png().toFile(dest)
    console.log('  · splash_branding.png transparente (tenant sin branding)')
  }
}

function copyIfExists(src, dest) {
  const full = resolve(frontendRoot, src)
  if (!existsSync(full)) {
    console.warn(`  · Asset faltante: ${src} (saltando)`)
    return
  }
  copyFileSync(full, dest)
  console.log(`  · ${src} → ${dest.replace(frontendRoot + '/', '')}`)
}

function resolveCmd(cmd) {
  // On Windows, npm/npx are .cmd wrappers — spawn needs the full name.
  if (process.platform === 'win32' && ['npm', 'npx'].includes(cmd)) return `${cmd}.cmd`
  return cmd
}

function runCommand(cmd, args, opts) {
  return new Promise((resolveP, rejectP) => {
    const isWin = process.platform === 'win32'
    const child = spawn(resolveCmd(cmd), args, {
      stdio: 'inherit',
      cwd: frontendRoot,
      shell: isWin,
      ...opts,
    })
    child.on('error', rejectP)
    child.on('exit', (code) => {
      if (code === 0) resolveP()
      else rejectP(new Error(`${cmd} ${args.join(' ')} salió con código ${code}`))
    })
  })
}
