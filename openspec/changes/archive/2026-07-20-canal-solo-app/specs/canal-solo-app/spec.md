## ADDED Requirements

### Requirement: Flag de canal exclusivo por tenant
El tenant DEBE (MUST) tener un feature flag `app_only_channel` que, cuando está activo, restringe la solicitud de crédito a la app móvil. Por defecto DEBE estar apagado (`false`), preservando el acceso web.

#### Scenario: Flag apagado preserva el acceso web
- **WHEN** `features.app_only_channel` es `false` (o ausente)
- **THEN** la solicitud/onboarding funciona en web y en app como hasta ahora

#### Scenario: Seeder de MoneyCapital
- **WHEN** se siembra el tenant MoneyCapital
- **THEN** `features.app_only_channel` queda en `false`

### Requirement: Bloqueo de la solicitud web cuando el flag está activo
Cuando `app_only_channel` está activo y el acceso NO es nativo, las rutas de solicitud (auth/onboarding) NO DEBEN (MUST NOT) ser accesibles desde el navegador; DEBEN redirigir a la landing informativa del tenant.

#### Scenario: Onboarding web bloqueado con flag activo
- **WHEN** el flag está activo y un usuario web (no nativo) navega a una ruta de solicitud (auth u onboarding)
- **THEN** es redirigido a la landing informativa del tenant (que ofrece la descarga de la app)

#### Scenario: App nativa no se afecta
- **WHEN** el flag está activo y el acceso es la app nativa
- **THEN** la solicitud/onboarding funciona normalmente (el bloqueo solo aplica a web)

### Requirement: Web informativa con descarga y simulador
La landing web DEBE (MUST) ofrecer la descarga de la app (App Store y Google Play) y DEBE conservar el simulador como herramienta informativa.

#### Scenario: Landing con descarga
- **WHEN** un usuario visita la landing de MoneyCapital en web
- **THEN** ve botones de descarga a App Store y Google Play

#### Scenario: Simulador disponible en web
- **WHEN** un usuario usa el simulador en web
- **THEN** puede calcular su crédito; para solicitarlo se le indica descargar la app
