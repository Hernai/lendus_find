# Tasks — contraoferta-admin-aceptacion

## 1. Backend — máquina de estados y modelo

- [x] 1.1 Migración: columna `approved_term_days` (unsignedSmallInteger nullable) en `applications`, espejo de `requested_term_days`
- [x] 1.2 Agregar `STATUS_COUNTER_OFFERED` a las constantes del modelo `Application` y al mapa `STATUS_TRANSITIONS` (entrada: IN_REVIEW, DOCS_PENDING; salida: APPROVED, CANCELLED)
- [x] 1.3 `Application::sendCounterOffer()`: transicionar a `COUNTER_OFFERED` (sin transición si ya está ahí — reenvío sobreescribe JSONB y renueva `expires_at`) y guardar snapshot completo (`amount`, `term_days` XOR `term_months`, `interest_rate` y `opening_commission` del producto, `reason`, `offered_by`, `offered_at`, `expires_at`, `responded_at: null`, `accepted: null`)
- [x] 1.4 `Application::respondToCounterOffer()`: validar vigencia (`expires_at`) y no-respondida; aceptar → transición a `APPROVED` + copia a `approved_amount`/`approved_term_months`/`approved_term_days`/`approved_interest_rate`; rechazar → transición a `CANCELLED` con razón de cliente

## 2. Backend — service, controllers y validación

- [x] 2.1 `Staff/ApplicationController::sendCounterOffer` + `ApplicationService::sendCounterOffer`: validar `term_days` XOR `term_months` coherente con `product.rules.term_in_days`, rangos de monto/plazo contra rules del producto, `expires_in_minutes` (default 30), permiso `canApproveRejectApplications`
- [x] 2.2 `Applicant/ApplicationController::respondToCounterOffer`: payload `{ accepted: boolean }`; 422 si expirada, ya respondida o estado ≠ `COUNTER_OFFERED`; verificar pertenencia (solicitante + tenant)
- [x] 2.3 Emitir `COUNTER_OFFER_ACCEPTED` / `COUNTER_OFFER_REJECTED` en `ApplicationService::respondToCounterOffer` (templates ya seedeados); verificar variables `counter_offer.*` disponibles
- [x] 2.4 Exponer `counter_offer` completo (con `expires_at`, `term_days`, `opening_commission`) en el recurso/respuesta del endpoint applicant de detalle de solicitud

## 3. Backend — expiración programada

- [x] 3.1 Comando `counter-offers:expire`: barrer `COUNTER_OFFERED` con `expires_at < now()` y `responded_at` null → transición a `CANCELLED` (razón "contraoferta expirada") + notificación `APPLICATION_CANCELLED`
- [x] 3.2 Registrar en `routes/console.php` con `everyMinute()->withoutOverlapping()->onOneServer()`
- [x] 3.3 Tests de feature: envío (días y meses), aceptación con copia de términos, rechazo → CANCELLED, expiración, doble respuesta, payload inválido, permisos

## 4. Frontend — admin

- [x] 4.1 `CounterOfferModal.vue` adaptivo: modo días (monto + plazo en días validado contra `min/max_term_days`, sin tasa/frecuencia) vs modo meses (actual); campo vigencia default 30 min en ambos
- [x] 4.2 `ApplicationDetailHeader.vue`: habilitar acción "Contraoferta" también en `COUNTER_OFFERED` (reenvío); mostrar oferta vigente (monto, plazo, vigencia restante, estado de respuesta) en el detalle
- [x] 4.3 Actualizar `V2CounterOfferCreatePayload` y `application.staff.service.ts` (term_days, expires_in_minutes)

## 5. Frontend — solicitante

- [x] 5.1 Corregir `V2CounterOfferResponsePayload` a `{ accepted: boolean }` y actualizar call sites: `LoanOfferView.respond()`, `DashboardView.acceptCounterOffer/rejectCounterOffer`
- [x] 5.2 `LoanOfferView.vue`: cargar snapshot real; valores fijos; agregar línea "Comisiones (IVA incluido)" con `opening_commission`; interés desde `interest_rate` del snapshot; textos de contraoferta (sin promesa de auto-recálculo); estado expirado; rechazo informa cancelación
- [x] 5.3 Sección de contraoferta en `ApplicationStatusView.vue` para productos en meses (monto, meses, tasa, motivo, aceptar/rechazar) + rama `COUNTER_OFFERED` en su stepper/statusConfig
- [x] 5.4 Redirect de entrada: con solicitud activa en `COUNTER_OFFERED` del tenant actual → pantalla de oferta (días → `m-loan-offer`, meses → vista de estado); tarjeta "Tienes una oferta" en `DashboardView`
- [x] 5.5 Verificación anti-cross-tenant del redirect (solicitud debe pertenecer al tenant actual)

## 6. Verificación end-to-end

- [x] 6.1 `npm run type-check` + `php artisan test --filter=CounterOffer` en verde
- [x] 6.2 Flujo manual MoneyCapital: admin envía contraoferta (días) → push/estado → solicitante aterriza en oferta → acepta → `APPROVED` con `approved_term_days`; repetir con rechazo → `CANCELLED`
- [x] 6.3 Flujo manual demo (meses): contraoferta desde admin → sección en vista de estado → aceptar/rechazar
- [x] 6.4 Expiración: enviar oferta con vigencia 1 min, verificar countdown, bloqueo client-side y auto-cancelación por el comando
