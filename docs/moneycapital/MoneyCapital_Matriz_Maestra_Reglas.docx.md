

**MoneyCapital**

**Matriz Maestra de Reglas de Negocio**  
**y Evaluación Funcional**

Documento de trabajo para revisión, evaluación y parametrización con LENDUS y equipo técnico

Versión de trabajo | Julio 2026

# 

# **Propósito del documento**

Este documento consolida las reglas de negocio y los puntos funcionales que **MoneyCapital** requiere revisar con **LENDUS** y el equipo técnico antes de definir la parametrización final del sistema.

No representa una configuración definitiva ni una instrucción cerrada de programación. Su función es servir como base de trabajo Para que cada equipo revise, proponga ajustes y acuerde la regla final.

La información se organizó de forma práctica: cada regla explica el objetivo operativo, el criterio base de **MoneyCapital** y los puntos que deben definirse con **LENDUS.**

1. # **Onboarding y control inicial**

| Regla 01 | Control de solicitudes y prevención de duplicidades |
| :---- | :---- |

**Etapa del flujo:** Onboarding \- Registro e inicio de solicitud

| Objetivo operativo | Definir los controles para evitar que un mismo cliente genere múltiples solicitudes innecesarias, vuelva a insistir después de un rechazo sin cumplir condiciones o mantenga más de un crédito activo. |
| :---- | :---- |
| **Criterio base MoneyCapital** | MoneyCapital requiere permitir una sola solicitud activa o un solo crédito vigente por cliente. Si existe rechazo, solicitud en proceso, revisión pendiente, oferta vigente o crédito activo, el sistema debe reconocerlo antes de consumir nuevas validaciones o generar otra operación. |
| **Puntos a definir con LENDUS y equipo** | Identificadores para reconocer al cliente: teléfono, CURP, INE, CLABE u otros. Estados que bloquean una nueva solicitud: rechazada, en proceso, pendiente, aprobada, oferta vigente o crédito activo. Tiempo o condición para permitir una nueva solicitud después de rechazo. Diferencia entre rechazo real, falla técnica o proceso incompleto. Casos que soporte podrá desbloquear de manera controlada. |
| **Parámetro final acordado** | Los dos identificadores únicos son: CURP, INE.Estados que bloquean una nueva solicitud: rechazada (cuenta días de la conf),en proceso, crédito activoTiempo o condición para permitir una nueva solicitud después de rechazo: (Configurable por sistema en días) |
| **Observaciones / Pendientes** |   |

| Regla 02 | Tiempo de respuesta y estatus de solicitud |
| :---- | :---- |

**Etapa del flujo:** Onboarding \- Revisión de solicitud y resultado

| Objetivo operativo | Definir el tiempo estimado de respuesta al cliente después de completar el onboarding y los estatus que verá durante el proceso. |
| :---- | :---- |
| **Criterio base MoneyCapital** | MoneyCapital busca una respuesta rápida, idealmente en segundos o pocos minutos, con mensajes claros: aprobado, rechazado, pendiente de revisión o detenido por validación incompleta/falla técnica. |
| **Puntos a definir con LENDUS y equipo** | Tiempo promedio de respuesta que maneja LENDUS. Procesos automáticos versus casos que requieren revisión manual. Estatus visibles para el cliente en app/web y para el equipo interno. Manejo de demoras o fallas de proveedores externos. Mensaje final para aprobación, rechazo o revisión pendiente. |
| **Parámetro final acordado** | Estatus visibles: rechazado, en proceso, activo |
| **Observaciones / Pendientes** |   |

| Regla 03 | Automatización y horario operativo |
| :---- | :---- |

**Etapa del flujo:** Operación general \- Solicitud, aprobación y desembolso

