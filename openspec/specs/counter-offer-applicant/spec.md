# counter-offer-applicant

## Requirements

### Requirement: Pantalla de oferta MoneyCapital fija o con rango
Para productos en días, `LoanOfferView` (`/m/solicitud/:id/oferta`) DEBE (MUST) mostrar la
oferta real desde el snapshot `counter_offer` en el modo que corresponda:

- **Monto fijo** (sin `max_amount` en el snapshot): sliders bloqueados en el valor
  ofertado, comportamiento actual íntegro.
- **Rango** (con `max_amount`): sliders habilitados dentro de
  `[min_amount → max_amount]` y `[min_term_days → max_term_days]`, iniciando en la
  pre-selección del snapshot, con recálculo en vivo de interés, comisiones (IVA
  incluido), total a pagar y fecha límite al mover cualquier slider.

En ambos modos DEBE mostrar cuenta regresiva hasta `expires_at`, checkbox de contrato,
botón principal "Recibir mi préstamo ahora" y enlace discreto de rechazo. Al aceptar en
modo rango, DEBE enviar el monto y plazo elegidos.

#### Scenario: Carga de contraoferta fija vigente
- **WHEN** el solicitante con contraoferta manual de $700 a 10 días abre la pantalla
- **THEN** ve $700 y 10 días fijos, interés, comisiones (13% + IVA), total y fecha límite, con el countdown corriendo

#### Scenario: Oferta de rango ajustable
- **WHEN** el solicitante abre una oferta del motor con rango $300-$400 × 7 días pre-seleccionada en $400
- **THEN** puede deslizar el monto entre $300 y $400 viendo el recálculo en vivo, con el plazo fijo en 7 días

#### Scenario: Aceptar con monto elegido
- **WHEN** el solicitante elige $350, marca el contrato y pulsa "Recibir mi préstamo ahora"
- **THEN** se envía `{ accepted: true, amount: 350, term_days: 7 }`, se muestra confirmación y navega a la pantalla de procesamiento

#### Scenario: Rechazar
- **WHEN** el solicitante pulsa el enlace de rechazo
- **THEN** se envía `{ accepted: false }` y se le informa que su solicitud quedó cancelada

#### Scenario: Oferta expirada en pantalla
- **WHEN** el countdown llega a cero
- **THEN** el botón de aceptar se deshabilita y se muestra "Oferta expirada"

#### Scenario: Sin contraoferta
- **WHEN** el solicitante navega a la pantalla y su solicitud no tiene contraoferta vigente
- **THEN** se le informa y se le redirige a su pantalla de estado

### Requirement: Sección de contraoferta para tenants en meses
Para productos en meses, `ApplicationStatusView` DEBE (MUST) mostrar una sección de
contraoferta cuando la solicitud esté en `COUNTER_OFFERED`: monto, plazo en meses, tasa,
motivo y acciones de aceptar/rechazar con el mismo contrato de API.

#### Scenario: Contraoferta en tenant demo
- **WHEN** un solicitante de un tenant en meses abre su vista de estado con solicitud en `COUNTER_OFFERED`
- **THEN** ve la sección de contraoferta con monto, meses y tasa, y puede aceptar o rechazar

### Requirement: Redirect automático como pantalla principal
Al entrar autenticado a la app (login, dashboard u onboarding), si la solicitud activa
del solicitante **en el tenant actual** está en `COUNTER_OFFERED`, el sistema DEBE (MUST)
redirigirlo a la pantalla de oferta correspondiente (días → `LoanOfferView`; meses →
vista de estado). La navegación de salida DEBE permanecer libre y el dashboard DEBE
mostrar una tarjeta destacada para volver a la oferta.

#### Scenario: Entrada con oferta activa (MoneyCapital)
- **WHEN** el solicitante de MoneyCapital abre la app con su solicitud en `COUNTER_OFFERED`
- **THEN** aterriza en `/m/solicitud/:id/oferta` sin pasos intermedios

#### Scenario: Salida libre y regreso
- **WHEN** el solicitante navega de la oferta al dashboard
- **THEN** puede salir sin responder y el dashboard muestra la tarjeta "Tienes una oferta" que lo regresa

#### Scenario: Solicitud de otro tenant no redirige
- **WHEN** el solicitante tiene en localStorage una solicitud `COUNTER_OFFERED` de otro tenant (origen compartido)
- **THEN** no se redirige a la oferta de ese otro tenant

### Requirement: Respuesta estandarizada en accepted para todos los tenants
Todos los puntos del frontend que responden contraofertas (LoanOfferView, dashboard,
vista de estado) DEBEN (MUST) enviar `{ accepted: boolean }` conforme al tipo
`V2CounterOfferResponsePayload` corregido.

#### Scenario: Botones existentes del dashboard reparados
- **WHEN** un solicitante de demo/finatea acepta desde la tarjeta del dashboard
- **THEN** el backend recibe `accepted: true` y procesa la aceptación (hoy falla por enviar `accept`)
