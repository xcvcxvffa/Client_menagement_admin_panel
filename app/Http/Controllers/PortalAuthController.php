<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class PortalAuthController extends Controller
{
    /**
     * Handle magic link login
     */
    public function login(Request $request, Client $client)
    {
        if (!$request->hasValidSignature()) {
            abort(401, 'This login link is invalid or has expired.');
        }

        Auth::guard('client')->login($client);

        return redirect()->route('portal.dashboard');
    }

    /**
     * Log the client out
     */
    public function logout(Request $request)
    {
        Auth::guard('client')->logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
