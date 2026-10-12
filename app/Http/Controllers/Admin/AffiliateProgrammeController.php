<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AffiliateProspectStatus;
use App\Enums\AffiliateStatus;
use App\Http\Controllers\Controller;
use App\Models\AffiliateAccount;
use App\Models\AffiliateAttribution;
use App\Models\AffiliateProspect;
use App\Models\AffiliateSettingAudit;
use App\Services\AffiliateProgrammeSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AffiliateProgrammeController extends Controller
{
    public function overview(): View
    {
        $accounts = AffiliateAccount::query();
        $prospects = AffiliateProspect::query();
        $cancelled = (clone $prospects)->where('status', AffiliateProspectStatus::Cancelled->value)->count();
        $converted = (clone $prospects)->where('status', AffiliateProspectStatus::Converted->value)->count();
        $created = (clone $prospects)->count();
        $denominator = $created - $cancelled;

        $cities = AffiliateAccount::query()
            ->selectRaw('city, state_region, count(*) as total')
            ->groupBy('city', 'state_region')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return view('admin.affiliate-programme.overview', [
            'counts' => [
                'total' => (clone $accounts)->count(),
                'approved' => (clone $accounts)->where('status', AffiliateStatus::Approved->value)->count(),
                'pending' => (clone $accounts)->where('status', AffiliateStatus::Pending->value)->count(),
                'suspended' => (clone $accounts)->where('status', AffiliateStatus::Suspended->value)->count(),
                'referred' => AffiliateAttribution::query()->whereNotNull('affiliate_account_id')->count(),
                'reservations' => (clone $prospects)->holdingReservation()->count(),
                'conversion' => $denominator > 0 ? (int) round(($converted / $denominator) * 100) : null,
            ],
            'cities' => $cities,
            'levels' => AffiliateProgrammeSettings::levels(),
        ]);
    }

    public function settings(): View
    {
        return view('admin.affiliate-programme.settings', [
            'settings' => AffiliateProgrammeSettings::all(),
            'sections' => $this->sections(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        if ($request->input('payout_mode') === 'paystack') {
            throw ValidationException::withMessages([
                'payout_mode' => 'Paystack Transfer cannot be enabled until that payout feature exists.',
            ]);
        }

        $validated = $request->validate($this->rules());
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $values = $validated;
        foreach (['default_commission_bps', 'penalty_first_bps', 'penalty_second_bps', 'retention_waiver_bps', 'restored_commission_bps'] as $key) {
            $values[$key] = (int) round(((float) $validated[$key]) * 100);
        }
        $values['registration_fee_minor'] = (int) round(((float) $validated['registration_fee_minor']) * 100);
        $values['minimum_payout_minor'] = (int) round(((float) $validated['minimum_payout_minor']) * 100);

        if ((int) $values['penalty_second_months'] <= (int) $values['penalty_first_months']) {
            throw ValidationException::withMessages([
                'penalty_second_months' => 'The second penalty must come after the first penalty.',
            ]);
        }

        $changes = AffiliateProgrammeSettings::save($values, $actor);

        return redirect()
            ->route('admin.affiliate-programme.settings')
            ->with('status', $changes === 0
                ? __('No programme settings changed.')
                : __('Affiliate programme settings saved. :count change(s) were audited.', ['count' => $changes]));
    }

    public function levels(): View
    {
        return view('admin.affiliate-programme.levels', [
            'levels' => AffiliateProgrammeSettings::levels(),
            'graceDays' => (int) AffiliateProgrammeSettings::all()['demotion_grace_days'],
        ]);
    }

    public function updateLevels(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'levels' => ['required', 'array', 'min:1', 'max:12'],
            'levels.*.name' => ['nullable', 'string', 'max:40'],
            'levels.*.min_active_paying_churches' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'levels.*.display_order' => ['nullable', 'integer', 'min:0', 'max:100'],
            'levels.*.enabled' => ['nullable', 'boolean'],
        ]);

        $levels = [];
        $minimums = [];
        foreach ($validated['levels'] as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $enabled = filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOL);
            $minimum = (int) ($row['min_active_paying_churches'] ?? 0);
            if ($enabled && in_array($minimum, $minimums, true)) {
                throw ValidationException::withMessages([
                    'levels' => 'Two enabled levels cannot share the same minimum number of active paying churches.',
                ]);
            }
            if ($enabled) {
                $minimums[] = $minimum;
            }

            $levels[] = [
                'name' => $name,
                'min_active_paying_churches' => $minimum,
                'display_order' => (int) ($row['display_order'] ?? 0),
                'enabled' => $enabled,
            ];
        }

        if ($levels === []) {
            throw ValidationException::withMessages([
                'levels' => 'Keep at least one affiliate level.',
            ]);
        }

        usort($levels, function (array $left, array $right): int {
            return [$left['display_order'], $left['min_active_paying_churches']]
                <=> [$right['display_order'], $right['min_active_paying_churches']];
        });

        $actor = $request->user();
        abort_unless($actor !== null, 403);
        $changes = AffiliateProgrammeSettings::saveLevels($levels, $actor);

        return redirect()
            ->route('admin.affiliate-programme.levels')
            ->with('status', $changes === 0
                ? __('No level changes to save.')
                : __('Affiliate levels saved. The change was audited.'));
    }

    public function audit(): View
    {
        return view('admin.affiliate-programme.audit', [
            'audits' => AffiliateSettingAudit::query()->with('actor')->latest('id')->paginate(30),
            'labels' => AffiliateProgrammeSettings::labels(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'registration_mode' => ['required', Rule::in(AffiliateProgrammeSettings::REGISTRATION_MODES)],
            'require_admin_approval' => ['required', 'boolean'],
            'registration_fee_minor' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'registration_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'attribution_days' => ['required', 'integer', 'min:1', 'max:365'],
            'prospect_reservation_days' => ['required', 'integer', 'min:1', 'max:365'],
            'prospect_active_limit' => ['required', 'integer', 'min:0', 'max:500'],
            'outreach_visit_enabled' => ['required', 'boolean'],
            'outreach_direct_email_enabled' => ['required', 'boolean'],
            'outreach_phone_call_enabled' => ['required', 'boolean'],
            'default_commission_bps' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_hold_days' => ['required', 'integer', 'min:0', 'max:90'],
            'payout_qualification_target' => ['required', 'integer', 'min:1', 'max:100'],
            'recurring_commission_enabled' => ['required', 'boolean'],
            'inactivity_warning_months' => ['required', 'integer', 'min:1', 'max:24'],
            'penalty_first_months' => ['required', 'integer', 'min:1', 'max:36'],
            'penalty_first_bps' => ['required', 'numeric', 'min:0', 'max:100'],
            'penalty_second_months' => ['required', 'integer', 'min:1', 'max:48'],
            'penalty_second_bps' => ['required', 'numeric', 'min:0', 'max:100'],
            'retention_protection_enabled' => ['required', 'boolean'],
            'retention_waiver_bps' => ['required', 'numeric', 'min:1', 'max:100'],
            'restore_on_new_paying_church' => ['required', 'boolean'],
            'restore_on_retention_recovery' => ['required', 'boolean'],
            'restored_commission_bps' => ['required', 'numeric', 'min:0', 'max:100'],
            'demotion_grace_days' => ['required', 'integer', 'min:0', 'max:365'],
            'minimum_payout_minor' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'bank_details_required' => ['required', 'boolean'],
            'admin_can_release_prospects' => ['required', 'boolean'],
            'release_reason_required' => ['required', 'boolean'],
            'affiliate_notes_enabled' => ['required', 'boolean'],
            'prospect_note_max_length' => ['required', 'integer', 'min:100', 'max:5000'],
            'max_approved_affiliates' => ['required', 'integer', 'min:0', 'max:100000'],
            'max_applications_per_month' => ['required', 'integer', 'min:0', 'max:100000'],
            'show_renewal_approaching' => ['required', 'boolean'],
            'show_failed_renewal' => ['required', 'boolean'],
            'show_past_due' => ['required', 'boolean'],
            'show_disconnected_clients' => ['required', 'boolean'],
            'show_retention_percentage' => ['required', 'boolean'],
            'show_conversion_rate' => ['required', 'boolean'],
            'show_affiliate_level' => ['required', 'boolean'],
            'show_level_progress' => ['required', 'boolean'],
            'show_lifetime_earnings' => ['required', 'boolean'],
            'show_prospect_statistics' => ['required', 'boolean'],
        ];
    }

    /**
     * @return list<array{title: string, intro: string, fields: list<array<string, mixed>>}>
     */
    private function sections(): array
    {
        return [
            [
                'title' => 'Programme status',
                'intro' => 'Closed registration stops new affiliate accounts. Free and Paid describe future applications. Switching to Paid does not suspend affiliates who are already approved, and it does not charge anyone. Registration fees are not collected yet.',
                'fields' => [
                    $this->boolField('enabled', 'Affiliate programme', 'When this is off, new referral visits and new church plans stop. Channels that already have an attribution stay as they are.'),
                    $this->selectField('registration_mode', 'Registration mode', [
                        'closed' => 'Closed',
                        'free' => 'Free',
                        'paid' => 'Paid',
                    ], 'Paid applies to new applicants only. Existing approved affiliates remain approved.'),
                    $this->boolField('require_admin_approval', 'Require admin approval', 'There is no public application form yet. This choice is saved for that form. It does not approve or reject anyone on its own.'),
                    $this->moneyField('registration_fee_minor', 'Registration fee', 'Stored for a future paid application. Saving a fee does not create a Paystack charge.'),
                    $this->textField('registration_currency', 'Registration currency', 'Three-letter currency code, such as NGN.'),
                ],
            ],
            [
                'title' => 'Referral and attribution',
                'intro' => 'The attribution window limits how long a referral visit can still be used. A prospect reservation is a separate, shorter hold on a church. Self-referral protection stays on.',
                'fields' => [
                    $this->numberField('attribution_days', 'Referral attribution window (days)', 'A visit older than this is ignored. The default is 90 days. Changing it does not move an attribution that is already stored on a channel.'),
                    $this->lockedField('Attribution model', 'First-touch', 'The first valid referral visit is kept. A later link does not replace it. This is not switchable from this screen.'),
                    $this->numberField('prospect_reservation_days', 'Prospect reservation period (days)', 'A new church plan holds that church for this many days. The default is 30. It does not renew, and it does not extend a plan that is already open.'),
                    $this->numberField('prospect_active_limit', 'Maximum active prospect reservations', 'Each approved affiliate can hold this many live plans. The default is 20. Expired, cancelled, converted, and not-interested plans do not use a slot.'),
                    $this->boolField('outreach_visit_enabled', 'Allow Plan Visit', 'Affiliates can only start an outreach method that is turned on here.'),
                    $this->boolField('outreach_direct_email_enabled', 'Allow Plan Direct Email', 'Turning this off blocks new direct-email plans. Existing plans stay on record.'),
                    $this->boolField('outreach_phone_call_enabled', 'Allow Plan Phone Call', 'Turning this off blocks new phone-call plans. Existing plans stay on record.'),
                    $this->lockedField('Self-referral protection', 'Enabled', 'An affiliate who owns or administers the channel cannot earn from that channel. This cannot be turned off here.'),
                ],
            ],
            [
                'title' => 'Commission settings',
                'intro' => 'These numbers are the future policy. They do not calculate a commission, and they do not read plan prices or payment checkouts. A rate change applies only to a later qualifying payment. Any commission row, once it exists, keeps the rate it was given.',
                'fields' => [
                    $this->percentField('default_commission_bps', 'Default commission rate (%)', 'Default 10%. This is the rate a future commission would snapshot. Nothing already recorded is rewritten, because no commission rows are created here.'),
                    $this->numberField('commission_hold_days', 'Commission hold period (days)', 'Default 14 days. A future payable date would wait this long. Saving it does not move money.'),
                    $this->numberField('payout_qualification_target', 'Minimum distinct paying churches', 'Default 5. A future payout check would count distinct paying churches, not list prices.'),
                    $this->boolField('recurring_commission_enabled', 'Recurring commission', 'When a ledger exists, this says whether later collected renewals can commission. It does not create those rows now.'),
                    $this->lockedField('Commission basis', 'Actual amount collected', 'Commission is not taken from the package list price or from payment checkouts.'),
                ],
            ],
            [
                'title' => 'Performance and penalties',
                'intro' => 'A penalty is defined as inactivity past the threshold and retention below the configured rate. If retention stays at or above that rate, the inactivity penalty is waived. These rules are stored only. They are not applied to affiliates yet, and they do not change a historical rate.',
                'fields' => [
                    $this->numberField('inactivity_warning_months', 'Inactivity warning (months)', 'Default 1 month. A warning would come before either penalty.'),
                    $this->numberField('penalty_first_months', 'First penalty after (months)', 'Default 3 months, and only when retention is also below the threshold.'),
                    $this->percentField('penalty_first_bps', 'First penalty rate (%)', 'Default 7%. A future payment would snapshot this rate. Past payments are not recalculated.'),
                    $this->numberField('penalty_second_months', 'Second penalty after (months)', 'Default 6 months. This must be later than the first penalty.'),
                    $this->percentField('penalty_second_bps', 'Second penalty rate (%)', 'Default 5%. Same snapshot rule as the first penalty.'),
                    $this->boolField('retention_protection_enabled', 'Retention protection', 'When this is on, good retention waives the inactivity penalty.'),
                    $this->percentField('retention_waiver_bps', 'Minimum retention rate (%)', 'Default 90%. At or above this rate, inactivity does not reduce the commission rate.'),
                ],
            ],
            [
                'title' => 'Recovery',
                'intro' => 'Recovery would put the restored rate on payments that happen after the affiliate qualifies again. It would not recalculate commissions that were already snapshotted.',
                'fields' => [
                    $this->boolField('restore_on_new_paying_church', 'Restore after a new paying church', 'A later collected payment could use the restored rate once a new paying church is gained.'),
                    $this->boolField('restore_on_retention_recovery', 'Restore when retention recovers', 'A later collected payment could use the restored rate once retention is back at the threshold.'),
                    $this->percentField('restored_commission_bps', 'Restored commission rate (%)', 'Default 10%. This is the rate those future payments would snapshot.'),
                    $this->numberField('demotion_grace_days', 'Demotion grace period (days)', 'Default 30 days. Promotion would be immediate once assignment exists. Demotion would wait this long. Changing it does not move anyone today, because levels are not assigned from payments yet.'),
                ],
            ],
            [
                'title' => 'Payout settings',
                'intro' => 'Payouts are not sent from this screen. Paystack Transfer stays unavailable until that feature exists.',
                'fields' => [
                    $this->lockedField('Payout frequency', 'Monthly', 'The first payout cadence is monthly. It is not editable yet.'),
                    $this->moneyField('minimum_payout_minor', 'Minimum payout amount', 'Optional floor for a future manual payout. Zero means no floor. Nothing is paid by saving this.'),
                    $this->lockedField('Payout mode', 'Manual', 'Paystack Transfer is disabled until the transfer feature exists. It cannot be selected.'),
                    $this->boolField('bank_details_required', 'Require bank details', 'A future payout screen can refuse to continue until bank details are on file. Those details are not collected here.'),
                ],
            ],
            [
                'title' => 'Prospect controls',
                'intro' => 'Reservation length and the active limit are also set under referral settings. Renewal by the affiliate stays off.',
                'fields' => [
                    $this->boolField('admin_can_release_prospects', 'Admin can release a reservation', 'Platform admins can cancel another affiliate’s hold when this is on. Church managers never can.'),
                    $this->boolField('release_reason_required', 'Require a reason for release', 'A release without a reason is rejected while this is on. The reason is stored with the operator and the time.'),
                    $this->boolField('affiliate_notes_enabled', 'Allow affiliate notes', 'Notes stay on the affiliate’s own plan and the platform admin view. They are not a place for payment data.'),
                    $this->numberField('prospect_note_max_length', 'Maximum note length', 'Default 2,000 characters.'),
                    $this->lockedField('Self-service renewal', 'Disabled', 'A reservation does not renew itself, and an affiliate cannot extend it from the portal.'),
                ],
            ],
            [
                'title' => 'Capacity',
                'intro' => 'Use these to slow the programme without removing people who are already approved. Zero means no cap.',
                'fields' => [
                    $this->numberField('max_approved_affiliates', 'Maximum approved affiliates', 'New approvals stop at this number. People who are already approved stay approved.'),
                    $this->numberField('max_applications_per_month', 'Maximum new applications per month', 'Counts affiliate accounts opened this calendar month. Zero means unlimited.'),
                ],
            ],
            [
                'title' => 'Affiliate dashboard',
                'intro' => 'These toggles choose what an affiliate may see about their own work. They never reveal another church’s invoices, card, bank account, or Paystack data. Earnings, rank, and retention figures are not calculated yet, so those toggles stay stored until that reporting exists.',
                'fields' => [
                    $this->boolField('show_prospect_statistics', 'Prospect statistics', 'Active plans, visits, emails, calls, converted, and expired on My Prospects.'),
                    $this->boolField('show_conversion_rate', 'Conversion rate', 'Converted plans divided by plans that were not cancelled.'),
                    $this->boolField('show_renewal_approaching', 'Renewal approaching', 'Stored for a later report. No church billing detail is included.'),
                    $this->boolField('show_failed_renewal', 'Failed renewal', 'Stored for a later report. No charge reference is shown to the affiliate.'),
                    $this->boolField('show_past_due', 'Past due', 'Stored for a later report.'),
                    $this->boolField('show_disconnected_clients', 'Disconnected clients', 'Stored for a later report.'),
                    $this->boolField('show_retention_percentage', 'Retention percentage', 'Stored for a later report. Retention is not calculated yet.'),
                    $this->boolField('show_affiliate_level', 'Affiliate level', 'Stored for a later report. Levels are not assigned from payments yet.'),
                    $this->boolField('show_level_progress', 'Progress toward the next level', 'Stored for a later report.'),
                    $this->boolField('show_lifetime_earnings', 'Lifetime earnings', 'Stored for a later report. No commission total exists yet.'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function boolField(string $key, string $label, string $help): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'bool', 'help' => $help];
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, mixed>
     */
    private function selectField(string $key, string $label, array $options, string $help): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'select', 'options' => $options, 'help' => $help];
    }

    /**
     * @return array<string, mixed>
     */
    private function numberField(string $key, string $label, string $help): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'number', 'help' => $help];
    }

    /**
     * @return array<string, mixed>
     */
    private function percentField(string $key, string $label, string $help): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'percent', 'help' => $help];
    }

    /**
     * @return array<string, mixed>
     */
    private function moneyField(string $key, string $label, string $help): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'money', 'help' => $help];
    }

    /**
     * @return array<string, mixed>
     */
    private function textField(string $key, string $label, string $help): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'text', 'help' => $help];
    }

    /**
     * @return array<string, mixed>
     */
    private function lockedField(string $label, string $value, string $help): array
    {
        return ['label' => $label, 'type' => 'locked', 'value' => $value, 'help' => $help];
    }
}