| Objetivo operativo | Definir qué procesos funcionarán 24/7 y cuáles quedarán sujetos al horario operativo del equipo de MoneyCapital. |
| :---- | :---- |
| **Criterio base MoneyCapital** | El sistema debe operar con el mayor nivel de automatización posible, permitiendo solicitudes fuera de horario. Los casos claros deberían avanzar sin intervención humana; los casos de riesgo o validación pendiente deben quedar en una bandeja para revisión. |
| **Puntos a definir con LENDUS y equipo** | Procesos 100% automáticos fuera de horario. Condiciones para aprobación y desembolso automático. Casos que pasan a revisión manual. Estatus que verá el cliente fuera de horario. Bandeja o alertas para el equipo al iniciar jornada. Límites por monto, horario o riesgo. |
| **Parámetro final acordado** |  |
| **Observaciones / Pendientes** |   |

# 

2. # **Oferta, aprobación y renovación**

| Regla 04 | Oferta aprobada y vigencia |
| :---- | :---- |

**Etapa del flujo:** Resultado de solicitud / Oferta preaprobada / Renovación

| Objetivo operativo | Definir por cuánto tiempo permanecerá disponible una oferta aprobada para que el cliente pueda aceptarla desde su panel. |
| :---- | :---- |
| **Criterio base MoneyCapital** | Toda oferta aprobada, tanto de crédito nuevo como de renovación, debe tener una vigencia definida. El cliente puede no aceptar de inmediato, pero la oferta no debe quedar disponible indefinidamente. |
| **Puntos a definir con LENDUS y equipo** | Vigencia recomendada: 24 horas, 3 días, 7 días, 15 días, 30 días u otro plazo. Si la vigencia será igual para nuevos y renovaciones. Cómo verá el cliente el vencimiento de la oferta. Qué ocurre si intenta aceptar una oferta vencida. Si se requiere reevaluación al vencer. Recordatorios antes del vencimiento. |
| **Parámetro final acordado** |  |
| **Observaciones / Pendientes** |  |

| Regla 05 | Renovación automática después de liquidar |
| :---- | :---- |

**Etapa del flujo:** Pago liquidado / Renovación / Nueva oferta

| Objetivo operativo | Permitir que, una vez confirmado el pago del crédito por un canal habilitado, el sistema cierre el crédito anterior y evalúe automáticamente la generación de una nueva oferta en minutos. |
| :---- | :---- |
| **Criterio base MoneyCapital** | Después de liquidar, el cliente debe poder recibir una nueva oferta de forma automática, sin repetir todo el onboarding inicial, reutilizando la información vigente que el sistema permita validar. |
| **Puntos a definir con LENDUS y equipo** | Confirmación del pago desde Openpay, STP/SPEI  Tiempo de actualización del crédito a estado liquidado. Condiciones para generar la nueva oferta automática. Información del cliente que puede reutilizarse en la renovación. Generación de contrato, carátula y tabla para el nuevo crédito. Desembolso después de la aceptación y firma de la nueva oferta. |
| **Parámetro final acordado** |  |
| **Observaciones / Pendientes** |  |

| Regla 06 | Simulador por cupo y plazo autorizado |
| :---- | :---- |

**Etapa del flujo:** Oferta aprobada / Renovación / Aceptación de crédito

