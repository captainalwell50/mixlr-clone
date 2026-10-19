<?php

namespace App\Console\Commands;

use App\Enums\OrgRole;
use App\Mail\InvoiceReminderMail;
use App\Models\Invoice;
use App\Services\OutboundEmailService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendInvoiceRemindersCommand extends Command
{
    protected $signature = 'billing:send-invoice-reminders {--dry-run : Only log actions without sending emails}';
    protected $description = 'Send scheduled invoice reminders for upcoming and overdue invoices';

    public function handle(OutboundEmailService $outbound): int
    {
        $today = now()->startOfDay();
        $dryRun = $this->option('dry-run');

        $invoices = Invoice::query()
            ->with(['organization.users'])
            ->whereIn('status', [Invoice::STATUS_PENDING, 'open', Invoice::STATUS_PAST_DUE, Invoice::STATUS_PARTIALLY_PAID])
            ->get();

        $sentCount = 0;

        foreach ($invoices as $invoice) {
            if ($invoice->isPaid()) {
                continue;
            }

            $dueDate = $invoice->due_date->copy()->startOfDay();
            $diffDays = (int) $today->diffInDays($dueDate, false); // positive if due date is future, negative if past

            $stage = match ($diffDays) {
                7 => '7_days_before',
                3 => '3_days_before',
                0 => 'due_date',
                -3 => '3_days_overdue',
                -7 => '7_days_overdue',
                default => null,
            };

            if ($stage === null) {
                continue;
            }

            $eventKey = "invoice-reminder:{$invoice->id}:{$stage}";

            $owners = $invoice->organization->users()
                ->wherePivot('role', OrgRole::Owner->value)
                ->get();

            if ($owners->isEmpty()) {
                $owners = $invoice->organization->users;
            }

            foreach ($owners as $user) {
                $userEventKey = "{$eventKey}:{$user->id}";

                if ($dryRun) {
                    $this->info("[Dry Run] Would send {$stage} reminder for Invoice {$invoice->invoice_number} to {$user->email}");
                    $sentCount++;
                    continue;
                }

                $delivery = $outbound->sendOperational(
                    mailable: new InvoiceReminderMail($invoice, $stage),
                    recipient: $user->email,
                    type: 'invoice_reminder',
                    eventKey: $userEventKey,
                    user: $user,
                    metadata: [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'stage' => $stage,
                    ]
                );

                if ($delivery) {
                    $sentCount++;
                }
            }
        }

        $this->info("Completed sending invoice reminders. Total dispatched: {$sentCount}");

        return self::SUCCESS;
    }
}
