<?php

namespace Database\Seeders;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Plantillas de notificación específicas para MoneyCapital.
 * Idempotente: se puede correr varias veces sin duplicar.
 */
class MoneyCapitalNotificationSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'moneycapital')->first();
        if (! $tenant) {
            $this->command->warn('Tenant moneycapital no existe. Corre MoneyCapitalSeeder primero.');
            return;
        }

        $templates = [
            [
                'name' => 'Crédito preaprobado - Push',
                'event' => NotificationEvent::LOAN_PREAPPROVED,
                'channel' => NotificationChannel::PUSH,
                'subject' => '¡Tu préstamo fue preaprobado!',
                'body' => 'Acepta tu préstamo de {{loan.amount}} antes de {{loan.expires_at}}.',
            ],
            [
                'name' => 'Crédito preaprobado - Email',
                'event' => NotificationEvent::LOAN_PREAPPROVED,
                'channel' => NotificationChannel::EMAIL,
                'subject' => '¡Tu préstamo fue preaprobado! - {{tenant.name}}',
                'body' => "Hola {{user.first_name}},\n\nTu préstamo de {{loan.amount}} fue preaprobado. Tienes hasta {{loan.expires_at}} para aceptarlo desde la app.\n\n{{tenant.name}}",
            ],
            [
                'name' => 'Crédito dispersado - Push',
                'event' => NotificationEvent::LOAN_DISBURSED,
                'channel' => NotificationChannel::PUSH,
                'subject' => '¡Tu dinero fue depositado!',
                'body' => 'Tu préstamo de {{loan.amount}} fue depositado en la cuenta {{loan.bank_last4}}. Ref: {{loan.reference}}',
            ],
            [
                'name' => 'Crédito dispersado - SMS',
                'event' => NotificationEvent::LOAN_DISBURSED,
                'channel' => NotificationChannel::SMS,
                'subject' => null,
                'body' => '{{tenant.name}}: Tu préstamo de {{loan.amount}} fue depositado en cuenta terminación {{loan.bank_last4}}. Ref {{loan.reference}}',
            ],
            [
                'name' => 'Pago próximo a vencer - Push',
                'event' => NotificationEvent::PAYMENT_UPCOMING,
                'channel' => NotificationChannel::PUSH,
                'subject' => 'Tu pago vence pronto',
                'body' => 'Tu pago de {{loan.amount}} vence el {{loan.due_date}}. Paga desde la app y construye tu historial.',
            ],
            [
                'name' => 'Pago vencido - Push',
                'event' => NotificationEvent::PAYMENT_OVERDUE,
                'channel' => NotificationChannel::PUSH,
                'subject' => 'Tu pago está atrasado',
                'body' => 'Evita cargos adicionales: paga ahora desde la app.',
            ],
            [
                'name' => 'Pago vencido - SMS',
                'event' => NotificationEvent::PAYMENT_OVERDUE,
                'channel' => NotificationChannel::SMS,
                'subject' => null,
                'body' => '{{tenant.name}}: Tu pago está atrasado. Paga desde la app para evitar cargos adicionales.',
            ],
            [
                'name' => 'Prórroga aprobada - Push',
                'event' => NotificationEvent::LOAN_EXTENSION_GRANTED,
                'channel' => NotificationChannel::PUSH,
                'subject' => 'Prórroga aprobada',
                'body' => 'Tu prórroga fue aprobada. Nuevo vencimiento: {{loan.new_due_date}}.',
            ],
            [
                'name' => 'Crédito liquidado - Push',
                'event' => NotificationEvent::LOAN_COMPLETED,
                'channel' => NotificationChannel::PUSH,
                'subject' => '¡Felicidades, liquidaste tu préstamo!',
                'body' => 'Pagaste tu préstamo. Sigue construyendo tu historial con nosotros.',
            ],
        ];

        foreach ($templates as $i => $tpl) {
            NotificationTemplate::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'event' => $tpl['event'],
                    'channel' => $tpl['channel'],
                    'name' => $tpl['name'],
                ],
                [
                    'priority' => 1,
                    'subject' => $tpl['subject'],
                    'body' => $tpl['body'],
                    'is_active' => true,
                ],
            );
        }

        $this->command->info("Plantillas MoneyCapital creadas: " . count($templates));
    }
}
