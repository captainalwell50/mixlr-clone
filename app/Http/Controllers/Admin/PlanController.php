<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Support\PlanFeatureCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $plans = Plan::query()->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.plans.index', compact('plans'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $plan = new Plan([
            'currency' => config('services.paystack.currency', env('PAYSTACK_CURRENCY', 'NGN')),
            'interval' => 'monthly',
            'is_active' => true,
            'sort_order' => (int) Plan::query()->max('sort_order') + 1,
            'limits' => PlanFeatureCatalog::defaultLimits(),
        ]);

        return view('admin.plans.form', [
            'plan' => $plan,
            'quotas' => PlanFeatureCatalog::quotas(),
            'featureGroups' => PlanFeatureCatalog::featuresByGroup(),
            'storageGb' => PlanFeatureCatalog::storageGbFromLimits($plan->limits),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validated($request);
        $slug = $validated['slug'] ?: Str::slug($validated['name']);

        Plan::query()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'paystack_plan_code' => ($validated['paystack_plan_code'] ?? null) ?: null,
            'amount' => (int) $validated['amount'],
            'currency' => strtoupper($validated['currency']),
            'interval' => $validated['interval'],
            'limits' => PlanFeatureCatalog::limitsFromInput($request->all()),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) $validated['sort_order'],
        ]);

        return redirect()->route('admin.plans.index')
            ->with('status', __('Pricing package created.'));
    }

    public function edit(Request $request, Plan $plan): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return view('admin.plans.form', [
            'plan' => $plan,
            'quotas' => PlanFeatureCatalog::quotas(),
            'featureGroups' => PlanFeatureCatalog::featuresByGroup(),
            'storageGb' => PlanFeatureCatalog::storageGbFromLimits($plan->limits),
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validated($request, $plan);

        $plan->fill([
            'name' => $validated['name'],
            'slug' => ($validated['slug'] ?? null) ?: $plan->slug,
            'paystack_plan_code' => ($validated['paystack_plan_code'] ?? null) ?: null,
            'amount' => (int) $validated['amount'],
            'currency' => strtoupper($validated['currency']),
            'interval' => $validated['interval'],
            'limits' => PlanFeatureCatalog::limitsFromInput($request->all()),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) $validated['sort_order'],
        ])->save();

        return redirect()->route('admin.plans.edit', $plan)
            ->with('status', __('Pricing package updated.'));
    }

    public function destroy(Request $request, Plan $plan): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($plan->subscriptions()->exists()) {
            return back()->with('error', __('This package has subscribers. Deactivate it instead of deleting.'));
        }

        $plan->delete();

        return redirect()->route('admin.plans.index')
            ->with('status', __('Pricing package deleted.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('plans', 'slug')->ignore($plan?->id),
            ],
            'paystack_plan_code' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'interval' => ['required', 'in:monthly,yearly'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
            'max_streams' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'max_recordings' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'storage_gb' => ['nullable', 'integer', 'min:0', 'max:10240'],
            'features' => ['nullable', 'array'],
        ]);
    }
}