| Objetivo operativo | Permitir que toda oferta de crédito (préstamo nuevo o renovación) se presente mediante un simulador interactivo, donde el cliente pueda ajustar el monto y el plazo dentro del rango autorizado, visualizando en tiempo real las condiciones antes de aceptar la oferta. |
| :---- | :---- |
| **Criterio base MoneyCapital** | Cada vez que el sistema genere una oferta de crédito, deberá mostrar automáticamente un simulador con el rango de monto y plazo previamente autorizados para ese cliente. El simulador permitirá que el cliente seleccione libremente el monto que desea solicitar, desde el monto mínimo permitido hasta el monto máximo aprobado, así como el plazo disponible desde el mínimo establecido hasta el plazo máximo autorizado para esa oferta. Al modificar el monto o el plazo, el sistema deberá recalcular automáticamente el interés, comisiones, IVA, CAT informativo, total a pagar y fecha límite de pago, mostrando siempre la información actualizada antes de la aceptación. Una vez que el cliente confirme la oferta seleccionada, el sistema generará el contrato y la carátula correspondientes con las condiciones finales elegidas para proceder con el desembolso. Durante la prueba piloto, los créditos iniciales serán desde $300 hasta $1,000 MXN, con un plazo único de 7 días. A partir de cada renovación, el sistema podrá incrementar gradualmente el monto y el plazo autorizado conforme al historial de pago del cliente. El simulador siempre deberá mostrar el rango disponible para esa oferta, iniciando desde 7 días hasta el plazo máximo autorizado en ese momento (10, 15, 20, 25 o 30 días). De la misma forma, el rango de monto se actualizará conforme al cupo autorizado para cada renovación, permitiendo al cliente seleccionar únicamente un monto dentro del intervalo disponible. El objetivo es que el cliente adapte el crédito a su capacidad de pago, seleccionando el monto y plazo que mejor se ajusten a sus necesidades, manteniendo siempre las condiciones autorizadas por el motor de decisión. Como apoyo visual, MoneyCapital compartió el flujo de onboarding donde se muestra la pantalla del simulador, para que LENDUS y el equipo tengan una referencia clara del funcionamiento esperado. |
| **Puntos a definir con LENDUS y equipo** | Configuración del simulador para cada nueva oferta y cada renovación. Definición de rangos dinámicos de monto y plazo autorizados por el motor de decisión. Recalculo automático de interés, comisiones, IVA, CAT, total a pagar y fecha límite conforme a la selección del cliente. Generación automática del contrato y carátula con las condiciones finales elegidas. |
| **Parámetro final acordado** |  |
| **Observaciones / Pendientes** |   |

| Regla 07 | Aceptación de renovación: OTP o aceptación directa |
| :---- | :---- |

**Etapa del flujo:** Renovación / Simulador / Aceptación de oferta

| Objetivo operativo | Definir el mecanismo de confirmación antes del desembolso cuando el cliente seleccione Recibir mi préstamo ahora |
| :---- | :---- |
| **Criterio base MoneyCapital** | Después de revisar el simulador, el contrato y las condiciones del crédito, el cliente confirmará la operación seleccionando **Recibir mi préstamo ahora**. En este punto se evaluará si la confirmación final requiere un código OTP o si la aceptación directa será suficiente para autorizar el desembolso a la cuenta bancaria registrada. |
| **Puntos a definir con LENDUS y equipo** | ¿La confirmación mediante Recibir mi préstamo ahora requiere OTP o aceptación directa? ¿Cómo quedará registrada la aceptación del cliente? ¿Cuál es la mejor alternativa para mantener la seguridad sin afectar la rapidez de las renovaciones? |
| **Parámetro final acordado** |  |
| **Observaciones / pendientes** |   |

| Regla 08 | Contrato digital y carátula visible |
| :---- | :---- |

**Etapa del flujo:** Panel del cliente / Crédito activo / Historial

| Objetivo operativo | Garantizar que el cliente pueda consultar el contrato digital y la carátula del crédito aceptado en cualquier momento. |
| :---- | :---- |
| **Criterio base MoneyCapital** | El panel del cliente en app y web debe contar con un botón visible de “Ver contrato”, donde se consulte el documento firmado, la carátula y las condiciones aceptadas: monto, plazo, comisiones, intereses, total a pagar y fecha límite. |
| **Puntos a definir con LENDUS y equipo** |  Contrato y carátula por cada crédito o renovación. Formato de visualización o descarga. Evidencia de firma o aceptación digital. |
| **Parámetro final acordado** |  |
| **Observaciones / pendientes** |   |

# 

# 

# 

3. # **Crédito activo, pagos y mora**

| Regla 09 | Pago o liquidación anticipada |
| :---- | :---- |

**Etapa del flujo:** Crédito activo / Pago / Liquidación

