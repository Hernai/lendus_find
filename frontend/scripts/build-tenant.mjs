#!/usr/bin/env node
/**
 * Orquestador de build per-tenant.
 *
 * Uso:
 *   npm run tenant:build -- <slug>
 *
 * Pasos:
 * 1. Carga `tenants/<slug>.tenant.ts` con jiti (TS en Node).
 * 2. Exporta variables VITE_* derivadas de la config (apiBaseUrl, reverb*).
 * 3. Ejecuta `vite build`.
 * 4. Copia assets PNG del tenant a `assets/` para que `@capacitor/assets` los
 *    use al regenerar iconos/splash (paso opcional, solo si la dep está
 *    instalada).
 * 5. Si los proyectos `ios/` y `android/` existen, hace `npx cap sync`.
 */

import { spawn } from 'node:child_process'
import { existsSync, mkdirSync, copyFileSync, writeFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { createJiti } from 'jiti'

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
// `.env.local` temporal antes del build y lo eliminamos al final. (No usamos
// process.env porque Vite no lo lee directamente.)
const envFile = join(frontendRoot, '.env.local')
const envLines = [
  `# Generado por scripts/build-tenant.mjs para tenant=${tenant.slug}. NO commitear.`,
  `VITE_TENANT_SLUG=${tenant.slug}`,
  `VITE_API_URL=${tenant.apiBaseUrl}/api`,
  `VITE_REVERB_HOST=${tenant.reverbHost}`,
  `VITE_REVERB_PORT=${tenant.reverbPort}`,
  `VITE_REVERB_SCHEME=${tenant.reverbScheme}`,
  `VITE_REVERB_APP_KEY=${tenant.reverbAppKey}`,
  `VITE_APP_NAME=${tenant.appName}`,
  `VITE_THEME_PRIMARY=${tenant.theme.primary}`,
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

// Si existen proyectos nativos, sincronizar Capacitor.
const hasIos = existsSync(join(frontendRoot, 'ios'))
const hasAndroid = existsSync(join(frontendRoot, 'android'))

if (hasIos || hasAndroid) {
  console.log('▶ Capacitor sync...')
  await runCommand('npx', ['cap', 'sync'], { env })
} else {
  console.log('ℹ Aún no se ejecutó `npx cap add ios/android`. Hazlo una vez para crear los proyectos nativos.')
}

console.log(`✓ Build de tenant ${slug} completado.`)

function copyIfExists(src, dest) {
  const full = resolve(frontendRoot, src)
  if (!existsSync(full)) {
    console.warn(`  · Asset faltante: ${src} (saltando)`)
    return
  }
  copyFileSync(full, dest)
  console.log(`  · ${src} → ${dest.replace(frontendRoot + '/', '')}`)
}

function runCommand(cmd, args, opts) {
  return new Promise((resolveP, rejectP) => {
    const child = spawn(cmd, args, { stdio: 'inherit', cwd: frontendRoot, ...opts })
    child.on('error', rejectP)
    child.on('exit', (code) => {
      if (code === 0) resolveP()
      else rejectP(new Error(`${cmd} ${args.join(' ')} salió con código ${code}`))
    })
  })
}
