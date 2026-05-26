<?php

namespace App\Http\Controllers\Api\V2\Applicant;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\ApplicantAccount;
use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Endpoints del cliente para gestionar sus préstamos activos
 * (módulo Loan Portfolio — solo tenants con feature flag activo).
 */
class LoanController extends Controller
{
    use ApiResponses;

    public function __construct(private LoanService $service) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->applicant($request);
        $loans = Loan::query()
            ->where('applicant_account_id', $user->id)
            ->with(['payments' => fn($q) => $q->where('status', 'COMPLETED')])
            ->orderByDesc('disbursed_at')
            ->get()
            ->map(fn($l) => $this->serializeLoan($l));

        return $this->success(['items' => $loans]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $loan = $this->findOwnedLoan($request, $id);
        if (! $loan) return $this->error('NOT_FOUND', 'Préstamo no encontrado', 404);
        return $this->success(['loan' => $this->serializeLoan($loan, withDetails: true)]);
    }

    public function quoteExtension(Request $request, string $id): JsonResponse
    {
        $loan = $this->findOwnedLoan($request, $id);
        if (! $loan) return $this->error('NOT_FOUND', 'Préstamo no encontrado', 404);

        $data = Validator::make($request->all(), ['days' => 'required|integer|in:7,15'])->validate();
        $quote = $this->service->quoteExtension($loan, (int) $data['days']);
        return $this->success($quote);
    }

    public function requestExtension(Request $request, string $id): JsonResponse
    {
        $loan = $this->findOwnedLoan($request, $id);
        if (! $loan) return $this->error('NOT_FOUND', 'Préstamo no encontrado', 404);
        $data = Validator::make($request->all(), ['days' => 'required|integer|in:7,15'])->validate();
        $extension = $this->service->requestExtension($loan, (int) $data['days']);
        return $this->created([
            'extension' => [
                'id' => $extension->id,
                'days_added' => $extension->days_added,
                'fee_amount' => $extension->fee_amount,
                'new_due_date' => $extension->new_due_date,
                'status' => $extension->status,
            ],
        ], 'Solicitud de prórroga enviada');
    }

    public function pay(Request $request, string $id): JsonResponse
    {
        $loan = $this->findOwnedLoan($request, $id);
        if (! $loan) return $this->error('NOT_FOUND', 'Préstamo no encontrado', 404);

        // Stub: hasta que Conekta/OpenPay estén integrados, devolvemos URL placeholder.
        // El cliente debe ser redirigido al gateway. Cuando se complete el pago, el
        // webhook recibido llamará LoanService::recordPayment.
        return $this->success([
            'gateway' => 'stub',
            'payment_url' => 'https://example.com/pay/' . $loan->id,
            'amount' => (float) $loan->outstanding_balance,
        ], 'Pago en proceso (gateway stub)');
    }

    private function applicant(Request $request): ApplicantAccount
    {
        /** @var ApplicantAccount $u */
        $u = $request->user();
        return $u;
    }

    private function findOwnedLoan(Request $request, string $id): ?Loan
    {
        $user = $this->applicant($request);
        return Loan::where('id', $id)
            ->where('applicant_account_id', $user->id)
            ->first();
    }

    private function serializeLoan(Loan $loan, bool $withDetails = false): array
    {
        $base = [
            'id' => $loan->id,
            'application_id' => $loan->application_id,
            'principal_amount' => (float) $loan->principal_amount,
            'interest_rate' => (float) $loan->interest_rate,
            'term_days' => $loan->term_days,
            'total_to_pay' => (float) $loan->total_to_pay,
            'outstanding_balance' => (float) $loan->outstanding_balance,
            'paid_amount' => (float) $loan->paid_amount,
            'status' => is_string($loan->status) ? $loan->status : $loan->status->value,
            'disbursed_at' => $loan->disbursed_at?->toIso8601String(),
            'due_date' => $loan->due_date?->toDateString(),
            'days_until_due' => $loan->daysUntilDue(),
            'is_overdue' => $loan->isOverdue(),
            'completed_at' => $loan->completed_at?->toIso8601String(),
        ];

        if ($withDetails) {
            $base['payments'] = $loan->payments->map(fn($p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'paid_at' => $p->paid_at?->toIso8601String(),
                'status' => $p->status,
                'channel' => $p->channel,
                'provider_reference' => $p->provider_reference,
            ])->all();
            $base['extensions'] = $loan->extensions->map(fn($e) => [
                'id' => $e->id,
                'days_added' => $e->days_added,
                'fee_amount' => (float) $e->fee_amount,
                'previous_due_date' => $e->previous_due_date?->toDateString(),
                'new_due_date' => $e->new_due_date?->toDateString(),
                'status' => $e->status,
                'approved_at' => $e->approved_at?->toIso8601String(),
            ])->all();
            $base['rewards'] = $loan->rewards->map(fn($r) => [
                'type' => $r->type,
                'points' => $r->points,
                'description' => $r->description,
                'earned_at' => $r->earned_at?->toIso8601String(),
            ])->all();
        }

        return $base;
    }
}
