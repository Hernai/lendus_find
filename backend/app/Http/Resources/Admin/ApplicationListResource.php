<?php

namespace App\Http\Resources\Admin;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for Application list view (Admin panel).
 *
 * Lightweight format for list/table display.
 */
class ApplicationListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Application $application */
        $application = $this->resource;

        return [
            'id' => $application->id,
            'folio' => $application->folio,
            'status' => $application->status,
            'applicant' => $application->applicant ? [
                'id' => $application->applicant->id,
                'name' => $application->applicant->full_name,
                'phone' => $application->applicant->phone,
                'email' => $application->applicant->email,
            ] : null,
            'product' => $application->product ? [
                'id' => $application->product->id,
                'name' => $application->product->name,
                'type' => $application->product->type,
            ] : null,
            'requested_amount' => (float) $application->requested_amount,
            'approved_amount' => $application->approved_amount ? (float) $application->approved_amount : null,
            'term_months' => $application->term_months,
            'payment_frequency' => $application->payment_frequency,
            'monthly_payment' => (float) $application->monthly_payment,
            'assigned_to' => $application->assignedAgent?->name,
            'risk_level' => $application->risk_level,
            'created_at' => $application->created_at->toIso8601String(),
            'updated_at' => $application->updated_at->toIso8601String(),
        ];
    }
}
