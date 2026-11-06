<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\NonConformity;
use App\Models\Smk3Criterion;
use App\Models\User;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The charts of the dashboard, for one plant over a period: Score per department, open findings
 * per department and category, repeated Minor clauses, repair photo progress, findings per month
 * and the age of open findings.
 *
 * Like the Progress Monitoring recap, only departments listed for the plant count, so totals match.
 * Auditees are limited to what the findings list shows them: the Score of their own departments,
 * and otherwise their visible findings (own departments plus findings they are the PIC of).
 *
 * Each chart is returned as ['empty' => bool, 'config' => chart options, 'table' => table view].
 */
class DashboardSummary
{
    /** Age groups of open findings in days: label => highest age in the group. */
    private const AGE_GROUPS = ['0–30' => 30, '31–60' => 60, '61–90' => 90, '> 90' => PHP_INT_MAX];

    /** Stacking order of the category series, most severe first. */
    private const CATEGORIES = ['Kritis' => 'kritis', 'Major' => 'major', 'Minor' => 'minor'];

    /** Repeated clauses in the chart; the table view lists all of them. */
    public const REPEATED_CHART_LIMIT = 7;

    public function __construct(private ProgressMonitor $monitor) {}

    /**
     * @return array<string, array<string, mixed>> one entry per chart
     */
    public function build(User $user, string $plant, ?string $from, ?string $to): array
    {
        $departments = collect(NonConformity::departmentsFor($plant))
            ->mapWithKeys(fn (string $name, string $code) => [strtoupper($code) => $name]);

        $findings = NonConformity::query()
            ->visibleTo($user)
            ->where('pabrik', $plant)
            ->inPeriod($from, $to)
            ->select(['id', 'tanggal', 'departemen', 'kategori', 'status'])
            ->withExists(['photos as has_repair_photo' => fn (Builder $query) => $query->where('field', 'foto_perbaikan')])
            ->get()
            ->filter(fn (NonConformity $finding) => $departments->has($this->departmentCode($finding)));

        $open = $findings->reject(fn (NonConformity $finding) => $finding->isClosed());
        $ownCodes = $user->otorisasi === Role::Auditee
            ? array_map('strtoupper', $user->departmentCodes())
            : $departments->keys()->all();
        $openCodes = $open->map(fn (NonConformity $finding) => $this->departmentCode($finding))->all();
        $shownDepartments = $departments->only([...$ownCodes, ...$openCodes]);

        $recapRows = collect($this->monitor->recap($plant, $from, $to)['rows'])
            ->filter(fn (array $row) => in_array(strtoupper($row['code']), $ownCodes, true));

        return [
            'score' => $this->score($recapRows),
            'open' => $this->openPerDepartment($open, $shownDepartments),
            'repeated' => $this->repeatedClauses($recapRows, $shownDepartments->count()),
            'repairPhotos' => $this->repairPhotos($open, $shownDepartments),
            'trend' => $this->trend($findings),
            'aging' => $this->aging($open),
        ];
    }

    /**
     * Clauses open as Minor often enough in one department to count as an extra Major: closing
     * these lifts the rating most. The chart shows the most frequent ones, the table all of them.
     *
     * @param  Collection<int, array<string, mixed>>  $recapRows
     * @param  int  $neighbourRows  rows of the department chart beside it, whose height it matches
     * @return array<string, mixed>
     */
    private function repeatedClauses(Collection $recapRows, int $neighbourRows): array
    {
        $rows = $recapRows
            ->flatMap(fn (array $row) => array_map(
                fn (array $clause) => $clause + ['department' => $row['name']],
                $row['converted_clauses'],
            ))
            ->sortByDesc('count')
            ->values();

        $criteria = Smk3Criterion::whereIn('no_kriteria', $rows->pluck('clause')->unique())->pluck('kriteria', 'no_kriteria');
        $top = $rows->take(self::REPEATED_CHART_LIMIT);

        return [
            'empty' => $rows->isEmpty(),
            'config' => [
                'categories' => $top->map(fn (array $row) => "{$row['clause']} · {$row['department']}")->all(),
                'horizontal' => true,
                'rows' => max($top->count(), $neighbourRows),
                'series' => [
                    ['key' => 'minor', 'name' => __('dashboard.repeated.count'), 'data' => $top->pluck('count')->all()],
                ],
            ],
            'table' => [
                'headers' => [__('nc.columns.clause'), __('nc.columns.department'), __('dashboard.repeated.count')],
                'rows' => $rows->map(fn (array $row) => [
                    ['text' => $row['clause'], 'description' => $criteria[$row['clause']] ?? null],
                    $row['department'],
                    $this->bar($row['count'], $rows->max('count')),
                ])->all(),
            ],
        ];
    }

