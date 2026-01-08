<?php

namespace App\Events;

use App\Enums\VerifiableField;
use App\Models\Applicant;
use App\Models\DataVerification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DataCorrectionSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DataVerification $verification,
        public Applicant $applicant,
        public mixed $oldValue,
        public mixed $newValue
    ) {}

    public function broadcastOn(): array
    {
        return [
            // Canal personal del aplicante (para su dashboard)
            new PrivateChannel("tenant.{$this->applicant->tenant_id}.applicant.{$this->applicant->id}"),

            // Canal admin del tenant (para que admins vean las correcciones)
            new PrivateChannel("tenant.{$this->applicant->tenant_id}.admin"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'data.correction.submitted';
    }

    public function broadcastWith(): array
    {
        $history = $this->verification->correction_history ?? [];
        $latestCorrection = end($history) ?: null;

        // Para campos de nombre, mostrar "Nombre Completo" como label
        $nameFields = [
            VerifiableField::FIRST_NAME->value,
            VerifiableField::LAST_NAME_1->value,
            VerifiableField::LAST_NAME_2->value,
        ];
        $fieldLabel = in_array($this->verification->field_name, $nameFields)
            ? 'Nombre Completo'
            : DataVerification::getFieldLabel($this->verification->field_name);

        return [
            'verification_id' => $this->verification->id,
            'applicant_id' => $this->applicant->id,
            'applicant_name' => $this->applicant->full_name,
            'field_name' => $this->verification->field_name,
            'field_label' => $fieldLabel,
            'old_value' => $this->oldValue,
            'new_value' => $this->newValue,
            'corrected_by' => $latestCorrection['corrected_by'] ?? null,
            'correction_count' => count($history),
            'corrected_at' => $latestCorrection['corrected_at'] ?? now()->toIso8601String(),
        ];
    }
}
