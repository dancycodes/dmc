<?php

namespace App\Http\Responses;

use Closure;
use Illuminate\Http\Request;
use Spatie\Honeypot\SpamResponder\SpamResponder;

/**
 * Silently reject spam submissions without revealing the protection mechanism.
 *
 * For Gale (SSE) requests: returns an empty 200 response to avoid bot detection.
 * For regular HTTP requests: redirects back to the form page silently.
 */
class SilentSpamResponder implements SpamResponder
{
    public function respond(Request $request, Closure $next): mixed
    {
        if ($request->isGale()) {
            return response('', 200);
        }

        return redirect()->back();
    }
}
