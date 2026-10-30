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
 *  - Penyimpangan: findings per category. A clause found as Minor at least MINOR_TO_MAJOR times in
 *    one department counts as an extra Major;
 *  - Score: 0 with any Kritis, 50 with any Major, else (criteria - Minor) / criteria × 100;
 *  - Nilai SMK3: "satisfactory" when there is no Kritis / Major and Score >= SATISFACTORY;
 *  - Total Ranking: 0 with any Kritis, 50 with any Major, else (% closed + Score) / 2, where a
 *    department without findings counts as 100% closed.
 */
class ProgressMonitor
{
    public const MINOR_TO_MAJOR = 2;

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
        $penalty = match (true) {
            $counts['kritis'] > 0 => 0,
            $counts['major'] + $counts['converted_major'] > 0 => 50,
            default => null,
        };
        $score = $penalty ?? round(($criteria - $counts['minor']) / $criteria * 100, 2);

        return ['code' => $code, 'name' => $name] + $counts + [
            'score' => $score,
            'satisfactory' => $penalty === null && $score >= self::SATISFACTORY,
            // nothing to close counts as fully closed (the legacy query ranked such departments at 50)
            'ranking' => $penalty ?? round((($counts['total'] ? $counts['closed_percent'] : 100) + $score) / 2, 2),
        ];
    }

    /**
     * @param  Collection<int, NonConformity>  $findings
     * @return array<string, int|float>
     */
    private function counts(Collection $findings): array
    {
        $total = $findings->count();
        $closed = $findings->filter(fn (NonConformity $finding) => $finding->isClosed())->count();
        $minors = $findings->where('kategori', 'Minor');

        return [
            'total' => $total,
            'closed' => $closed,
            'open' => $total - $closed,
            'closed_percent' => $total ? round($closed / $total * 100, 2) : 0,
            'minor' => $minors->count(),
            'major' => $findings->where('kategori', 'Major')->count(),
            'kritis' => $findings->where('kategori', 'Kritis')->count(),
            // clauses found as Minor often enough in one department to count as a Major
            'converted_major' => $minors
                ->filter(fn (NonConformity $finding) => filled($finding->no_klausul))
                ->groupBy(fn (NonConformity $finding) => strtoupper((string) $finding->departemen).'|'.$finding->no_klausul)
                ->filter(fn (Collection $group) => $group->count() >= self::MINOR_TO_MAJOR)
                ->count(),
        ];
    }
}
