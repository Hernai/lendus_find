<?php

namespace App\Services\Export;

use App\Enums\ApplicationStatus;
use Illuminate\Support\Collection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * Export data to CSV format.
     */
    public function toCsv(Collection|array $data, array $headers, string $filename): StreamedResponse
    {
        $data = $data instanceof Collection ? $data->toArray() : $data;

        return response()->streamDownload(function () use ($data, $headers) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Write headers
            fputcsv($handle, array_values($headers));

            // Write data rows
            foreach ($data as $row) {
                $csvRow = [];
                foreach (array_keys($headers) as $key) {
                    $csvRow[] = $row[$key] ?? '';
                }
                fputcsv($handle, $csvRow);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export applications report.
     */
    public function exportApplicationsReport(Collection $applications): StreamedResponse
    {
        $headers = [
            'folio' => 'Folio',
            'created_at' => 'Fecha de Solicitud',
            'applicant' => 'Solicitante',
            'curp' => 'CURP',
            'product' => 'Producto',
            'requested_amount' => 'Monto Solicitado',
            'approved_amount' => 'Monto Aprobado',
            'term_months' => 'Plazo (Meses)',
            'monthly_payment' => 'Pago Mensual',
            'interest_rate' => 'Tasa de Interés (%)',
            'status' => 'Estatus',
            'approved_at' => 'Fecha Aprobación',
            'disbursed_at' => 'Fecha Dispersión',
        ];

        $data = $applications->map(fn($app) => [
            'folio' => $app->folio,
            'created_at' => $app->created_at->format('Y-m-d H:i'),
            'applicant' => $app->applicant?->full_name ?? 'N/A',
            'curp' => $app->applicant?->curp ?? '',
            'product' => $app->product?->name ?? 'N/A',
            'requested_amount' => number_format($app->requested_amount, 2, '.', ''),
            'approved_amount' => number_format($app->approved_amount ?? 0, 2, '.', ''),
            'term_months' => $app->term_months,
            'monthly_payment' => number_format($app->monthly_payment ?? 0, 2, '.', ''),
            'interest_rate' => number_format($app->interest_rate ?? 0, 2, '.', ''),
            'status' => $this->translateStatus($app->status),
            'approved_at' => $app->approved_at?->format('Y-m-d H:i') ?? '',
            'disbursed_at' => $app->disbursed_at?->format('Y-m-d H:i') ?? '',
        ]);

        $filename = 'solicitudes_' . now()->format('Y-m-d_His') . '.csv';

        return $this->toCsv($data, $headers, $filename);
    }

    /**
     * Export disbursements report.
     */
    public function exportDisbursementsReport(Collection $applications): StreamedResponse
    {
        $headers = [
            'folio' => 'Folio',
            'disbursed_at' => 'Fecha de Dispersión',
            'applicant' => 'Solicitante',
            'curp' => 'CURP',
            'product' => 'Producto',
            'amount' => 'Monto Dispersado',
            'term_months' => 'Plazo (Meses)',
            'monthly_payment' => 'Pago Mensual',
            'interest_rate' => 'Tasa (%)',
            'bank' => 'Banco',
            'clabe' => 'CLABE',
            'reference' => 'Referencia',
        ];

        $data = $applications->map(fn($app) => [
            'folio' => $app->folio,
            'disbursed_at' => $app->disbursed_at?->format('Y-m-d H:i') ?? '',
            'applicant' => $app->applicant?->full_name ?? 'N/A',
            'curp' => $app->applicant?->curp ?? '',
            'product' => $app->product?->name ?? 'N/A',
            'amount' => number_format($app->approved_amount, 2, '.', ''),
            'term_months' => $app->term_months,
            'monthly_payment' => number_format($app->monthly_payment ?? 0, 2, '.', ''),
            'interest_rate' => number_format($app->interest_rate ?? 0, 2, '.', ''),
            'bank' => $app->applicant?->bank_info['bank_name'] ?? '',
            'clabe' => $app->applicant?->bank_info['clabe'] ?? '',
            'reference' => $app->disbursement_reference ?? '',
        ]);

        $filename = 'dispersiones_' . now()->format('Y-m-d_His') . '.csv';

        return $this->toCsv($data, $headers, $filename);
    }

    /**
     * Export portfolio report.
     */
    public function exportPortfolioReport(Collection $portfolio): StreamedResponse
    {
        $headers = [
            'folio' => 'Folio',
            'applicant' => 'Solicitante',
            'product' => 'Producto',
            'disbursed_at' => 'Fecha Dispersión',
            'original_amount' => 'Monto Original',
            'outstanding_balance' => 'Saldo Vigente',
            'term_months' => 'Plazo',
            'payments_made' => 'Pagos Realizados',
            'payments_remaining' => 'Pagos Pendientes',
            'next_payment_date' => 'Próximo Pago',
            'days_past_due' => 'Días de Mora',
            'status' => 'Estatus',
        ];

        $filename = 'cartera_' . now()->format('Y-m-d_His') . '.csv';

        return $this->toCsv($portfolio, $headers, $filename);
    }

    /**
     * Translate application status to Spanish.
     */
    private function translateStatus(string $status): string
    {
        return match ($status) {
            ApplicationStatus::DRAFT => 'Borrador',
            ApplicationStatus::SUBMITTED => 'Enviada',
            ApplicationStatus::IN_REVIEW => 'En Revisión',
            ApplicationStatus::DOCS_PENDING => 'Docs. Pendientes',
            ApplicationStatus::APPROVED => 'Aprobada',
            ApplicationStatus::REJECTED => 'Rechazada',
            ApplicationStatus::DISBURSED => 'Dispersada',
            ApplicationStatus::CANCELLED => 'Cancelada',
            default => $status,
        };
    }
}
