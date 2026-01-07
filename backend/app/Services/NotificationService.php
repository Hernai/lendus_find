<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Applicant;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Send application submitted notification.
     */
    public function applicationSubmitted(Application $application): void
    {
        $applicant = $application->applicant;
        $user = $applicant->user;

        // Send to applicant
        $this->sendEmail(
            to: $user->email ?? $applicant->email,
            subject: "Solicitud #{$application->folio} recibida",
            template: 'application-submitted',
            data: [
                'name' => $applicant->first_name,
                'folio' => $application->folio,
                'product' => $application->product->name ?? 'Crédito',
                'amount' => number_format($application->requested_amount, 2),
            ]
        );

        // Send SMS if configured
        if ($user->phone) {
            $this->sendSms(
                to: $user->phone,
                message: "Tu solicitud #{$application->folio} ha sido recibida. Te notificaremos el resultado pronto."
            );
        }

        Log::info('Application submitted notification sent', [
            'application_id' => $application->id,
            'folio' => $application->folio,
        ]);
    }

    /**
     * Send application approved notification.
     */
    public function applicationApproved(Application $application): void
    {
        $applicant = $application->applicant;
        $user = $applicant->user;

        $this->sendEmail(
            to: $user->email ?? $applicant->email,
            subject: "¡Felicidades! Tu solicitud #{$application->folio} ha sido aprobada",
            template: 'application-approved',
            data: [
                'name' => $applicant->first_name,
                'folio' => $application->folio,
                'product' => $application->product->name ?? 'Crédito',
                'approved_amount' => number_format($application->approved_amount ?? $application->requested_amount, 2),
                'term_months' => $application->term_months,
                'monthly_payment' => number_format($application->monthly_payment, 2),
            ]
        );

        if ($user->phone) {
            $this->sendSms(
                to: $user->phone,
                message: "¡Felicidades! Tu solicitud #{$application->folio} por \${$application->approved_amount} ha sido APROBADA."
            );
        }

        Log::info('Application approved notification sent', [
            'application_id' => $application->id,
            'folio' => $application->folio,
        ]);
    }

    /**
     * Send application rejected notification.
     */
    public function applicationRejected(Application $application): void
    {
        $applicant = $application->applicant;
        $user = $applicant->user;

        $this->sendEmail(
            to: $user->email ?? $applicant->email,
            subject: "Actualización de tu solicitud #{$application->folio}",
            template: 'application-rejected',
            data: [
                'name' => $applicant->first_name,
                'folio' => $application->folio,
                'product' => $application->product->name ?? 'Crédito',
                'reason' => $application->rejection_reason,
            ]
        );

        Log::info('Application rejected notification sent', [
            'application_id' => $application->id,
            'folio' => $application->folio,
        ]);
    }

    /**
     * Send documents pending notification.
     */
    public function documentsPending(Application $application, array $pendingDocs): void
    {
        $applicant = $application->applicant;
        $user = $applicant->user;

        $this->sendEmail(
            to: $user->email ?? $applicant->email,
            subject: "Documentos pendientes para tu solicitud #{$application->folio}",
            template: 'documents-pending',
            data: [
                'name' => $applicant->first_name,
                'folio' => $application->folio,
                'pending_documents' => $pendingDocs,
            ]
        );

        if ($user->phone) {
            $this->sendSms(
                to: $user->phone,
                message: "Tienes documentos pendientes en tu solicitud #{$application->folio}. Ingresa a tu cuenta para subirlos."
            );
        }

        Log::info('Documents pending notification sent', [
            'application_id' => $application->id,
            'folio' => $application->folio,
            'pending_count' => count($pendingDocs),
        ]);
    }

    /**
     * Send disbursement notification.
     */
    public function applicationDisbursed(Application $application): void
    {
        $applicant = $application->applicant;
        $user = $applicant->user;
        $bankAccount = $applicant->primaryBankAccount;

        $this->sendEmail(
            to: $user->email ?? $applicant->email,
            subject: "¡Tu crédito ha sido dispersado! Solicitud #{$application->folio}",
            template: 'application-disbursed',
            data: [
                'name' => $applicant->first_name,
                'folio' => $application->folio,
                'amount' => number_format($application->approved_amount ?? $application->requested_amount, 2),
                'bank_name' => $bankAccount?->bank_name,
                'clabe_last4' => $bankAccount ? substr($bankAccount->clabe, -4) : '****',
                'disbursement_reference' => $application->disbursement_reference,
            ]
        );

        if ($user->phone) {
            $this->sendSms(
                to: $user->phone,
                message: "Tu crédito de \${$application->approved_amount} ha sido depositado. Ref: {$application->disbursement_reference}"
            );
        }

        Log::info('Application disbursed notification sent', [
            'application_id' => $application->id,
            'folio' => $application->folio,
        ]);
    }

    /**
     * Send counter-offer notification.
     */
    public function counterOfferCreated(Application $application): void
    {
        $applicant = $application->applicant;
        $user = $applicant->user;
        $counterOffer = $application->counter_offers[count($application->counter_offers) - 1] ?? null;

        if (!$counterOffer) {
            return;
        }

        $this->sendEmail(
            to: $user->email ?? $applicant->email,
            subject: "Nueva propuesta para tu solicitud #{$application->folio}",
            template: 'counter-offer',
            data: [
                'name' => $applicant->first_name,
                'folio' => $application->folio,
                'original_amount' => number_format($application->requested_amount, 2),
                'offered_amount' => number_format($counterOffer['amount'] ?? 0, 2),
                'offered_term' => $counterOffer['term_months'] ?? $application->term_months,
                'offered_rate' => $counterOffer['interest_rate'] ?? $application->interest_rate,
                'reason' => $counterOffer['reason'] ?? null,
            ]
        );

        if ($user->phone) {
            $this->sendSms(
                to: $user->phone,
                message: "Tienes una nueva propuesta para tu solicitud #{$application->folio}. Revisa los detalles en tu cuenta."
            );
        }

        Log::info('Counter-offer notification sent', [
            'application_id' => $application->id,
            'folio' => $application->folio,
        ]);
    }

    /**
     * Send OTP code.
     */
    public function sendOtpCode(string $phone, string $email = null, string $code, string $method): void
    {
        $message = "Tu código de verificación es: {$code}. Válido por 10 minutos.";

        if ($method === 'email' && $email) {
            $this->sendEmail(
                to: $email,
                subject: "Código de verificación: {$code}",
                template: 'otp-code',
                data: [
                    'code' => $code,
                    'expires_in' => '10 minutos',
                ]
            );
        } elseif (in_array($method, ['sms', 'whatsapp'])) {
            $this->sendSms(
                to: $phone,
                message: $message,
                channel: $method
            );
        }

        Log::info('OTP code sent', [
            'method' => $method,
            'phone' => substr($phone, -4),
        ]);
    }

    /**
     * Send email notification.
     */
    protected function sendEmail(string $to, string $subject, string $template, array $data = []): bool
    {
        if (empty($to)) {
            Log::warning('Cannot send email: no recipient', ['template' => $template]);
            return false;
        }

        try {
            // In production, use proper mail templates
            // For now, log the email
            if (config('app.env') === 'local') {
                Log::info('Email notification (local mode)', [
                    'to' => $to,
                    'subject' => $subject,
                    'template' => $template,
                    'data' => $data,
                ]);
                return true;
            }

            Mail::send("emails.{$template}", $data, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send email', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send SMS notification.
     */
    protected function sendSms(string $to, string $message, string $channel = 'sms'): bool
    {
        if (empty($to)) {
            Log::warning('Cannot send SMS: no recipient');
            return false;
        }

        try {
            // In production, integrate with Twilio/MessageBird/etc.
            // For now, log the SMS
            if (config('app.env') === 'local') {
                Log::info('SMS notification (local mode)', [
                    'to' => $to,
                    'channel' => $channel,
                    'message' => $message,
                ]);
                return true;
            }

            // TODO: Implement actual SMS sending via provider
            // $this->twilioService->sendSms($to, $message);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send SMS', [
                'to' => substr($to, -4),
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