    /**
     * Who an open finding waits for: the auditee while there is no repair photo, the auditor
     * (who closes findings by hand) once there is one.
     *
     * @param  Collection<int, NonConformity>  $open
     * @param  Collection<string, string>  $departments
     * @return array<string, mixed>
     */
    private function repairPhotos(Collection $open, Collection $departments): array
    {
        $byDepartment = $open->groupBy(fn (NonConformity $finding) => $this->departmentCode($finding));

        $rows = $departments
            ->map(function (string $name, string $code) use ($byDepartment) {
                $group = $byDepartment->get($code, collect());
                $withPhoto = $group->filter(fn (NonConformity $finding) => $finding->has_repair_photo)->count();

                return ['name' => $name, 'noPhoto' => $group->count() - $withPhoto, 'withPhoto' => $withPhoto];
            })
            ->sortByDesc(fn (array $row) => [$row['noPhoto'], $row['withPhoto']])
            ->values();

        return [
            'empty' => $open->isEmpty(),
            'config' => [
                'categories' => $rows->pluck('name')->all(),
                'horizontal' => true,
                'series' => [
                    ['key' => 'noPhoto', 'name' => __('dashboard.repairPhotos.noPhoto'), 'data' => $rows->pluck('noPhoto')->all()],
                    ['key' => 'withPhoto', 'name' => __('dashboard.repairPhotos.withPhoto'), 'data' => $rows->pluck('withPhoto')->all()],
                ],
            ],
            'table' => [
                'headers' => [__('nc.columns.department'), __('dashboard.repairPhotos.noPhoto'), __('dashboard.repairPhotos.withPhoto'), __('pm.total')],
                'rows' => $rows->map(fn (array $row) => [
                    $row['name'],
                    $row['noPhoto'],
                    $row['withPhoto'],
                    $this->bar($row['noPhoto'] + $row['withPhoto'], $open->groupBy(fn (NonConformity $finding) => $this->departmentCode($finding))->map->count()->max() ?? 0),
                ])->all(),
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $recapRows
     * @return array<string, mixed>
     */
    private function score(Collection $recapRows): array
    {
        $rows = $recapRows->sortBy('score')->values();

        $rating = fn (array $row) => __($row['satisfactory'] ? 'pm.satisfactory' : 'pm.poor');

        return [
            'empty' => $rows->isEmpty(),
            'config' => [
                'categories' => $rows->pluck('name')->all(),
                'values' => $rows->pluck('score')->all(),
                'satisfactory' => $rows->pluck('satisfactory')->all(),
                'ratings' => $rows->map($rating)->all(),
                'target' => ProgressMonitor::SATISFACTORY,
                'targetLabel' => __('dashboard.target', ['value' => ProgressMonitor::SATISFACTORY]),
                'seriesName' => __('pm.columns.score'),
                'ratingLabel' => __('pm.columns.rating'),
            ],
            'table' => [
                'headers' => [__('nc.columns.department'), __('pm.columns.score'), __('pm.columns.rating')],
                'rows' => $rows->map(fn (array $row) => [
                    $row['name'],
                    ['text' => $this->percent($row['score']), 'bar' => $row['score']],
                    ['text' => $rating($row), 'badge' => $row['satisfactory'] ? 'success' : 'error'],
                ])->all(),
            ],
        ];
    }

    /**
     * @param  Collection<int, NonConformity>  $open
     * @param  Collection<string, string>  $departments
     * @return array<string, mixed>
     */
    private function openPerDepartment(Collection $open, Collection $departments): array
    {
        $byDepartment = $open->groupBy(fn (NonConformity $finding) => $this->departmentCode($finding));

        // most severe on top; the sort is stable, so ties keep the department order
        $rows = $departments
            ->map(fn (string $name, string $code) => ['name' => $name] + $this->categoryCounts($byDepartment->get($code, collect())))
            ->sortByDesc(fn (array $row) => [$row['kritis'], $row['major'], $row['kritis'] + $row['major'] + $row['minor'] + $row['none']])
            ->values();

        return $this->categoryChart($open, $rows, __('nc.columns.department'), horizontal: true);
    }

    /**
     * @param  Collection<int, NonConformity>  $open
     * @return array<string, mixed>
     */
    private function aging(Collection $open): array
    {
        // tanggal has no time and the app clock runs on UTC, so count from the plants' own date
        $today = Carbon::parse(now(config('smk3.report.timezone'))->toDateString());

        $byGroup = $open->groupBy(function (NonConformity $finding) use ($today) {
            $age = max(0, (int) $finding->tanggal->diffInDays($today));

            return collect(self::AGE_GROUPS)->search(fn (int $highest) => $age <= $highest);
        });

        $rows = collect(array_keys(self::AGE_GROUPS))->map(fn (string $group) => [
            'name' => __('dashboard.aging.days', ['range' => $group]),
        ] + $this->categoryCounts($byGroup->get($group, collect())));

        return $this->categoryChart($open, $rows, __('dashboard.aging.age'), horizontal: false);
    }

    /**
     * @param  Collection<int, NonConformity>  $findings
     * @return array<string, mixed>
     */
    private function trend(Collection $findings): array
    {
        $byMonth = $findings->groupBy(fn (NonConformity $finding) => $finding->tanggal->format('Y-m'));

        // months without findings stay on the axis, so the time scale is not distorted
        $rows = $findings->isEmpty() ? collect() : collect(CarbonPeriod::create(
            $findings->min('tanggal')->startOfMonth(),
            '1 month',
            $findings->max('tanggal')->startOfMonth(),
        ))->map(function (CarbonInterface $month) use ($byMonth) {
            $group = $byMonth->get($month->format('Y-m'), collect());
            $closed = $group->filter(fn (NonConformity $finding) => $finding->isClosed())->count();

            return ['name' => $month->translatedFormat('M y'), 'closed' => $closed, 'open' => $group->count() - $closed];
        });

        return [
            'empty' => $rows->isEmpty(),
            'config' => [
                'categories' => $rows->pluck('name')->all(),
                'horizontal' => false,
                'series' => [
                    ['key' => 'closed', 'name' => __('nc.closed'), 'data' => $rows->pluck('closed')->all()],
                    ['key' => 'open', 'name' => __('nc.open'), 'data' => $rows->pluck('open')->all()],
                ],
            ],
            'table' => [
                'headers' => [__('dashboard.trend.month'), __('nc.closed'), __('nc.open'), __('pm.total')],
                'rows' => $rows->map(fn (array $row) => [
                    $row['name'],
                    $row['closed'],
                    $row['open'],
                    $this->bar($row['closed'] + $row['open'], $byMonth->map->count()->max() ?? 0),
                ])->all(),
            ],
        ];
    }

    /**
     * A stacked chart with one series per finding category.
     *
     * @param  Collection<int, NonConformity>  $open
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function categoryChart(Collection $open, Collection $rows, string $firstHeader, bool $horizontal): array
    {
        $series = array_flip(self::CATEGORIES);
        // findings without a category must not vanish, but the common case needs no empty series
        if ($rows->sum('none') > 0) {
            $series['none'] = __('dashboard.uncategorized');
        }

        $total = fn (array $row) => array_sum(array_map(fn (string $key) => $row[$key], array_keys($series)));
        $highest = $rows->max($total);

        return [
            'empty' => $open->isEmpty(),
            'config' => [
                'categories' => $rows->pluck('name')->all(),
                'horizontal' => $horizontal,
                'series' => collect($series)
                    ->map(fn (string $name, string $key) => ['key' => $key, 'name' => $name, 'data' => $rows->pluck($key)->all()])
                    ->values()
                    ->all(),
            ],
            'table' => [
                'headers' => [$firstHeader, ...array_values($series), __('pm.total')],
                'rows' => $rows->map(fn (array $row) => [
                    $row['name'],
                    ...array_map(fn (string $key) => $row[$key], array_keys($series)),
                    $this->bar($total($row), $highest),
                ])->all(),
            ],
        ];
    }

    /**
     * @param  Collection<int, NonConformity>  $findings
     * @return array{kritis: int, major: int, minor: int, none: int}
     */
    private function categoryCounts(Collection $findings): array
    {
        $counts = ['kritis' => 0, 'major' => 0, 'minor' => 0, 'none' => 0];

        foreach ($findings as $finding) {
            $counts[self::CATEGORIES[$finding->kategori] ?? 'none']++;
        }

        return $counts;
    }

    /**
     * A table cell with a small bar, sized against the highest value of its column.
     *
     * @return array{text: int, bar: int}
     */
    private function bar(int $value, int $highest): array
    {
        return ['text' => $value, 'bar' => $highest ? (int) round($value / $highest * 100) : 0];
    }

    private function departmentCode(NonConformity $finding): string
    {
        return strtoupper((string) $finding->departemen);
    }

    private function percent(int|float $value): string
    {
        $separator = app()->getLocale() === 'id' ? ',' : '.';

        return rtrim(rtrim(number_format($value, 2, $separator, ''), '0'), $separator).'%';
    }
}
