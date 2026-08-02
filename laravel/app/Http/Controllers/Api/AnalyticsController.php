<?php

namespace App\Http\Controllers\Api;

use App\Enums\MatchStatus;
use App\Enums\QueueStatus;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\QueueEntry;
use App\Models\VisitorSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * GET /api/admin/analytics (auth:sanctum + can:manage-court)
 *
 * Visitor analytics + session statistics for the admin dashboard:
 *   - unique vs. returning monthly visitors, average session length, page views
 *   - visitors by region (country · region), 30-day traffic trend, peak hours
 *   - match totals, cumulative playing hours, popular playing hours, queue dwell
 *
 * Bucketing is done in PHP (not SQL dialect-specific group-by) so the same
 * queries run on SQLite (tests) and MySQL/PostgreSQL (production).
 */
class AnalyticsController extends Controller
{
    /** Cap a single session's reported length (stuck rows skew averages). */
    private const MAX_SESSION_MINUTES = 12 * 60;

    public function index(): JsonResponse
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $thirtyDaysAgo = $now->copy()->subDays(30)->startOfDay();

        $monthSessions = VisitorSession::where('started_at', '>=', $monthStart)->get();
        $visitorRows = VisitorSession::where('started_at', '>=', $thirtyDaysAgo)->get(['visitor_id', 'started_at', 'country', 'region']);

        $monthVisitorIds = $monthSessions->pluck('visitor_id')->unique();

        $returningIds = VisitorSession::where('started_at', '<', $monthStart)
            ->whereIn('visitor_id', $monthVisitorIds)
            ->distinct()
            ->pluck('visitor_id')
            ->all();

        $returning = count($returningIds);

        $overview = [
            'total_visitors' => (int) VisitorSession::distinct('visitor_id')->count('visitor_id'),
            'month_visitors' => $monthVisitorIds->count(),
            'new_this_month' => max(0, $monthVisitorIds->count() - $returning),
            'returning_this_month' => $returning,
            'month_sessions' => $monthSessions->count(),
            'page_views' => (int) $monthSessions->sum('hits'),
            'avg_session_minutes' => $this->averageSessionMinutes($thirtyDaysAgo, $now),
            'active_sessions_now' => (int) VisitorSession::whereNull('ended_at')
                ->where('last_activity_at', '>=', $now->copy()->subMinutes(VisitorSession::TIMEOUT_MINUTES))
                ->count(),
        ];

        $regions = $this->regions($visitorRows);

        $trends = $this->dailyTrend($now, $visitorRows);

        $peakHours = $this->hourlyBuckets(
            $visitorRows->pluck('started_at'),
            fn ($date) => (int) $date->format('G'),
            'visits',
            'hour',
        );

        $matchStats = $this->matchStats($monthStart);

        $queueStats = $this->queueStats($now);