| Objetivo operativo | Definir que el sistema calcule diariamente el saldo de liquidación del crédito, permitiendo que el cliente pueda pagar antes de la fecha final y cubrir únicamente los días reales de uso del préstamo. |
| :---- | :---- |
| **Criterio base MoneyCapital** | MoneyCapital maneja una tasa diaria. Aunque el crédito se otorgue con un plazo determinado, el cliente podrá liquidarlo en cualquier momento antes de la fecha de vencimiento y el sistema deberá recalcular automáticamente el saldo conforme a los días efectivamente utilizados. Si el cliente liquida el mismo día del desembolso, únicamente pagará el capital más las comisiones aplicables, sin generar interés ordinario diario. A partir del día siguiente, el sistema deberá actualizar diariamente el saldo de liquidación, incorporando únicamente el interés ordinario correspondiente a cada día transcurrido, hasta alcanzar el monto total pactado en la fecha de vencimiento. El cliente siempre deberá visualizar en su panel el saldo actualizado de liquidación, para conocer el valor exacto a pagar si decide cancelar su crédito de manera anticipada. |
| **Puntos a definir con LENDUS y equipo** | Configuración del cálculo diario del saldo de liquidación. Tratamiento de la liquidación el mismo día del desembolso. Actualización automática del saldo visible para el cliente. Aplicación de capital, comisiones e interés diario hasta la fecha de pago. Cierre automático del crédito al recibir el pago total calculado. |
| **Parámetro final acordado** |  |
| **Observaciones / Pendientes** |   |

| Regla 10 | Notificaciones, campañas y llamadas de seguimiento |
| :---- | :---- |

**Etapa del flujo:** Crédito activo / Comunicación / Panel del cliente

| Objetivo operativo | Definir la configuración de campañas y disparos automáticos de notificaciones para acompañar al cliente durante todo el ciclo del crédito. |
| :---- | :---- |
| **Criterio base MoneyCapital** | MoneyCapital utilizará diferentes canales de comunicación como SMS, WhatsApp, notificaciones push de la app y otros servicios que se definan, para informar al cliente sobre eventos importantes del crédito. Estas notificaciones podrán aplicarse para créditos aprobados, desembolsos, recordatorios de pago, vencimientos próximos, mora, acuerdos de pago, campañas comerciales y demás comunicaciones operativas. El objetivo es automatizar los mensajes según el estado del crédito y los momentos clave del proceso, evitando gestiones manuales y mejorando el seguimiento al cliente. |
| **Puntos a definir con LENDUS y equipo** | Canales disponibles para notificaciones: SMS, WhatsApp, push y llamadas telefónicas. Momentos del flujo donde se dispararán mensajes automáticos. Recordatorios antes del vencimiento, el día de pago y en mora. Integración con proveedor externo de mensajería. Configuración de campañas operativas y comerciales. |
| **Parámetro final acordado** |  |
| **Observaciones / pendientes** |   |

| Regla 11 | Extensión o prórroga del crédito |
| :---- | :---- |

**Etapa del flujo:** Crédito activo / Fecha de vencimiento / Alternativa de pago

| Objetivo operativo | Permitir que el cliente solicite desde la app o la web una extensión de 7 o 15 días cuando no pueda liquidar el crédito en la fecha de vencimiento. |
| :---- | :---- |
| **Criterio base MoneyCapital** | El cliente podrá seleccionar la prórroga disponible y pagar la comisión correspondiente. Una vez confirmado el pago, el sistema deberá actualizar automáticamente la nueva fecha de vencimiento, manteniendo el mismo saldo pendiente del crédito. El objetivo es ofrecer una alternativa antes de que el cliente caiga en mora y facilitar la regularización del crédito. |
| **Puntos a definir con LENDUS y equipo** | Configuración de prórrogas de 7 y 15 días. Comisión aplicable para cada opción. Actualización automática de la fecha después del pago. Canal de pago para cubrir la comisión. Número máximo de prórrogas permitidas por crédito. Condiciones para habilitar o bloquear nuevas prórrogas. |
| **Parámetro final acordado** |  |
| **Observaciones / pendientes** |   |

| Regla 12 | Vencimiento, mora diaria y límite de recargos |
| :---- | :---- |

**Etapa del flujo:** Crédito vencido / Mora / Cobranza

