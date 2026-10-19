<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailUnsubscribeController extends Controller
{
    public function show(Request $request, User $user): View
    {
        return view('emails.unsubscribe', [
            'user' => $user,
            'isOptedOut' => ! $user->allowsMarketingEmail(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $action = $request->input('action', 'unsubscribe');

        if ($action === 'unsubscribe') {
            $user->unsubscribeFromMarketing();
            $status = __('You have been successfully unsubscribed from marketing emails.');
        } else {
            $user->optInToMarketing();
            $status = __('Your marketing email preferences have been updated.');
        }

        return redirect()->to(\Illuminate\Support\Facades\URL::signedRoute('email.unsubscribe', $user))
            ->with('status', $status);
    }
}