        return response()->json([
            'generated_at' => $now->toISOString(),
            'overview' => $overview,
            'regions' => $regions,
            'trends' => $trends,
            'peak_hours' => $peakHours,
            'matches' => $matchStats,
            'queue' => $queueStats,
        ]);
    }

    /**
     * Average session length in minutes over the last 30 days. Still-open
     * sessions use their last activity as the end, capped at 12 hours.
     */
    private function averageSessionMinutes($since, $now): float
    {
        $sessions = VisitorSession::where('started_at', '>=', $since)
            ->get(['started_at', 'last_activity_at', 'ended_at']);

        if ($sessions->isEmpty()) {
            return 0.0;
        }

        $totalSeconds = $sessions->sum(function (VisitorSession $session) use ($now) {
            $end = $session->ended_at ?? $session->last_activity_at ?? $now;

            // Carbon 3 diffs are signed — abs() guards against odd data.
            return (int) abs($end->diffInSeconds($session->started_at));
        });

        $minutes = $totalSeconds / 60 / $sessions->count();

        return round(min($minutes, self::MAX_SESSION_MINUTES), 1);
    }

    /**
     * Visitors grouped by country · region (last 30 days), top 10.
     *
     * @param  Collection<int, VisitorSession>  $rows
     * @return array<int, array{label: string, visitors: int, sessions: int}>
     */
    private function regions(Collection $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $label = (string) ($row->country ?: 'Unknown');

            if ($row->region) {
                $label .= ' · '.$row->region;
            }

            $groups[$label] ??= ['visitors' => [], 'sessions' => 0];
            $groups[$label]['visitors'][] = $row->visitor_id;
            $groups[$label]['sessions'] += 1;
        }

        $regions = [];

        foreach ($groups as $label => $group) {
            $regions[] = [
                'label' => $label,
                'visitors' => count(array_unique($group['visitors'])),
                'sessions' => $group['sessions'],
            ];
        }

        usort($regions, fn (array $a, array $b) => $b['visitors'] <=> $a['visitors']);

        return array_slice($regions, 0, 10);
    }

    /**
     * Daily traffic trend over the last 30 days (zero-filled).
     *
     * @param  Collection<int, VisitorSession>  $rows
     * @return array<int, array{date: string, label: string, visits: int, visitors: int}>
     */
    private function dailyTrend($now, Collection $rows): array
    {
        $byDay = $rows->groupBy(fn (VisitorSession $row) => $row->started_at->format('Y-m-d'));

        $trend = [];

        for ($i = 29; $i >= 0; $i--) {
            $day = $now->copy()->startOfDay()->subDays($i);
            $key = $day->format('Y-m-d');
            $dayRows = $byDay->get($key, collect());

            $trend[] = [
                'date' => $day->format('M j'),
                'label' => $day->format('D, M j'),
                'visits' => $dayRows->count(),
                'visitors' => $dayRows->pluck('visitor_id')->unique()->count(),
            ];
        }

        return $trend;
    }

    /**
     * Turn a list of dates into 24 zero-filled hour buckets.
     *
     * @param  Collection<int, \Illuminate\Support\Carbon>  $dates
     * @return array<int, array<string, mixed>>
     */
    private function hourlyBuckets(Collection $dates, callable $hourOf, string $valueKey, string $labelKey): array
    {
        $buckets = array_fill(0, 24, 0);

        foreach ($dates as $date) {
            $buckets[$hourOf($date)] += 1;
        }

        return array_map(
            fn (int $hour, int $count) => [
                $labelKey => $hour,
                'label' => sprintf('%02d:00', $hour),
                $valueKey => $count,
            ],
            array_keys($buckets),
            $buckets,
        );
    }

    /**
     * Match totals: played, this month, cumulative + average play time, and
     * the hours of the day matches tend to start (popular playing hours).
     *
     * @return array<string, mixed>
     */
    private function matchStats($monthStart): array
    {
        $completed = Game::where('status', MatchStatus::Completed)
            ->whereNotNull('ended_at')
            ->get(['started_at', 'ended_at']);

        $totalMinutes = $completed->sum(
            fn (Game $match) => max(0, (int) round(abs($match->ended_at->diffInMinutes($match->started_at)))),
        );

        return [
            'matches_played' => Game::where('status', MatchStatus::Completed)->count(),
            'matches_this_month' => Game::where('status', MatchStatus::Completed)
                ->where('started_at', '>=', $monthStart)
                ->count(),
            'total_play_minutes' => $totalMinutes,
            'avg_match_minutes' => $completed->isEmpty()
                ? 0.0
                : round($totalMinutes / $completed->count(), 1),
            'play_hours' => $this->hourlyBuckets(
                $completed->pluck('started_at'),
                fn ($date) => (int) $date->format('G'),
                'matches',
                'hour',
            ),
        ];
    }

    /**
     * Queue activity: cumulative dwell time (joined → resolved), how many
     * units are on a court right now, and the combined active-session hours.
     *
     * @return array<string, mixed>
     */
    private function queueStats($now): array
    {
        $resolved = QueueEntry::whereIn('status', [QueueStatus::Completed, QueueStatus::Skipped])
            ->whereNotNull('joined_at')
            ->get(['joined_at', 'resolved_at']);

        $dwellMinutes = $resolved->sum(
            fn (QueueEntry $entry) => $entry->resolved_at
                ? max(0, (int) round(abs($entry->resolved_at->diffInMinutes($entry->joined_at))))
                : 0,
        );

        $onCourt = QueueEntry::where('status', QueueStatus::OnCourt)
            ->whereNotNull('joined_at')
            ->get(['joined_at']);

        $liveMinutes = $onCourt->sum(
            fn (QueueEntry $entry) => max(0, (int) round(abs($now->diffInMinutes($entry->joined_at)))),
        );

        $matchMinutes = (int) Game::where('status', MatchStatus::Completed)
            ->whereNotNull('ended_at')
            ->get(['started_at', 'ended_at'])
            ->sum(fn (Game $match) => max(0, (int) round(abs($match->ended_at->diffInMinutes($match->started_at)))));

        $totalActiveMinutes = $dwellMinutes + $liveMinutes + $matchMinutes;

        return [
            'resolved_entries' => $resolved->count(),
            'queue_dwell_minutes' => $dwellMinutes,
            'live_queue_minutes' => $liveMinutes,
            'on_court_now' => $onCourt->count(),
            'match_minutes' => $matchMinutes,
            'total_active_minutes' => $totalActiveMinutes,
        ];
    }
}
