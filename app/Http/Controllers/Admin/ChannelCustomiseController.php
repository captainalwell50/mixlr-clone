<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Stream;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ChannelCustomiseController extends Controller
{
    public function edit(Request $request, Organization $organization): View
    {
        abort_unless($request->user()?->canManageOrganization($organization), 403);

        $stream = $organization->defaultStream();

        return view('admin.organizations.customise', [
            'organization' => $organization,
            'stream' => $stream,
            'logoUrl' => $organization->logoUrl(),
            'artworkUrl' => $organization->artworkUrl(),
            'listenBackgroundUrl' => $stream?->listenBackgroundUrl(),
        ]);
    }

    public function updateLogo(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()?->canManageOrganization($organization), 403);

        $validated = $request->validate([
            'logo' => ['required', 'image', 'max:5120'],
        ]);

        $path = $this->storeBrandingImage($validated['logo'], $organization, 'logo');
        $this->deleteStoredPath($organization->logo_path);
        $organization->forceFill(['logo_path' => $path])->save();

        return redirect()
            ->route('admin.organizations.customise', $organization)
            ->with('status', __('Channel logo updated.'));
    }

    public function destroyLogo(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()?->canManageOrganization($organization), 403);

        $this->deleteStoredPath($organization->logo_path);
        $organization->forceFill(['logo_path' => null])->save();

        return redirect()
            ->route('admin.organizations.customise', $organization)
            ->with('status', __('Channel logo removed.'));
    }

    public function updateArtwork(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()?->canManageOrganization($organization), 403);

        $validated = $request->validate([
            'artwork' => ['required', 'image', 'max:12288'],
        ]);

        $path = $this->storeBrandingImage($validated['artwork'], $organization, 'artwork');
        $this->deleteStoredPath($organization->artwork_path);
        $organization->forceFill(['artwork_path' => $path])->save();

        return redirect()
            ->route('admin.organizations.customise', $organization)
            ->with('status', __('Channel artwork updated.'));
    }

    public function destroyArtwork(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()?->canManageOrganization($organization), 403);

        $this->deleteStoredPath($organization->artwork_path);
        $organization->forceFill(['artwork_path' => null])->save();

        return redirect()
            ->route('admin.organizations.customise', $organization)
            ->with('status', __('Channel artwork removed.'));
    }

    public function updateBackground(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()?->canManageOrganization($organization), 403);

        $stream = $this->requireDefaultStream($organization);

        $validated = $request->validate([
            'background' => ['required', 'image', 'max:12288'],
        ]);

        $path = $validated['background']->store('listen-bg/'.$stream->uuid, 'public');
        $this->deleteStoredPath($stream->listen_background_path);
        $stream->forceFill(['listen_background_path' => $path])->save();

        return redirect()
            ->route('admin.organizations.customise', $organization)
            ->with('status', __('Listen background updated.'));
    }

    public function destroyBackground(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()?->canManageOrganization($organization), 403);

        $stream = $this->requireDefaultStream($organization);

        $this->deleteStoredPath($stream->listen_background_path);
        $stream->forceFill(['listen_background_path' => null])->save();

        return redirect()
            ->route('admin.organizations.customise', $organization)
            ->with('status', __('Listen background removed.'));
    }

    public function updateGiving(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()?->canManageOrganization($organization), 403);

        $validated = $request->validate([
            'giving_enabled' => ['sometimes', 'boolean'],
            'giving_url' => ['nullable', 'url', 'max:500'],
            'giving_account_name' => ['nullable', 'string', 'max:255'],
            'giving_bank_name' => ['nullable', 'string', 'max:255'],
            'giving_account_number' => ['nullable', 'string', 'max:64'],
            'giving_note' => ['nullable', 'string', 'max:255'],
        ]);

        $blankToNull = static function (mixed $value): ?string {
            if (! is_string($value)) {
                return null;
            }

            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        };

        $enabled = $request->boolean('giving_enabled');
        $url = $blankToNull($validated['giving_url'] ?? null);
        $accountName = $blankToNull($validated['giving_account_name'] ?? null);
        $bankName = $blankToNull($validated['giving_bank_name'] ?? null);
        $accountNumber = $blankToNull($validated['giving_account_number'] ?? null);
        $note = $blankToNull($validated['giving_note'] ?? null);
        $hasAccount = filled($accountName) || filled($bankName) || filled($accountNumber);

        if ($enabled && $url === null && ! filled($organization->support_url) && ! $hasAccount) {
            return back()
                ->withErrors([
                    'giving_url' => __('Add a giving page URL or bank account details before enabling Give online.'),
                ])
                ->withInput();
        }

        $organization->forceFill([
            'giving_enabled' => $enabled,
            'giving_url' => $url,
            'giving_account_name' => $accountName,
            'giving_bank_name' => $bankName,
            'giving_account_number' => $accountNumber,
            'giving_note' => $note,
        ])->save();

        return redirect()
            ->route('admin.organizations.customise', $organization)
            ->with('status', __('Give online settings saved.'));
    }

    private function requireDefaultStream(Organization $organization): Stream
    {
        $stream = $organization->defaultStream();
        abort_unless($stream !== null, 404, 'Create a stream for this channel before setting a listen background.');

        return $stream;
    }

    private function storeBrandingImage(UploadedFile $file, Organization $organization, string $kind): string
    {
        return $file->store('org-branding/'.$organization->id.'/'.$kind, 'public');
    }

    private function deleteStoredPath(?string $previous): void
    {
        if (! is_string($previous) || $previous === '') {
            return;
        }

        if (str_starts_with($previous, 'http://')
            || str_starts_with($previous, 'https://')
            || str_starts_with($previous, '/')) {
            return;
        }

        Storage::disk('public')->delete($previous);
    }
}
