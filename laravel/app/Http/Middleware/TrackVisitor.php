<?php

namespace App\Http\Middleware;

use App\Models\VisitorSession;
use App\Services\GeoIpService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Visitor analytics tracking for the admin dashboard.
 *
 * Gives every browser a stable tp_visitor cookie and tracks each active
 * browsing window as a tp_session cookie. Sessions go stale after 30 minutes
 * of inactivity (a new one starts on the next hit), which lets the dashboard
 * chart unique vs. returning monthly visitors and average session lengths.
 *
 * All DB work is wrapped in try/catch so tracking can never break a page —
 * if the table is missing or the DB is down, requests just pass through.
 */
class TrackVisitor
{
    public const VISITOR_COOKIE = 'tp_visitor';

    public const SESSION_COOKIE = 'tp_session';

    /** Paths that should never count as visitor activity (health checks…). */
    private const SKIP_PATHS = ['/up', '/_debugbar', '/telescope', '/horizon'];

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->track($request);
        } catch (Throwable) {
            // Tracking must never take the app down.
        }

        return $next($request);
    }

    private function track(Request $request): void
    {
        $path = $request->path() !== '/' ? '/'.$request->path() : '/';

        if (in_array($path, self::SKIP_PATHS, true) || str_starts_with($path, '/api/')) {
            return;
        }

        $visitorId = (string) ($request->cookie(self::VISITOR_COOKIE) ?: Str::uuid());
        $sessionId = (string) ($request->cookie(self::SESSION_COOKIE) ?: Str::uuid());

        $now = now();
        $session = VisitorSession::where('session_id', $sessionId)->first();

        if ($session && $session->last_activity_at?->gt($now->copy()->subMinutes(VisitorSession::TIMEOUT_MINUTES))) {
            // Same active session — bump the counters and refresh both cookies
            // so a cleared visitor cookie can't silently split one browser into
            // two identities mid-session.
            $session->increment('hits');
            $session->update([
                'last_activity_at' => $now,
                'path' => $path,
            ]);

            Cookie::queue(Cookie::make(self::VISITOR_COOKIE, $visitorId, 60 * 24 * 365, '/'));
            Cookie::queue(Cookie::make(self::SESSION_COOKIE, $sessionId, 30, '/'));

            return;
        }

        // New session — or the previous one went stale, so close it first to
        // keep average session lengths honest. A brand-new session id is used
        // (the stale row stays in the table for history, so its id cannot be
        // reused — session_id is unique).
        if ($session) {
            $session->update(['ended_at' => $session->last_activity_at]);
        }

        $freshSessionId = (string) Str::uuid();
        $geo = app(GeoIpService::class)->resolve($request->ip());

        VisitorSession::create([
            'visitor_id' => $visitorId,
            'session_id' => $freshSessionId,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 400),
            'country' => $geo['country'],
            'region' => $geo['region'],
            'city' => $geo['city'],
            'path' => $path,
            'hits' => 1,
            'started_at' => $now,
            'last_activity_at' => $now,
        ]);

        // Persist / refresh the identifying cookies.
        Cookie::queue(Cookie::make(self::VISITOR_COOKIE, $visitorId, 60 * 24 * 365, '/'));
        Cookie::queue(Cookie::make(self::SESSION_COOKIE, $freshSessionId, 30, '/'));
    }
}
