# counter-offer-applicant

## REMOVED Requirements

### Requirement: Pantalla de oferta MoneyCapital con valores fijos
**Reason**: La pantalla de oferta ahora soporta dos modos — monto fijo (ofertas
manuales de staff) y rango ajustable (ofertas del motor de decisión, Regla 06 de la
Matriz MoneyCapital) — por lo que el requirement "con valores fijos" queda superado.
**Migration**: Reemplazado por "Pantalla de oferta MoneyCapital fija o con rango"
(abajo), que conserva íntegro el comportamiento de monto fijo y agrega el modo rango.

## ADDED Requirements

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
