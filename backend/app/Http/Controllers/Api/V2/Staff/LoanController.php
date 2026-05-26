<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanExtension;
use App\Models\LoanPayment;
use App\Services\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Endpoints del staff para gestionar Loans:
 *  - Listar con filtros
 *  - Ver detalle
 *  - Registrar pago manual
 *  - Aprobar / rechazar prórrogas
 */
class LoanController extends Controller
{
    use ApiResponses;

    public function __construct(private LoanService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = Loan::query()->orderByDesc('disbursed_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($applicant = $request->input('applicant_id')) {
            $query->where('applicant_account_id', $applicant);
        }
        if ($overdue = $request->boolean('overdue')) {
            $query->whereDate('due_date', '<', now()->toDateString())
                ->whereIn('status', ['ACTIVE', 'DISBURSED', 'DEFAULT']);
        }
        if ($from = $request->input('from')) $query->where('disbursed_at', '>=', $from);
        if ($to = $request->input('to')) $query->where('disbursed_at', '<=', $to);

        $perPage = max(1, min((int) $request->input('per_page', 25), 100));
        $loans = $query->paginate($perPage);

        return $this->success([
            'items' => collect($loans->items())->map(fn($l) => $this->serialize($l))->all(),
            'pagination' => [
                'current_page' => $loans->currentPage(),
                'per_page' => $loans->perPage(),
                'total' => $loans->total(),
                'last_page' => $loans->lastPage(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $loan = Loan::with(['payments', 'extensions', 'rewards'])->find($id);
        if (! $loan) return $this->error('NOT_FOUND', 'Préstamo no encontrado', 404);
        return $this->success(['loan' => $this->serialize($loan, withDetails: true)]);
    }

    public function recordPayment(Request $request, string $id): JsonResponse
    {
        $loan = Loan::find($id);
        if (! $loan) return $this->error('NOT_FOUND', 'Préstamo no encontrado', 404);

        $data = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'channel' => 'in:CONEKTA,OPENPAY,STP,MANUAL',
            'paid_at' => 'nullable|date',
            'provider_reference' => 'nullable|string|max:128',
            'notes' => 'nullable|string',
        ])->validate();

        $payment = $this->service->recordPayment($loan, [
            'amount' => $data['amount'],
            'channel' => $data['channel'] ?? LoanPayment::CHANNEL_MANUAL,
            'paid_at' => $data['paid_at'] ?? now(),
            'provider_reference' => $data['provider_reference'] ?? null,
            'metadata' => isset($data['notes']) ? ['notes' => $data['notes']] : null,
        ]);

        return $this->created([
            'payment' => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'status' => $payment->status,
            ],
            'loan' => [
                'id' => $loan->fresh()->id,
                'outstanding_balance' => (float) $loan->fresh()->outstanding_balance,
                'status' => is_string($loan->fresh()->status) ? $loan->fresh()->status : $loan->fresh()->status->value,
            ],
        ], 'Pago registrado');
    }

    public function approveExtension(string $loanId, string $extensionId, Request $request): JsonResponse
    {
        $extension = LoanExtension::where('id', $extensionId)
            ->where('loan_id', $loanId)
            ->first();
        if (! $extension) return $this->error('NOT_FOUND', 'Extensión no encontrada', 404);

        $this->service->approveExtension($extension, $request->user()?->id);

        return $this->success(['extension_id' => $extension->id], 'Prórroga aprobada');
    }

    private function serialize(Loan $loan, bool $withDetails = false): array
    {
        $base = [
            'id' => $loan->id,
            'application_id' => $loan->application_id,
            'applicant_account_id' => $loan->applicant_account_id,
            'person_id' => $loan->person_id,
            'principal_amount' => (float) $loan->principal_amount,
            'interest_rate' => (float) $loan->interest_rate,
            'term_days' => $loan->term_days,
            'opening_commission_amount' => (float) $loan->opening_commission_amount,
            'total_to_pay' => (float) $loan->total_to_pay,
            'outstanding_balance' => (float) $loan->outstanding_balance,
            'paid_amount' => (float) $loan->paid_amount,
            'late_fee_accrued' => (float) $loan->late_fee_accrued,
            'status' => is_string($loan->status) ? $loan->status : $loan->status->value,
            'disbursed_at' => $loan->disbursed_at?->toIso8601String(),
            'due_date' => $loan->due_date?->toDateString(),
            'completed_at' => $loan->completed_at?->toIso8601String(),
            'disbursement_provider' => $loan->disbursement_provider,
            'disbursement_reference' => $loan->disbursement_reference,
            'created_at' => $loan->created_at?->toIso8601String(),
        ];

        if ($withDetails) {
            $base['payments'] = $loan->payments;
            $base['extensions'] = $loan->extensions;
            $base['rewards'] = $loan->rewards;
        }
        return $base;
    }
}
