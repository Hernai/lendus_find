---
name: git-workflow
description: Convenciones de Git para LendusFind. Usar al crear commits, branches, tags, PRs o cualquier operación Git.
---

# Git Workflow

## Cuándo aplica

Usar este skill al realizar cualquier operación Git: crear commits, nombrar branches, crear PRs, hacer tags o releases. Define las convenciones del proyecto basadas en Conventional Commits, GitHub Flow y SemVer.

## Estrategia de Branching: GitHub Flow

```
main ──────────────────────────────── (siempre deployable)
  │         │            │
  ├── feat/envio-notificaciones      (funcionalidad nueva)
  ├── fix/calculo-tasa-simulador     (corrección de bug)
  └── (merge vía PR + review)
```

- `main` es la rama principal y siempre debe estar en estado deployable
- Branches cortos: vida de horas/días, nunca semanas
- Merge a `main` únicamente vía Pull Request con revisión

### Nomenclatura de branches

```
<tipo>/<descripcion-corta-en-kebab-case>
```

| Prefijo | Uso |
|---------|-----|
| `feat/` | Nueva funcionalidad |
| `fix/` | Corrección de bug |
| `refactor/` | Reestructuración sin cambio de comportamiento |
| `docs/` | Documentación |
| `chore/` | Mantenimiento, configuración, dependencias |
| `test/` | Agregar o corregir tests |
| `perf/` | Mejora de rendimiento |
| `ci/` | Cambios en CI/CD |

Ejemplos:

```bash
feat/modal-envio-prueba
fix/validacion-curp-con-enie
refactor/extraer-logica-permisos
chore/actualizar-vue-3.5
docs/guia-instalacion-local
```

## Commits: Conventional Commits

### Formato

```
<tipo>(<alcance>): <descripción>

[cuerpo opcional]

[pie(s) opcional(es)]
```

### Reglas del título

1. **Máximo 50 caracteres** (límite duro: 72)
2. **Modo imperativo**: "agregar", "corregir", "actualizar" (no "agregado", "corregido")
3. **Minúscula** después del tipo: `feat(auth): agregar...` (no `Agregar`)
4. **Sin punto final**
5. **Idioma**: español

### Tipos de commit

| Tipo | Descripción | SemVer |
|------|-------------|--------|
| `feat` | Nueva funcionalidad | MINOR |
| `fix` | Corrección de bug | PATCH |
| `docs` | Solo documentación | PATCH |
| `style` | Formato (espacios, punto y coma) sin cambio de lógica | PATCH |
| `refactor` | Reestructuración sin cambio de comportamiento | PATCH |
| `perf` | Mejora de rendimiento | PATCH |
| `test` | Agregar o corregir tests | PATCH |
| `build` | Dependencias, herramientas de build | PATCH |
| `ci` | Configuración de CI/CD | PATCH |
| `chore` | Mantenimiento general | PATCH |

### Alcances comunes en LendusFind

| Alcance | Área |
|---------|------|
| `auth` | Autenticación, Sanctum, login |
| `kyc` | Validación de identidad, Nubarium |
| `notifications` | Plantillas, envío, canales |
| `onboarding` | Flujo de solicitud del aplicante |
| `admin` | Panel de administración |
| `tenant` | Multi-tenancy, branding, config |
| `api` | Endpoints, respuestas, middleware |
| `models` | Modelos Eloquent, relaciones |
| `ui` | Componentes visuales, Tailwind |
| `simulator` | Simulador de crédito |
| `docs` | Documentación del proyecto |
| `deps` | Dependencias (composer, npm) |

### Ejemplos concretos

```bash
# Funcionalidad nueva
feat(notifications): agregar modal de envío de prueba

# Corrección de bug
fix(simulator): corregir cálculo de tasa con IVA

# Con cuerpo explicativo (qué y por qué, no cómo)
feat(kyc): agregar validación biométrica de rostro

Se integra Nubarium Face Match para validar la identidad
del solicitante comparando selfie con foto de INE.
Requisito regulatorio de la CNBV para SOFOMES.

Closes #234

# Refactorización
refactor(models): extraer lógica de transición a service

# Dependencias
build(deps): actualizar laravel de 11 a 12

# Breaking change (con !)
feat(api)!: migrar respuestas a formato V2ApiResponse

BREAKING CHANGE: todos los endpoints retornan
{ success, data, message, error } en lugar del formato V1
```