| Objetivo operativo | Definir cuándo inicia la penalización diaria por mora y cuál será el límite máximo de acumulación. |
| :---- | :---- |
| **Criterio base MoneyCapital** | Si el cliente no liquida en la fecha de vencimiento, la mora diaria se aplicará a partir del día siguiente conforme a la fórmula acordada. La prioridad es definir el número máximo de días o el límite hasta el cual se acumularán recargos, evitando cargos indefinidos en créditos de corto plazo. |
| **Puntos a definir con LENDUS y equipo** | Día exacto de inicio de mora. Formula de penalización diaria. Base de cálculo: capital, saldo vencido u otro criterio. Límite máximo de acumulación: 90, 120 días u otro. Qué ocurre cuando se alcanza el límite. Estatus posterior: cobranza avanzada, jurídico o gestión especial. Visualización del saldo vencido para cliente y equipo interno. |
| **Parámetro final acordado** |  |
| **Observaciones / Pendientes** |   |

# **IV. Cobranza y recuperación**

| Regla 13 | Activación de visita domiciliaria y cargo por gestión |
| :---- | :---- |

**Etapa del flujo:** Crédito vencido / Mora / Cobranza domiciliaria

| Objetivo operativo | Definir en qué momento se activará la visita domiciliaria para los créditos en mora y cómo se aplicará el cargo por gestión de cobranza. |
| :---- | :---- |
| **Criterio base MoneyCapital** | Cuando el cliente incurra en mora y su cuenta sea asignada a visita domiciliaria, se aplicará un cargo de gestión de $250 más IVA, adicional al saldo pendiente y a los conceptos correspondientes. Durante la prueba piloto en Culiacán, MoneyCapital contará con gestores de cobranza para realizar las visitas y recaudar pagos en efectivo mediante LENDUS Mobile, donde se llevará el seguimiento y registro de cada gestión. El punto principal por definir es si la visita domiciliaria se activa desde el primer día de mora o a partir del tercer día, otorgando dos días previos para gestión remota y organización operativa. |
| **Puntos a definir con LENDUS y equipo** |  Día de activación de la visita domiciliaria.   Momento en que se aplica el cargo de $250 más IVA.   Asignación automática de cuentas a los gestores.   Operación y registro de visitas mediante LENDUS Mobile y GPS.   Recaudo en efectivo y actualización del pago en el sistema.  |
| **Parámetro final acordado** |  |
| **Observaciones / Pendientes** |  |

| Regla 14 | Canales de pago y recaudo por gestor |
| :---- | :---- |

**Etapa del flujo:** Pago / Cobranza domiciliaria / LENDUS Mobile

| Objetivo operativo | Definir los canales de pago del cliente y el manejo del recaudo en efectivo cuando el crédito pase a gestión domiciliaria. |
| :---- | :---- |
| **Criterio base MoneyCapital** | Durante la etapa normal, el cliente pagará por Openpay o SPEI/STP desde app/web. Cuando el crédito sea asignado a gestor, se habilitará el recaudo en efectivo con registro en LENDUS Mobile, folio y comprobante digital. |
| **Puntos a definir con LENDUS y equipo** | Visualización de canales de pago en app/web. Generación de referencias de pago. Asignación de créditos vencidos a gestor. Registro de pago en efectivo. Folio y comprobante digital. Confirmación al cliente. Conciliación de efectivo recibido. GPS en Lendus Mobile para localización de clientes  Controles de auditoría por gestor. |
| **Parámetro final acordado** |  |
| **Observaciones / pendientes** |   |

| Regla 15 | Referencia personal y familiar |
| :---- | :---- |

**Etapa del flujo:** Onboarding / Cobranza / Localización

| Objetivo operativo | Definir cómo se capturan, validan y utilizan las referencias del cliente. |
| :---- | :---- |
| **Criterio base MoneyCapital** | Durante el onboarding se solicitará una referencia personal y una familiar. MoneyCapital debe definir si se validan al inicio o si se contactan principalmente cuando el cliente no paga, no contesta o se requiere apoyo de localización. |
| **Puntos a definir con LENDUS y equipo** | Datos obligatorios de cada referencia. Validación al inicio o activación en mora. Momento permitido para contactar referencias. Registro del resultado de llamada. Selección desde contactos del teléfono del cliente. Controles para evitar referencias falsas. |
| **Parámetro final acordado** |  |
| **Observaciones / pendientes** |   |

# 

# **V. Desembolso, datos y beneficios**

| Regla 16 | Dispersión por STP |
| :---- | :---- |

