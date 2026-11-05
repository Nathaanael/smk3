<?php

namespace App\Services;

use App\Models\NonConformity;
use App\Models\Smk3Criterion;
use Illuminate\Support\Collection;

/**
 * "Progress Monitoring SMK3" recap of one plant over a period, per department, ported from the
 * legacy dashboard (smk3old MainController::index):
 *
 *  - Progress Temuan: findings, closed, open and % closed;
 *  - Penyimpangan: open findings per category (closed ones are settled and no longer count). A
 *    clause open as Minor at least MINOR_TO_MAJOR times in one department counts as an extra Major,
 *    on top of those Minor findings;
 *  - Score: 0 with any open Kritis, else (criteria - (Minor + Major × MAJOR_WEIGHT)) / criteria × 100,
 *    at least 0, where Major includes the extra Majors;
 *  - Nilai SMK3: "satisfactory" when there is no open Kritis / Major and Score >= SATISFACTORY;
 *  - Total Ranking: 0 with any open Kritis, else (% closed + Score) / 2, where a department without
 *    findings counts as 100% closed.
 */
class ProgressMonitor
{
    public const MINOR_TO_MAJOR = 2;

    /** How many Minor findings one Major weighs in the Score. */
    public const MAJOR_WEIGHT = 2;

    public const SATISFACTORY = 85;

    /** Criteria count the legacy system was built on, used while the criteria table is empty. */
    private const LEGACY_CRITERIA = 166;

    /**
     * @return array{rows: list<array<string, mixed>>, total: array<string, int|float>, criteria: int}
     */
    public function recap(string $plant, ?string $from, ?string $to): array
    {
        $findings = NonConformity::query()
            ->where('pabrik', $plant)
            ->when($from, fn ($query) => $query->whereDate('tanggal', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('tanggal', '<=', $to))
            ->get(['id', 'departemen', 'no_klausul', 'kategori', 'status']);

        $criteria = Smk3Criterion::count() ?: self::LEGACY_CRITERIA;
        $byDepartment = $findings->groupBy(fn (NonConformity $finding) => strtoupper((string) $finding->departemen));

        // every department of the plant is listed, also those without findings
        $rows = collect(NonConformity::departmentsFor($plant))
            ->map(fn (string $name, string $code) => $this->row($code, $name, $byDepartment->get(strtoupper($code), collect()), $criteria))
            ->values()
            ->all();

        $listed = collect($rows)->flatMap(fn (array $row) => $byDepartment->get(strtoupper($row['code']), collect()));

        return ['rows' => $rows, 'total' => $this->counts($listed), 'criteria' => $criteria];
    }

    /**
     * @param  Collection<int, NonConformity>  $findings
     * @return array<string, mixed>
     */
    private function row(string $code, string $name, Collection $findings, int $criteria): array
    {
        $counts = $this->counts($findings);
        $hasKritis = $counts['kritis'] > 0;
        $majors = $counts['major'] + $counts['converted_major'];
        $points = $counts['minor'] + $majors * self::MAJOR_WEIGHT;
        $score = $hasKritis ? 0 : max(0, round(($criteria - $points) / $criteria * 100, 2));

        return ['code' => $code, 'name' => $name] + $counts + [
            'score' => $score,
            'satisfactory' => ! $hasKritis && $majors === 0 && $score >= self::SATISFACTORY,
            // nothing to close counts as fully closed (the legacy query ranked such departments at 50)
            'ranking' => $hasKritis ? 0 : round((($counts['total'] ? $counts['closed_percent'] : 100) + $score) / 2, 2),
        ];
    }

    /**
     * @param  Collection<int, NonConformity>  $findings
     * @return array<string, mixed>
     */
    private function counts(Collection $findings): array
    {
        $total = $findings->count();
        $closed = $findings->filter(fn (NonConformity $finding) => $finding->isClosed())->count();
        $open = $findings->reject(fn (NonConformity $finding) => $finding->isClosed());
        $minors = $open->where('kategori', 'Minor');

        // clauses found as Minor often enough in one department to count as a Major
        $repeated = $minors
            ->filter(fn (NonConformity $finding) => filled($finding->no_klausul))
            ->groupBy(fn (NonConformity $finding) => strtoupper((string) $finding->departemen).'|'.$finding->no_klausul)
            ->filter(fn (Collection $group) => $group->count() >= self::MINOR_TO_MAJOR);

        return [
            'total' => $total,
            'closed' => $closed,
            'open' => $open->count(),
            'closed_percent' => $total ? round($closed / $total * 100, 2) : 0,
            'minor' => $minors->count(),
            'major' => $open->where('kategori', 'Major')->count(),
            'kritis' => $open->where('kategori', 'Kritis')->count(),
            'converted_major' => $repeated->count(),
            'converted_clauses' => $repeated
                ->map(fn (Collection $group) => ['clause' => $group->first()->no_klausul, 'count' => $group->count()])
                ->sortByDesc('count')
                ->values()
                ->all(),
        ];
    }
}
