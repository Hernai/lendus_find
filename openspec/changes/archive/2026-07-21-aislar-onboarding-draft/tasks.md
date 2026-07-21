## 1. Sellar y validar el borrador (onboarding.ts)

- [x] 1.1 En `saveToStorage`, sellar el objeto guardado con `{ tenantId, userId }` (vía helper `getCurrentIdentity()`: tenantId desde `tenantStore.tenant?.id` con fallback a `STORAGE_KEYS.CURRENT_TENANT_ID`; userId desde `STORAGE_KEYS.CURRENT_USER_ID`).
- [x] 1.2 En `loadFromStorage`, restaurar SOLO si `tenantId` y `userId` coinciden. Si difieren o faltan → no restaurar, `removeItem`, **y resetear el estado reactivo en memoria** (`data`/`completedSteps`/`currentStep`) para no dejar PII del anterior en el store singleton.
- [x] 1.3 Watcher del tenant activo que llama `reset()` al cambiar de tenant en la misma sesión (cierra el caso same-session sin reload; guard `oldId && newId && oldId !== newId`).

## 2. Limpiar en cambio de usuario (auth.ts)

- [x] 2.1 Ya cubierto: `auth.ts::clearOnboardingCache()` (invocado en los 5 puntos de login con `previousUserId !== apiUser.id`) ya hace `localStorage.removeItem('onboarding_draft')` sin dependencia circular. No requirió cambio.

## 3. Verificación

- [x] 3.1 `npm run type-check` sin errores.
- [x] 3.2 `revisor-lendus`: cazó 1 bloqueante (el descarte no reseteaba el estado en memoria del store singleton) → corregido en el branch + watcher de tenant para el caso same-session. Sin dependencias circulares; sello guardado; sin falso match null==null.
- [ ] 3.3 PENDIENTE-SMOKE (opcional): con un borrador de usuario A en localStorage, iniciar sesión como B → el borrador no prefila y se limpia; navegar entre tenants en el mismo tab no arrastra PII.