**Etapa del flujo:** Oferta aceptada / Firma / Desembolso

| Objetivo operativo | Definir el esquema de dispersión de los créditos mediante STP, considerando un flujo automático, manual o híbrido según el horario operativo y las condiciones de cada solicitud. |
| :---- | :---- |
| **Criterio base MoneyCapital** | El objetivo de MoneyCapital es que las dispersiones se realicen principalmente de forma automática mediante STP, una vez que el cliente haya completado correctamente todo el proceso de aceptación y validación. Durante el horario operativo podrán existir casos que requieran revisión o autorización manual. Fuera de ese horario, el sistema deberá evaluar si el crédito cumple con todos los parámetros necesarios para realizar el desembolso automático sin intervención del personal. |
| **Puntos a definir con LENDUS y equipo** | Esquema de dispersión automática, manual o híbrida. Horarios y condiciones para cada modalidad. Casos que requieren autorización manual. Reglas para dispersión automática fuera del horario operativo. Confirmación y registro del desembolso realizado por STP.   |
| **Parámetro final acordado** |  |
| **Observaciones / pendientes** |   |

| Regla 17 | Bancos o instituciones aceptadas para desembolso |
| :---- | :---- |

**Etapa del flujo:** Cuenta bancaria / Validación CLABE / Desembolso

| Objetivo operativo | Definir qué bancos e instituciones financieras serán aceptados para el desembolso, asegurando que la transferencia se refleje de forma inmediata en la cuenta del cliente. |
| :---- | :---- |
| **Criterio base MoneyCapital** | MoneyCapital deberá aceptar únicamente cuentas bancarias habilitadas para recibir transferencias en línea y con acreditación inmediata. El objetivo es evitar que el crédito comience a generar intereses antes de que el cliente reciba efectivamente el dinero. Cuando una institución presente demoras frecuentes de 24 a 48 horas o más, deberá evaluarse su restricción dentro del onboarding para evitar riesgos operativos, reclamaciones y posibles contingencias legales. |
| **Puntos a definir con LENDUS y equipo** | Bancos e instituciones aceptadas y restringidas. Validación de la cuenta bancaria durante el onboarding. Confirmación de acreditación inmediata antes de iniciar el cálculo del crédito. Mensaje al cliente cuando su institución no sea compatible. Procedimiento para casos de dispersión retrasada o rechazada. |
| **Parámetro final acordado** |  |
| **Observaciones / pendientes** |   |

| Regla 18 | Actualización de datos del cliente |
| :---- | :---- |

**Etapa del flujo:** Perfil del cliente / App / Web / Soporte

| Objetivo operativo | Permitir que el cliente actualice desde su panel, en la app o en la web, su número telefónico y su cuenta CLABE registrada, manteniendo un proceso sencillo y seguro. |
| :---- | :---- |
| **Criterio base MoneyCapital** | El cliente podrá solicitar directamente la actualización de su número celular o cuenta CLABE. Antes de aplicar el cambio, el sistema deberá validar que la solicitud corresponde al titular registrado. Los cambios de domicilio u otros datos sensibles deberán gestionarse mediante soporte interno de MoneyCapital. |
| **Puntos a definir con LENDUS y equipo** | Proceso para actualizar el número telefónico cuando este funciona como usuario de acceso. Validación de identidad antes de cambiar el celular o la cuenta CLABE. Registro y verificación de una nueva cuenta bancaria a nombre del cliente. Confirmación del cambio y conservación del historial del cliente. Datos que podrán modificarse directamente y cuáles requerirán soporte interno. |
| **Parámetro final acordado** |  |
| **Observaciones / pendientes** |   |

| Regla 19 | Incentivos, recompensas y nuevas reglas operativas |
| :---- | :---- |

**Etapa del flujo:** Historial del cliente / Renovaciones / Fidelización

