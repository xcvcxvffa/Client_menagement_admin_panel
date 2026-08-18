<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();
            $businesses = $user->businesses;

            if ($businesses->isNotEmpty()) {
                $currentBusinessId = $user->current_business_id;

                // Verify user actually belongs to this current business
                if (!$currentBusinessId || !$businesses->contains('id', $currentBusinessId)) {
                    $currentBusinessId = $businesses->first()->id;
                    $user->current_business_id = $currentBusinessId;
                    $user->save();
                }

                session(['active_business_id' => $currentBusinessId]);
                
                // Set Spatie Team ID to the current business context
                setPermissionsTeamId($currentBusinessId);
            } else {
                if ($user->current_business_id !== null) {
                    $user->current_business_id = null;
                    $user->save();
                }
                session()->forget('active_business_id');
                setPermissionsTeamId(null);
            }
        } else {
            session()->forget('active_business_id');
            setPermissionsTeamId(null);
        }

        return $next($request);
    }
}
