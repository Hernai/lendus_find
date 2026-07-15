<?php

namespace App\Services;

use App\Enums\DecisionOutcome;
use App\Enums\NotificationEvent;
use App\Jobs\DecideApplicationJob;
use App\Models\ApplicantAccount;
use App\Models\Application;
use App\Models\DecisionPolicy;
use App\Models\Loan;
use App\Models\Person;
use App\Models\Product;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApplicationService
{
    // =====================================================
    // Constructor
    // =====================================================

    public function __construct(
        protected LoanCalculationService $loanCalculator,
        protected DocumentService $documentService,
        protected NotificationService $notificationService,
        protected LoanService $loanService
    ) {}

    // =====================================================
    // Application Creation
    // =====================================================

    /**
     * Create a new application for an individual (Person).
     */
    public function createForPerson(
        Tenant $tenant,
        Person $person,
        Product $product,
        array $loanData,
        ?ApplicantAccount $submittedBy = null
    ): Application {
        return $this->create(
            $tenant,
            $product,
            Application::TYPE_INDIVIDUAL,
            $person->id,
            $loanData,
            $submittedBy
        );
    }

    /**
     * Create a new application.
     */
    protected function create(
        Tenant $tenant,
        Product $product,
        string $applicantType,
        ?string $personId,
        array $loanData,
        ?ApplicantAccount $submittedBy = null
    ): Application {
        // Plazo en días: explícito o derivado de rules.default_term_days si el
        // producto se mide en días (BULLET / MoneyCapital).
        $rules = $product->rules ?? [];
        $termDays = $loanData['term_days'] ?? null;
        if ($termDays === null && !empty($rules['term_in_days'])) {
            $termDays = $rules['default_term_days'] ?? $rules['min_term_days'] ?? null;
        }

        // Calculate loan terms (pasa term_days para cálculo BULLET correcto)
        $calculation = $this->loanCalculator->calculateSimulation(
            $loanData['amount'],
            $loanData['term_months'],
            $loanData['frequency'] ?? 'MONTHLY',
            $product->annual_rate,
            $product->opening_commission_rate ?? 0,
            $termDays !== null ? (int) $termDays : null
        );

        return Application::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'applicant_type' => $applicantType,
            'person_id' => $personId,
            'submitted_by_account_id' => $submittedBy?->id,
            'requested_amount' => $loanData['amount'],
            'requested_term_months' => $loanData['term_months'],
            'requested_term_days' => $termDays,
            'purpose' => $loanData['purpose'] ?? null,
            'purpose_description' => $loanData['purpose_description'] ?? null,
            'interest_rate' => $product->annual_rate,
            'monthly_payment' => $calculation['payment_amount'],
            'total_interest' => $calculation['total_interest'],
            'total_amount' => $calculation['total_to_pay'],
            'cat' => $calculation['cat'] ?? null,
            'status' => Application::STATUS_DRAFT,
            'expires_at' => now()->addDays(30), // Draft applications expire after 30 days
        ]);
    }

    // =====================================================
    // Application Updates
    // =====================================================

    /**
     * Update loan terms for a draft application.
     */
    public function updateLoanTerms(Application $application, array $loanData): Application
    {
        if (!$application->canBeEdited()) {
            throw new \InvalidArgumentException('Application cannot be edited in current status');
        }

        $product = $application->product;
        $amount = $loanData['amount'] ?? $application->requested_amount;
        $termMonths = $loanData['term_months'] ?? $application->requested_term_months;
        $termDays = $loanData['requested_term_days'] ?? $application->requested_term_days;
        $frequency = $loanData['frequency']
            ?? ($product->payment_frequencies[0] ?? 'MONTHLY');

        $calculation = $this->loanCalculator->calculateSimulation(
            $amount,
            $termMonths,
            $frequency,
            $product->annual_rate,
            $product->opening_commission_rate ?? 0,
            $termDays !== null ? (int) $termDays : null
        );

        $application->update([
            'requested_amount' => $amount,
            'requested_term_months' => $termMonths,
            'requested_term_days' => $termDays,
            'monthly_payment' => $calculation['payment_amount'],
            'total_interest' => $calculation['total_interest'],
            'total_amount' => $calculation['total_to_pay'],
            'cat' => $calculation['cat'] ?? null,
            'purpose' => $loanData['purpose'] ?? $application->purpose,
            'purpose_description' => $loanData['purpose_description'] ?? $application->purpose_description,
        ]);

        return $application->fresh();
    }

    // =====================================================
    // Submission & Validation
    // =====================================================

    /**
     * Submit an application for review.
     */
    public function submit(
        Application $application,
        ApplicantAccount $submittedBy,
        ?string $ip = null,
        ?string $device = null
    ): Application {
        if (!$application->canBeSubmitted()) {
            throw new \InvalidArgumentException('Application cannot be submitted in current status');
        }

        // Validate completeness
        $errors = $this->validateForSubmission($application);
        if (!empty($errors)) {
            throw new \InvalidArgumentException(
                'Application is incomplete: ' . implode(', ', $errors)
            );
        }

        // Create snapshot of applicant data at submission time
        $snapshotData = $this->createSnapshot($application);

        DB::transaction(function () use ($application, $submittedBy, $ip, $device, $snapshotData) {
            $application->update([
                'snapshot_data' => $snapshotData,
                'submitted_by_account_id' => $submittedBy->id,
            ]);

            $application->submit($submittedBy->id, $ip, $device);
        });

        $this->sendNotification(NotificationEvent::APPLICATION_SUBMITTED->value, $application);

        // Motor de decisión: si el producto tiene política activa (shadow o
        // active) se evalúa async. Sin política, el flujo sigue 100% manual.
        $policy = DecisionPolicy::activeFor($application->product_id);
        if ($policy && $policy->modeEnum()->evaluates()) {
            DecideApplicationJob::dispatch($application->id);
        }

        return $application->fresh();
    }

    /**
     * Validate application for submission.
     */
    public function validateForSubmission(Application $application): array
    {
        $errors = [];

        // Validate applicant exists (only individual/person applications supported)
        if (!$application->person) {
            $errors[] = 'Person data is missing';
        }

        $product = $application->product;
        // Si el producto define un onboarding dinámico (flow MoneyCapital y similares),
        // purpose y documentos formales se omiten porque van dentro de dynamic_data.
        $hasDynamicOnboarding = !empty($product->onboarding_steps ?? null);

        if (!$hasDynamicOnboarding) {
            // Validate purpose
            if (empty($application->purpose)) {
                $errors[] = 'Loan purpose is required';
            }

            // Validate required documents
            $requiredDocs = $product->required_documents ?? [];
            if (!empty($requiredDocs) && $application->person) {
                $missingDocs = $this->documentService->getMissingRequired($application->person, $requiredDocs);
                if (!empty($missingDocs)) {
                    $names = array_map(
                        fn ($d) => is_array($d) ? ($d['type'] ?? $d['description'] ?? json_encode($d)) : (string) $d,
                        $missingDocs,
                    );
                    $errors[] = 'Missing required documents: ' . implode(', ', $names);
                }
            }
        }

        return $errors;
    }

    /**
     * Create a snapshot of applicant data.
     */
    protected function createSnapshot(Application $application): array
    {
        // Only individual/person applications are supported
        $person = $application->person;
        return [
            'type' => 'individual',
            'person_id' => $person->id,
            'full_name' => $person->full_name,
            'curp' => $person->curp,
            'rfc' => $person->rfc,
            'birth_date' => $person->birth_date?->format('Y-m-d'),
            'nationality' => $person->nationality,
            // Access as property to get the loaded model, not the relation
            'current_address' => $person->currentHomeAddress?->toArray(),
            'current_employment' => $person->currentEmployment?->toArray(),
        ];
    }

    // =====================================================
    // Staff Actions
    // =====================================================

    /**
     * Assign application to a staff member.
     */
    public function assign(
        Application $application,
        StaffAccount $assignee,
        StaffAccount $assignedBy
    ): Application {
        $application->assignTo($assignee->id, $assignedBy->id);

        return $application->fresh();
    }

    /**
     * Change application status.
     */
    public function changeStatus(
        Application $application,
        string $newStatus,
        StaffAccount $changedBy,
        ?string $notes = null
    ): Application {
        $application->changeStatus($newStatus, $changedBy->id, StaffAccount::class, $notes);

        $statusEventMap = [
            'IN_REVIEW' => NotificationEvent::APPLICATION_IN_REVIEW,
            'DOCS_PENDING' => NotificationEvent::APPLICATION_DOCS_PENDING,
            'CORRECTIONS_PENDING' => NotificationEvent::APPLICATION_CORRECTIONS_REQUESTED,
        ];

        if (isset($statusEventMap[$newStatus])) {
            $this->sendNotification($statusEventMap[$newStatus]->value, $application);
        }

        return $application->fresh();
    }

    /**
     * Approve application.
     */
    public function approve(
        Application $application,
        StaffAccount $approvedBy,
        ?float $amount = null,
        ?int $termMonths = null,
        ?float $interestRate = null,
        ?string $notes = null
    ): Application {
        $application->approve($approvedBy->id, $amount, $termMonths, $interestRate, $notes);

        $this->sendNotification(NotificationEvent::APPLICATION_APPROVED->value, $application);

        return $application->fresh();
    }

    /**
     * Reject application.
     */
    public function reject(
        Application $application,
        StaffAccount $rejectedBy,
        string $reason,
        ?string $notes = null
    ): Application {
        $application->reject($rejectedBy->id, $reason, $notes);

        $this->sendNotification(NotificationEvent::APPLICATION_REJECTED->value, $application);

        return $application->fresh();
    }

    // =====================================================
    // Counter Offers
    // =====================================================

    /**
     * Send counter offer.
     *
     * El snapshot guarda tasa y comisión del producto al momento de ofertar,
     * para que la pantalla del solicitante no dependa de la config pública ni
     * de cambios futuros del producto. El plazo va en días (term_days) para
     * productos con rules.term_in_days, o en meses (term_months) para el resto.
     */
    public function sendCounterOffer(
        Application $application,
        StaffAccount $staff,
        array $offer,
        ?string $reason = null,
        int $expiresInMinutes = 30
    ): Application {
        $product = $application->product;

        if (!isset($offer['amount'])) {
            throw new \InvalidArgumentException('La contraoferta debe incluir el monto.');
        }

        if ($product->term_in_days) {
            if (!isset($offer['term_days'])) {
                throw new \InvalidArgumentException('La contraoferta debe incluir el plazo en días para este producto.');
            }
            // BULLET de pago único: tasa y comisión fijas del producto, sin amortización.
            $offer['term_months'] = null;
            $offer['interest_rate'] = $product->annual_rate;
            $offer['opening_commission'] = $product->opening_commission_rate;
        } else {
            if (!isset($offer['term_months'])) {
                throw new \InvalidArgumentException('La contraoferta debe incluir el plazo en meses.');
            }
            $offer['term_days'] = null;
            $interestRate = $offer['interest_rate'] ?? $product->annual_rate;

            $calculation = $this->loanCalculator->calculateSimulation(
                $offer['amount'],
                $offer['term_months'],
                'MONTHLY',
                $interestRate,
                $product->opening_commission_rate ?? 0
            );

            $offer['monthly_payment'] = $calculation['payment_amount'];
            $offer['total_amount'] = $calculation['total_to_pay'];
            $offer['interest_rate'] = $interestRate;
            $offer['opening_commission'] = $product->opening_commission_rate;
        }

        $offer['expires_at'] = now()->addMinutes($expiresInMinutes)->toIso8601String();
        $offer['source'] = 'STAFF';

        $application->sendCounterOffer($staff->id, $offer, $reason);

        $termLabel = $offer['term_days']
            ? "{$offer['term_days']} días"
            : "{$offer['term_months']} meses";
        $this->sendNotification(NotificationEvent::APPLICATION_COUNTER_OFFERED->value, $application, [
            'counter_offer' => [
                'amount' => '$' . number_format($offer['amount'], 2),
                'term' => $termLabel,
                'term_months' => $offer['term_months'] ?? '',
                'term_days' => $offer['term_days'] ?? '',
                'monthly_payment' => '$' . number_format($offer['monthly_payment'] ?? 0, 2),
                'total_amount' => '$' . number_format($offer['total_amount'] ?? 0, 2),
                'reason' => $reason ?? '',
            ],
        ]);

        return $application->fresh();
    }

    /**
     * Oferta generada por el motor de decisión (sin actor staff).
     *
     * A diferencia de la contraoferta manual, la del motor lleva RANGO
     * autorizado (min/max de monto y plazo) con el valor pre-seleccionado en
     * `amount`/`term_days`, `source: ENGINE` y vigencia en horas.
     */
    public function sendEngineOffer(
        Application $application,
        array $range,
        ?string $reason = null,
        int $validityHours = 72,
        ?int $reminderHoursBefore = 24,
        ?string $appliedByStaffId = null
    ): Application {
        $product = $application->product;

        $offer = [
            'amount' => $range['amount'],
            'min_amount' => $range['min_amount'],
            'max_amount' => $range['max_amount'],
            'source' => 'ENGINE',
        ];

        if ($product->term_in_days) {
            $offer['term_days'] = $range['term_days'];
            $offer['min_term_days'] = $range['min_term_days'] ?? $range['term_days'];
            $offer['max_term_days'] = $range['max_term_days'] ?? $range['term_days'];
            $offer['term_months'] = null;
            $offer['interest_rate'] = $product->annual_rate;
            $offer['opening_commission'] = $product->opening_commission_rate;
        } else {
            $offer['term_months'] = $range['term_months'];
            $offer['term_days'] = null;
            $offer['interest_rate'] = $product->annual_rate;
            $offer['opening_commission'] = $product->opening_commission_rate;

            $calculation = $this->loanCalculator->calculateSimulation(
                $offer['amount'],
                $offer['term_months'],
                'MONTHLY',
                $offer['interest_rate'],
                $offer['opening_commission'] ?? 0
            );
            $offer['monthly_payment'] = $calculation['payment_amount'];
            $offer['total_amount'] = $calculation['total_to_pay'];
        }

        $offer['expires_at'] = now()->addHours($validityHours)->toIso8601String();

        // Recordatorio único antes del vencimiento (barrido en counter-offers:expire).
        if ($reminderHoursBefore !== null && $reminderHoursBefore > 0 && $reminderHoursBefore < $validityHours) {
            $offer['reminder_at'] = now()->addHours($validityHours - $reminderHoursBefore)->toIso8601String();
            $offer['reminder_sent_at'] = null;
        }

        // $appliedByStaffId: el atajo "aplicar oferta sugerida" registra al
        // staff como actor, manteniendo source ENGINE (el rango es del motor).
        $application->sendCounterOffer($appliedByStaffId, $offer, $reason);

        $termLabel = $offer['term_days']
            ? "{$offer['term_days']} días"
            : "{$offer['term_months']} meses";
        $this->sendNotification(NotificationEvent::APPLICATION_COUNTER_OFFERED->value, $application, [
            'counter_offer' => [
                'amount' => '$' . number_format($offer['max_amount'], 2),
                'term' => $termLabel,
                'term_months' => $offer['term_months'] ?? '',
                'term_days' => $offer['term_days'] ?? '',
                'monthly_payment' => '$' . number_format($offer['monthly_payment'] ?? 0, 2),
                'total_amount' => '$' . number_format($offer['total_amount'] ?? 0, 2),
                'reason' => $reason ?? '',
            ],
        ]);

        return $application->fresh();
    }

    /**
     * Respond to counter offer.
     *
     * En ofertas de rango, $chosen trae monto/plazo elegidos por el cliente
     * (validados aquí contra el rango del snapshot) y $evidence la evidencia
     * de aceptación (ip, user_agent). En ofertas fijas ambos se ignoran.
     */
    public function respondToCounterOffer(
        Application $application,
        ApplicantAccount $account,
        bool $accepted,
        array $chosen = [],
        array $evidence = []
    ): Application {
        if (!$application->has_counter_offer) {
            throw new \InvalidArgumentException('No hay una contraoferta pendiente.');
        }

        if ($application->counter_offer_responded_at !== null) {
            throw new \InvalidArgumentException('La contraoferta ya fue respondida.');
        }

        // Cinturón server-side: no se puede aceptar una oferta vencida (el
        // comando counter-offers:expire puede tardar hasta 1 min en cancelarla).
        $expiresAt = $application->counter_offer['expires_at'] ?? null;
        if ($accepted && $expiresAt && now()->greaterThan($expiresAt)) {
            throw new \InvalidArgumentException('La contraoferta ya expiró.');
        }

        $acceptance = [];
        if ($accepted) {
            $acceptance = $this->resolveAcceptedTerms($application->counter_offer ?? [], $chosen);
            $acceptance['ip'] = $evidence['ip'] ?? null;
            $acceptance['user_agent'] = $evidence['user_agent'] ?? null;
        }

        $application->respondToCounterOffer($accepted, $account->id, $acceptance);

        // Aceptación en tenant con portafolio: crear el Loan con los términos
        // aceptados (era la intención documentada del módulo, sin cablear).
        if ($accepted) {
            $this->createLoanFromAcceptance($application->fresh());
        }

        $event = $accepted
            ? NotificationEvent::COUNTER_OFFER_ACCEPTED
            : NotificationEvent::COUNTER_OFFER_REJECTED;
        $this->sendNotification($event->value, $application, [
            'counter_offer' => [
                'amount' => '$' . number_format($application->counter_offer['amount'] ?? 0, 2),
                'reason' => $application->counter_offer['reason'] ?? '',
            ],
        ]);

        return $application->fresh();
    }

    /**
     * Resuelve los términos aceptados de una oferta. En ofertas fijas devuelve
     * vacío (el modelo copia el snapshot); en ofertas de rango valida lo
     * elegido contra el rango autorizado y recalcula el pago en modo meses.
     */
    protected function resolveAcceptedTerms(array $snapshot, array $chosen): array
    {
        $isRange = isset($snapshot['max_amount']);
        if (!$isRange) {
            return [];
        }

        $amount = (float) ($chosen['amount'] ?? $snapshot['amount']);
        $minAmount = (float) ($snapshot['min_amount'] ?? $amount);
        $maxAmount = (float) $snapshot['max_amount'];
        if ($amount < $minAmount || $amount > $maxAmount) {
            throw new \InvalidArgumentException('El monto elegido está fuera del rango autorizado.');
        }

        $terms = ['amount' => $amount];

        if (!empty($snapshot['term_days']) || !empty($snapshot['max_term_days'])) {
            $termDays = (int) ($chosen['term_days'] ?? $snapshot['term_days']);
            $minDays = (int) ($snapshot['min_term_days'] ?? $termDays);
            $maxDays = (int) ($snapshot['max_term_days'] ?? $termDays);
            if ($termDays < $minDays || $termDays > $maxDays) {
                throw new \InvalidArgumentException('El plazo elegido está fuera del rango autorizado.');
            }
            $terms['term_days'] = $termDays;
        } elseif (!empty($snapshot['term_months'])) {
            $termMonths = (int) ($chosen['term_months'] ?? $snapshot['term_months']);
            $terms['term_months'] = $termMonths;

            $calculation = $this->loanCalculator->calculateSimulation(
                $amount,
                $termMonths,
                'MONTHLY',
                (float) ($snapshot['interest_rate'] ?? 0),
                (float) ($snapshot['opening_commission'] ?? 0)
            );
            $terms['monthly_payment'] = $calculation['payment_amount'];
        }

        return $terms;
    }

    /**
     * Crea el Loan al aceptarse una oferta (productos en días, tenants con
     * feature loan_portfolio). Idempotente por application_id. Un fallo aquí no
     * revierte la aceptación: queda APPROVED sin loan y el staff lo resuelve.
     */
    protected function createLoanFromAcceptance(Application $application): void
    {
        $tenant = $application->tenant;
        $product = $application->product;

        if (!$tenant?->hasFeature('loan_portfolio') || !$product?->term_in_days) {
            return;
        }

        if (!$application->approved_amount || !$application->approved_term_days) {
            return;
        }

        $exists = Loan::withoutGlobalScopes()
            ->where('application_id', $application->id)
            ->exists();
        if ($exists) {
            return;
        }

        try {
            $this->loanService->createFromApplication($application, [
                'amount' => (float) $application->approved_amount,
                'term_days' => (int) $application->approved_term_days,
                'interest_rate' => (float) $application->approved_interest_rate,
            ]);
        } catch (\Throwable $e) {
            Log::error('No se pudo crear el Loan tras aceptar la oferta', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // =====================================================
    // Motor de decisión — ejecución de salidas
    // =====================================================

    /**
     * Ejecuta la salida del motor de decisión sobre una solicitud (solo en modo
     * ACTIVE; en sombra el job registra la evaluación sin llamar aquí).
     *
     * OFFER: SUBMITTED → IN_REVIEW → COUNTER_OFFERED con oferta de rango.
     * REVIEW: → IN_REVIEW (bandeja de no-asignadas existente).
     * REJECT: rechazo con motivo del motor (inicia el cooldown).
     */
    public function applyEngineOutcome(Application $application, array $result, DecisionPolicy $policy): void
    {
        $reason = implode('; ', $result['reasons'] ?? []);

        switch ($result['outcome']) {
            case DecisionOutcome::OFFER->value:
                $application->changeStatus(
                    Application::STATUS_IN_REVIEW,
                    null,
                    'system',
                    'Motor de decisión: evaluación completada'
                );
                $this->sendEngineOffer(
                    $application->fresh(),
                    $result['range'],
                    $reason ?: null,
                    (int) $policy->rule('offer.validity_hours', 72),
                    (int) $policy->rule('offer.reminder_hours_before', 24)
                );
                break;

            case DecisionOutcome::REVIEW->value:
                $application->changeStatus(
                    Application::STATUS_IN_REVIEW,
                    null,
                    'system',
                    'Motor de decisión: revisión manual — ' . ($reason ?: 'alertas parciales')
                );
                break;

            case DecisionOutcome::REJECT->value:
                $application->reject(null, 'MOTOR_DECISION', $reason ?: 'Rechazo del motor de decisión');
                $this->sendNotification(NotificationEvent::APPLICATION_REJECTED->value, $application);
                break;
        }
    }

    /**
     * Crea la solicitud de renovación al liquidarse un préstamo (Regla 05):
     * sin re-onboarding, reutilizando los datos vigentes de la persona, con la
     * oferta de rango del nivel de graduación ya calculada por el motor.
     */
    public function createRenewal(Loan $loan, DecisionPolicy $policy, array $result): Application
    {
        $origin = $loan->application;
        $product = $origin->product;
        $range = $result['range'];

        $application = Application::create([
            'tenant_id' => $loan->tenant_id,
            'product_id' => $product->id,
            'applicant_type' => Application::TYPE_INDIVIDUAL,
            'person_id' => $loan->person_id,
            'submitted_by_account_id' => $loan->applicant_account_id,
            'requested_amount' => $range['amount'],
            'requested_term_months' => $product->term_in_days ? 1 : ($range['term_months'] ?? 1),
            'requested_term_days' => $product->term_in_days ? $range['term_days'] : null,
            'interest_rate' => $product->annual_rate,
            'status' => Application::STATUS_DRAFT,
            'renewal_of_loan_id' => $loan->id,
            'metadata' => ['renewal' => true, 'renewal_of_loan_id' => $loan->id],
            'expires_at' => now()->addDays(30),
        ]);

        $application->update([
            'snapshot_data' => $this->createSnapshot($application),
            'submitted_at' => now(),
        ]);

        // Riel normal de estados con actor 'system' (sin re-onboarding).
        $application->changeStatus(Application::STATUS_SUBMITTED, null, 'system', 'Renovación automática al liquidar el crédito anterior');
        $application->changeStatus(Application::STATUS_IN_REVIEW, null, 'system', 'Motor de decisión: graduación de renovación');

        $this->sendEngineOffer(
            $application->fresh(),
            $range,
            'Oferta de renovación',
            (int) $policy->rule('offer.validity_hours', 72),
            (int) $policy->rule('offer.reminder_hours_before', 24)
        );

        return $application->fresh();
    }

    /**
     * Recordatorio único de oferta por vencer (lo dispara el barrido del
     * comando counter-offers:expire al cruzar reminder_at).
     */
    public function sendCounterOfferExpiringReminder(Application $application): void
    {
        $snapshot = $application->counter_offer ?? [];
        $snapshot['reminder_sent_at'] = now()->toIso8601String();
        $application->update(['counter_offer' => $snapshot]);

        $termLabel = !empty($snapshot['term_days'])
            ? "{$snapshot['term_days']} días"
            : (($snapshot['term_months'] ?? '') . ' meses');
        $this->sendNotification(NotificationEvent::COUNTER_OFFER_EXPIRING->value, $application, [
            'counter_offer' => [
                'amount' => '$' . number_format($snapshot['max_amount'] ?? $snapshot['amount'] ?? 0, 2),
                'term' => $termLabel,
                'reason' => $snapshot['reason'] ?? '',
            ],
        ]);
    }

    // =====================================================
    // Application Cancellation & Sync
    // =====================================================

    /**
     * Cancel application.
     */
    public function cancel(
        Application $application,
        ?string $cancelledById,
        string $cancelledByType,
        ?string $reason = null
    ): Application {
        if (!$application->canBeCancelled()) {
            throw new \InvalidArgumentException('Application cannot be cancelled in current status');
        }

        $application->cancel($cancelledById, $cancelledByType, $reason);

        // Sólo notificamos al solicitante cuando la cancelación NO la hizo él mismo
        // (evita avisarle de su propia acción). Ej: cancelación por el staff.
        if (strtoupper($cancelledByType) !== 'APPLICANT') {
            $this->sendNotification(NotificationEvent::APPLICATION_CANCELLED->value, $application, [
                'cancellation' => ['reason' => $reason ?? ''],
            ]);
        }

        return $application->fresh();
    }

    /**
     * Mark application as synced to external system.
     */
    public function markSynced(
        Application $application,
        string $externalId,
        string $system,
        ?array $syncData = null
    ): Application {
        $application->markSynced($externalId, $system, $syncData);

        return $application->fresh();
    }

    // =====================================================
    // Verification & Risk
    // =====================================================

    /**
     * Update verification checklist.
     */
    public function updateVerification(Application $application, array $checks): Application
    {
        $application->updateVerification($checks);

        return $application->fresh();
    }

    /**
     * Set risk assessment.
     */
    public function setRiskAssessment(
        Application $application,
        string $level,
        ?array $data = null
    ): Application {
        $application->setRiskAssessment($level, $data);

        return $application->fresh();
    }

    // =====================================================
    // Notifications
    // =====================================================

    /**
     * Send a notification for an application lifecycle event.
     */
    protected function sendNotification(string $event, Application $application, array $extra = []): void
    {
        try {
            $applicant = $application->submittedByAccount;
            if (!$applicant) {
                return;
            }

            $person = $applicant->person ?? $applicant->getPersonOrFind();

            // Los templates usan {{user.*}}; mantenemos `applicant` como alias.
            $who = [
                'first_name' => $person?->first_name ?? '',
                'last_name' => $person?->last_name_1 ?? '',
                'full_name' => $person?->full_name ?? '',
                'email' => $applicant->primary_email ?? '',
                'phone' => $applicant->primary_phone ?? '',
            ];

            // Arrendamiento vs crédito: las notificaciones deben decir "arrendamiento"
            // y mostrar la RENTA mensual, no el valor del bien como monto de crédito.
            $typeVal = $application->product?->type;
            $isLease = ($typeVal instanceof \BackedEnum ? $typeVal->value : $typeVal) === 'ARRENDAMIENTO';
            $monthlyRental = $application->metadata['lease']['simulation']['monthly_rental_with_iva'] ?? null;

            $variables = array_merge([
                'user' => $who,
                'applicant' => $who,
                'application' => [
                    'id' => $application->id,
                    'folio' => $application->folio,
                    'amount' => '$' . number_format($application->requested_amount ?? 0, 2),
                    'term_months' => $application->requested_term_months,
                    'product_name' => $application->product?->name ?? '',
                    'status' => $application->status,
                    'status_label' => Application::statuses()[$application->status] ?? $application->status,
                    // Framing por tipo de producto para templates arrendamiento-aware.
                    'is_lease' => $isLease,
                    'type_label' => $isLease ? 'arrendamiento' : 'crédito',
                    'primary_label' => $isLease ? 'Renta mensual' : 'Monto',
                    'primary_amount' => $isLease && $monthlyRental !== null
                        ? '$' . number_format($monthlyRental, 2)
                        : '$' . number_format($application->requested_amount ?? 0, 2),
                    'monthly_rental' => $monthlyRental !== null ? '$' . number_format($monthlyRental, 2) : null,
                ],
                'tenant' => [
                    'name' => $application->tenant?->name ?? '',
                    'phone' => $application->tenant?->phone ?? '',
                    'email' => $application->tenant?->email ?? '',
                    'website' => $application->tenant?->website ?? '',
                ],
            ], $extra);

            $this->notificationService->send($event, $applicant, $variables);
        } catch (\Throwable $e) {
            Log::warning('Failed to send notification', [
                'event' => $event,
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispara una notificación de lifecycle para la solicitud (wrapper público
     * de sendNotification, para eventos disparados desde controllers).
     */
    public function notifyApplicationEvent(string $event, Application $application, array $extra = []): void
    {
        $this->sendNotification($event, $application, $extra);
    }

    /**
     * Notifica al solicitante un evento de documento (aprobado/rechazado),
     * reutilizando el patrón de variables + los datos del documento.
     */
    public function notifyDocumentEvent(string $event, Application $application, \App\Models\Document $document): void
    {
        $typeLabel = (string) $document->type;
        if ($enum = \App\Enums\DocumentType::tryFrom($typeLabel)) {
            $typeLabel = $enum->label();
        }

        $this->sendNotification($event, $application, [
            'document' => [
                'type' => $document->type,
                'type_label' => $typeLabel,
                'rejection_reason' => $document->rejection_reason ?? '',
            ],
        ]);
    }

    // =====================================================
    // Query Methods
    // =====================================================

    /**
     * Find an application by ID for a specific tenant.
     */
    public function findByIdForTenant(string $id, Tenant $tenant, array $relations = []): ?Application
    {
        $query = Application::where('id', $id)
            ->where('tenant_id', $tenant->id);

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->first();
    }

    /**
     * Find an application by ID for a specific tenant or fail.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByIdForTenantOrFail(string $id, Tenant $tenant, array $relations = []): Application
    {
        $application = $this->findByIdForTenant($id, $tenant, $relations);

        if (!$application) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                "Application [{$id}] not found for tenant [{$tenant->id}]"
            );
        }

        return $application;
    }

    /**
     * Get applications for a person.
     */
    public function getForPerson(Person $person, ?string $status = null): Collection
    {
        $query = Application::forPerson($person->id)
            ->with(['product', 'assignedTo'])
            ->orderByDesc('created_at');

        if ($status) {
            $query->status($status);
        }

        return $query->get();
    }

    /**
     * Get applications assigned to staff.
     */
    public function getAssignedTo(StaffAccount $staff, ?string $status = null): Collection
    {
        $query = Application::assignedToStaff($staff->id)
            ->with(['person.account', 'company', 'product', 'assignedTo.profile'])
            ->orderByDesc('submitted_at');

        if ($status) {
            $query->status($status);
        }

        return $query->get();
    }

    /**
     * Get unassigned applications for tenant.
     */
    public function getUnassigned(Tenant $tenant, ?string $status = null): Collection
    {
        $query = Application::where('tenant_id', $tenant->id)
            ->unassigned()
            ->whereIn('status', [
                Application::STATUS_SUBMITTED,
                Application::STATUS_IN_REVIEW,
            ])
            ->with(['person.account', 'company', 'product', 'assignedTo.profile'])
            ->orderBy('submitted_at');

        if ($status) {
            $query->status($status);
        }

        return $query->get();
    }

    // =====================================================
    // Listing & Filtering
    // =====================================================

    /**
     * Get Kanban board data with applications grouped by status.
     *
     * Returns applications organized by column/status with a limit per column.
     * More efficient than fetching all applications for Kanban views.
     */
    public function getBoardData(
        Tenant $tenant,
        array $columns,
        int $limitPerColumn = 15,
        ?string $assignedTo = null,
        string $sortBy = 'created_at',
        string $sortDir = 'desc'
    ): array {
        // Cache 30s. Multiples staff abren el board con los mismos filtros
        // (caso comun: kanban del dashboard) -> con cache responden de Redis.
        // 30s es suficientemente fresco para un dashboard operacional y
        // absorbe el 90% del trafico tipico.
        $cacheKey = 'applications:board:' . $tenant->id . ':' . md5(implode(',', $columns) . "|{$limitPerColumn}|{$assignedTo}|{$sortBy}|{$sortDir}");

        return \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            30,
            fn () => $this->buildBoardData($tenant, $columns, $limitPerColumn, $assignedTo, $sortBy, $sortDir)
        );
    }

    private function buildBoardData(
        Tenant $tenant,
        array $columns,
        int $limitPerColumn,
        ?string $assignedTo,
        string $sortBy,
        string $sortDir
    ): array {
        $statusLabels = Application::statuses();
        $result = [
            'columns' => [],
            'totals' => [
                'all' => 0,
                'by_status' => [],
            ],
        ];

        // Get counts for all requested columns in one query
        $countsQuery = Application::where('tenant_id', $tenant->id)
            ->whereIn('status', $columns);

        if ($assignedTo) {
            $countsQuery->where('assigned_to', $assignedTo);
        }

        $counts = (clone $countsQuery)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $result['totals']['all'] = array_sum($counts);
        $result['totals']['by_status'] = $counts;

        // Fetch limited items per column
        foreach ($columns as $status) {
            $query = Application::where('tenant_id', $tenant->id)
                ->where('status', $status)
                ->with(['person', 'product', 'assignedTo.profile']);

            if ($assignedTo) {
                $query->where('assigned_to', $assignedTo);
            }

            $items = $query
                ->orderBy($sortBy, $sortDir)
                ->limit($limitPerColumn)
                ->get()
                ->map(fn($app) => $this->formatBoardItem($app));

            $result['columns'][] = [
                'status' => $status,
                'status_label' => $statusLabels[$status] ?? $status,
                'count' => $counts[$status] ?? 0,
                'items' => $items,
                'has_more' => ($counts[$status] ?? 0) > $limitPerColumn,
            ];
        }

        return $result;
    }

    /**
     * Format application for board/Kanban view (minimal data).
     */
    protected function formatBoardItem(Application $app): array
    {
        return [
            'id' => $app->id,
            'folio' => $app->folio,
            'status' => $app->status,
            'applicant_type' => $app->applicant_type,
            'applicant_name' => $app->is_individual
                ? $app->person?->full_name
                : null, // Company applications not supported
            'product' => $app->product ? [
                'id' => $app->product->id,
                'name' => $app->product->name,
            ] : null,
            'requested_amount' => $app->requested_amount,
            'assigned_to' => $app->assignedTo ? [
                'id' => $app->assignedTo->id,
                'name' => $app->assignedTo->profile?->full_name ?? $app->assignedTo->email,
            ] : null,
            'created_at' => $app->created_at?->toIso8601String(),
            'submitted_at' => $app->submitted_at?->toIso8601String(),
        ];
    }

    /**
     * Get applications list with filters and pagination.
     */
    public function list(
        Tenant $tenant,
        array $filters = [],
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = Application::where('tenant_id', $tenant->id)
            ->with(['person.account.phoneIdentity', 'company', 'product', 'assignedTo.profile']);

        // Apply filters
        if (!empty($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
            $query->whereIn('status', $statuses);
        }

        if (!empty($filters['applicant_type'])) {
            if ($filters['applicant_type'] === 'individual') {
                $query->individuals();
            } else {
                $query->companies();
            }
        }

        if (!empty($filters['assigned_to'])) {
            $query->assignedToStaff($filters['assigned_to']);
        }

        if (!empty($filters['unassigned'])) {
            $query->unassigned();
        }

        // Handle assignment filter (all, assigned, unassigned)
        if (!empty($filters['assignment'])) {
            if ($filters['assignment'] === 'assigned') {
                $query->whereNotNull('assigned_to');
            } elseif ($filters['assignment'] === 'unassigned') {
                $query->unassigned();
            }
            // 'all' doesn't need any filter
        }

        if (!empty($filters['risk_level'])) {
            $query->riskLevel($filters['risk_level']);
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            // Sanitize search input: escape SQL wildcards and limit length
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $filters['search']);
            $search = mb_substr($search, 0, 100);
            $query->where(function ($q) use ($search) {
                // Search in persons table (name fields)
                $q->whereHas('person', function ($pq) use ($search) {
                    $pq->where('first_name', 'ILIKE', "%{$search}%")
                        ->orWhere('last_name_1', 'ILIKE', "%{$search}%")
                        ->orWhere('last_name_2', 'ILIKE', "%{$search}%")
                        // Also search in person_identifications for CURP/RFC
                        ->orWhereHas('identifications', function ($iq) use ($search) {
                            $iq->where('identifier_value', 'ILIKE', "%{$search}%")
                                ->where('is_current', true);
                        });
                });
            });
        }

        // Sorting with validation to prevent SQL injection
        $allowedSortColumns = [
            'created_at', 'submitted_at', 'requested_amount',
            'status', 'decision_at', 'updated_at',
        ];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSortColumns)
            ? $filters['sort_by']
            : 'created_at';
        $sortDir = in_array(strtolower($filters['sort_dir'] ?? ''), ['asc', 'desc'])
            ? $filters['sort_dir']
            : 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    // =====================================================
    // Statistics & History
    // =====================================================

    /**
     * Get application statistics for dashboard.
     */
    public function getStatistics(Tenant $tenant, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        // Cache 60s por tenant+rango. El dashboard refresca seguido pero los
        // numeros no necesitan ser exactos al segundo. Sin cache, este endpoint
        // dispara ~17 queries (un COUNT por cada uno de los 13 status mas
        // total, today, processing). Con cache, ~95% de los hits responden
        // con 0 queries.
        $cacheKey = "applications:statistics:{$tenant->id}:" . md5(($dateFrom ?? '') . '|' . ($dateTo ?? ''));

        return \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            60,
            fn () => $this->buildStatistics($tenant, $dateFrom, $dateTo)
        );
    }

    /**
     * 17 queries originales -> 3:
     *  1) group by status (cuenta total y byStatus en una pasada)
     *  2) approved+rejected today (1 query con FILTER)
     *  3) avg processing time en SQL (sin cargar todos los records a PHP)
     */
    private function buildStatistics(Tenant $tenant, ?string $dateFrom, ?string $dateTo): array
    {
        $baseConditions = ['tenant_id' => $tenant->id];

        // Query 1: COUNT total + COUNT por status en una sola pasada.
        $statusCounts = Application::where($baseConditions)
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byStatus = [];
        foreach (array_keys(Application::statuses()) as $status) {
            $byStatus[strtolower($status)] = (int) ($statusCounts[$status] ?? 0);
        }
        $total = (int) array_sum($statusCounts);

        // Query 2: approved+rejected today en una sola query con FILTER.
        $today = now()->startOfDay();
        $todayRow = Application::where($baseConditions)
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo))
            ->where('status_changed_at', '>=', $today)
            ->whereIn('status', [Application::STATUS_APPROVED, Application::STATUS_REJECTED])
            ->selectRaw('
                COUNT(*) FILTER (WHERE status = ?) as approved,
                COUNT(*) FILTER (WHERE status = ?) as rejected
            ', [Application::STATUS_APPROVED, Application::STATUS_REJECTED])
            ->first();

        // Query 3: AVG de horas procesadas en SQL puro. Antes cargaba TODOS
        // los registros (potencialmente miles) y hacia el calculo en PHP.
        $avgRow = Application::where($baseConditions)
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo))
            ->whereIn('status', [Application::STATUS_APPROVED, Application::STATUS_REJECTED])
            ->whereNotNull('submitted_at')
            ->whereNotNull('status_changed_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (status_changed_at - submitted_at)) / 3600) as avg_hours')
            ->first();

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'pending_review' => $byStatus['submitted'] ?? 0,
            'pending_documents' => $byStatus['docs_pending'] ?? 0,
            'approved_today' => (int) ($todayRow->approved ?? 0),
            'rejected_today' => (int) ($todayRow->rejected ?? 0),
            'average_processing_time_hours' => round((float) ($avgRow->avg_hours ?? 0), 2),
        ];
    }

    /**
     * Get status history for an application.
     */
    public function getStatusHistory(Application $application): Collection
    {
        return $application->statusHistory()->get();
    }
}
