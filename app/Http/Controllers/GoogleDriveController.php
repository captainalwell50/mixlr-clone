<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationDriveConnection;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class GoogleDriveController extends Controller
{
    public function redirect(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorizeOrg($request, $organization);

        $request->session()->put('google_drive_org_id', $organization->id);

        return Socialite::driver('google')
            ->scopes(config('services.google.scopes', []))
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $orgId = $request->session()->pull('google_drive_org_id');
        $organization = Organization::query()->find($orgId);
        abort_unless($organization && $request->user()?->canManageOrganization($organization), 403);

        $googleUser = Socialite::driver('google')->user();

        OrganizationDriveConnection::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'connected_by' => $request->user()->id,
                'google_email' => $googleUser->getEmail(),
                'access_token' => $googleUser->token,
                'refresh_token' => $googleUser->refreshToken,
                'token_expires_at' => now()->addSeconds((int) ($googleUser->expiresIn ?? 3600)),
            ],
        );

        app(GoogleDriveService::class)->ensureRootFolder(
            $organization->driveConnection()->firstOrFail()
        );

        $stream = $organization->defaultStream() ?? $organization->streams()->first();
        if ($stream === null) {
            return redirect()->route('creator.home')
                ->with('status', 'Google Drive connected.');
        }

        return redirect()
            ->route('studio.stream', $stream)
            ->with('status', 'Google Drive connected. Library files can use your Drive.');
    }

    public function status(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request, $organization);
        $connection = $organization->driveConnection;

        return response()->json([
            'connected' => $connection !== null,
            'email' => $connection?->google_email,
            'folder' => GoogleDriveService::ROOT_FOLDER_NAME,
        ]);
    }

    public function disconnect(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request, $organization);
        $organization->driveConnection?->delete();

        return response()->json(['ok' => true]);
    }

    public function files(Request $request, Organization $organization, GoogleDriveService $drive): JsonResponse
    {
        $this->authorizeOrg($request, $organization);
        $connection = $organization->driveConnection;
        abort_unless($connection, 422, 'Google Drive is not connected.');

        return response()->json([
            'files' => $drive->listAudioFiles($connection, $request->query('q')),
        ]);
    }

    private function authorizeOrg(Request $request, Organization $organization): void
    {
        abort_unless($request->user()?->canManageOrganization($organization), 403);
    }
}