### Co-autoría

Cuando Claude genera el commit, agregar al final:

```
Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>
```

## Pull Requests

### Título

Mismo formato que Conventional Commits, máximo 70 caracteres:

```
feat(notifications): agregar modal de envío de prueba
fix(simulator): corregir cálculo de tasa con IVA
```

### Descripción

```markdown
## Resumen
- <1-3 bullet points describiendo los cambios>

## Cambios principales
- `archivo1.vue`: descripción del cambio
- `archivo2.php`: descripción del cambio

## Cómo probar
1. Paso 1
2. Paso 2
3. Verificar resultado

## Test plan
- [ ] Tests unitarios pasan
- [ ] Type-check pasa (`npm run type-check`)
- [ ] Probado manualmente
```

### Reglas

- **Tamaño ideal**: 200-400 líneas cambiadas, máximo 500
- **Un propósito**: cada PR resuelve una sola cosa
- **Squash merge**: para mantener historial limpio en `main`
- **Al menos 1 revisión** antes de merge

## Tags y Versionado (SemVer)

### Formato

```
vMAJOR.MINOR.PATCH
```

| Componente | Cuándo incrementar |
|------------|-------------------|
| **MAJOR** | Cambio incompatible (breaking change) |
| **MINOR** | Nueva funcionalidad retrocompatible |
| **PATCH** | Corrección de bug retrocompatible |

### Comandos

```bash
# Crear tag anotado
git tag -a v1.2.0 -m "Release v1.2.0: agregar módulo de notificaciones"

# Push del tag
git push origin v1.2.0

# Listar tags
git tag -l "v1.*"

# Ver detalle
git show v1.2.0
```

### Pre-releases

```bash
v1.0.0-alpha.1    # Desarrollo temprano
v1.0.0-beta.1     # En pruebas
v1.0.0-rc.1       # Release candidate
v1.0.0             # Estable
```

Orden: `alpha` < `beta` < `rc` < estable

## Operaciones Git seguras

### Antes de hacer commit

```bash
# Ver estado actual
git status

# Ver cambios staged y unstaged
git diff
git diff --staged

# Agregar archivos específicos (NO usar git add -A ni git add .)
git add archivo1.ts archivo2.vue

# NUNCA agregar archivos sensibles
# .env, credentials.json, *.key, *.pem
```

### Formato del comando commit

```bash
git commit -m "$(cat <<'EOF'
feat(notifications): agregar plantillas HTML profesionales

Se reemplaza el helper inline por emailHtmlHelper.ts con diseño
de 6 secciones: barra acento, tenant header, hero, body, despedida
y footer. Incluye soporte para iconos, detalles y CTA.

Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>
EOF
)"
```

### Acciones prohibidas sin confirmación explícita

- `git push --force` (puede destruir trabajo de otros)
- `git reset --hard` (pierde cambios no commiteados)
- `git checkout .` / `git restore .` (descarta cambios locales)
- `git clean -f` (elimina archivos no rastreados)
- `git branch -D` (elimina branch sin merge)
- `git rebase` en branches compartidos
- `--no-verify` (salta hooks de validación)
- `--amend` en commits ya pusheados

### Resolver conflictos

1. Investigar el conflicto antes de actuar
2. Preferir `git merge` sobre `git rebase` para branches compartidos
3. Nunca descartar cambios de otros sin entender qué hacen
4. Si hay lock files, investigar qué proceso los usa antes de eliminar

## Errores comunes a evitar

1. **Commits gigantes**: dividir en commits atómicos, cada uno con un propósito
2. **Mensajes vagos**: "arreglos varios", "cambios", "wip" — siempre describir qué y por qué
3. **Commitear archivos sensibles**: verificar `git status` antes, usar `.gitignore`
4. **Branches de larga vida**: mergear frecuentemente, branches viven días no semanas
5. **Amend después de push**: crea divergencia de historial, usar commit nuevo
6. **Force push a main**: prohibido — puede destruir trabajo de todo el equipo
7. **Ignorar pre-commit hooks**: los hooks existen por una razón, corregir el problema en vez de saltarlos
8. **Commits sin contexto**: el diff dice el "cómo", el mensaje debe decir el "qué" y "por qué"