| Objetivo operativo | Evaluar cómo aplicar el módulo de incentivos o recompensas de LENDUS al modelo de MoneyCapital y dejar espacio para reglas futuras. |
| :---- | :---- |
| **Criterio base MoneyCapital** | MoneyCapital busca motivar el buen comportamiento de pago, renovaciones responsables y fidelización del cliente. También se reconoce que durante la implementación podrán surgir nuevas reglas que deberán documentarse y aprobarse. |
| **Puntos a definir con LENDUS y equipo** | Funcionamiento actual del módulo de recompensas de LENDUS. Incentivos configurables para MoneyCapital. Criterios: pago puntual, liquidación anticipada, renovaciones o historial positivo. Visualización de beneficios en app/web. Condiciones para perder o suspender beneficios. Proceso para documentar reglas futuras. Responsables de aprobar cambios posteriores. |
| **Parámetro final acordado** |  |
| **Observaciones / pendientes** |   |

| Regla 20 | Regla de aumento progresivo de cupo en renovaciones |
| :---- | :---- |

**Etapa del flujo: Historial del cliente / Renovaciones / Motor de decisión.**

| Objetivo operativo | Definir las reglas para incrementar gradualmente el cupo de crédito del cliente durante las renovaciones, considerando su comportamiento y cumplimiento de pago. |
| :---- | :---- |
| **Criterio base MoneyCapital** | Durante la prueba piloto, MoneyCapital iniciará con créditos de $300 a $1,000 según el perfil del cliente. El incremento del cupo no será automático únicamente por liquidar un crédito; cada nueva oferta deberá evaluarse con base en el comportamiento de pago, historial y políticas definidas por MoneyCapital. El propósito es construir un crecimiento gradual y responsable del cupo autorizado. |
| **Puntos a definir con LENDUS y equipo** | • Política de incremento por renovación. • Monto de aumento según el crédito anterior. • Incremento por monto fijo, porcentaje o niveles. • Número mínimo de créditos liquidados. • Condiciones para aumentar, mantener o reducir el cupo. • Tratamiento para clientes con mora o prórrogas. • Relación entre aumento de monto y plazo. • Generación automática de la nueva contraoferta. |
| **Parámetro final acordado** |  |
| **Observaciones / pendientes** |   |

| Regla 21 | Phone Risk Score (Score de teléfono) |
| :---- | :---- |

**Etapa del flujo: Registro inicial / Validación de número telefónico**

| Objetivo operativo | Definir cómo se utilizará el Phone Risk Score como uno de los primeros filtros de validación durante el proceso de onboarding, con el propósito de reducir solicitudes fraudulentas, optimizar los costos transaccionales y fortalecer el proceso de evaluación desde el inicio. |
| :---- | :---- |
| **Criterio base MoneyCapital** | Una vez que el cliente registre su número telefónico y complete la validación correspondiente, el sistema deberá consultar el Phone Risk Score como parte del primer análisis de riesgo. El resultado permitirá determinar si la solicitud continúa con el proceso normal, requiere validaciones adicionales o debe detenerse antes de consumir otros servicios de validación. El objetivo es utilizar el Phone Risk Score como un filtro preventivo para disminuir el riesgo de fraude y optimizar el consumo de validaciones posteriores. De acuerdo con la documentación del proveedor Nubarium, el servicio asigna un puntaje de riesgo y una recomendación de Allow, Flag o Block, además de proporcionar indicadores que ayudan a evaluar el comportamiento del número telefónico. |
| **Puntos a definir con LENDUS y equipo** | Momento en que se realizará la consulta del Phone Risk Score dentro del onboarding. Definir la política de aceptación, revisión o rechazo según el resultado del score. Acciones automáticas que ejecutará el sistema para cada nivel de riesgo. Tratamiento para solicitudes que requieran validación manual. Registro del score dentro del expediente del cliente. Integración del resultado con el motor de decisión. Definir si el Phone Risk Score será un requisito obligatorio para continuar con el proceso. |
| **Parámetro final acordado** |  |
| **Observaciones / pendientes** |   |

**CONTROL DE CAMBIOS Y REGLAS FUTURAS**

La presente matriz constituye una base inicial de trabajo y no limita las reglas o configuraciones que puedan surgir durante la implementación. Cualquier nueva necesidad, condición o ajuste identificado en el proceso será evaluado e incorporado de manera conjunta por **MoneyCapital**, **LENDUS** con el propósito de adaptar progresivamente el sistema a la operación definida.