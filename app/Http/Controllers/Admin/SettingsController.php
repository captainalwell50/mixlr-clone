<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\Stream;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $status = Stream::listenPlaybackStatus();

        return view('admin.settings.edit', [
            'listenStatus' => $status,
            'listenPreferHlsFormValue' => old('listen_prefer_hls', $status['form_value']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'listen_prefer_hls' => ['required', 'string', Rule::in(SiteSetting::LISTEN_PREFER_HLS_OPTIONS)],
        ]);

        SiteSetting::putValue(
            SiteSetting::KEY_LISTEN_PREFER_HLS,
            $validated['listen_prefer_hls'],
        );

        return redirect()->route('admin.settings.edit')
            ->with('status', __('Public listen playback preference updated.'));
    }
}
