<?php

namespace App\Services;

use App\Models\NonConformity;
use Illuminate\Support\Collection;

/**
 * "Progress & Kriteria" summary of the inspection report, per department, following the
 * legacy report template:
 *
 *  - Action Progress: findings, closed, open, % closed and its score;
 *  - Kriteria: distinct clauses found per category (Minor / Major / Kritis), Total NC (their sum),
 *    Total Kriteria (all SMK3 criteria), % Ach = (Total Kriteria - Total NC) / Total Kriteria and its score;
 *  - Total Score = both scores. Score bands are in config smk3.report.scores.
 */
class FindingReport
{
    /**
     * @param  Collection<int, NonConformity>  $findings
     * @return array{rows: list<array<string, int|float|string>>, total: array<string, int|float|string>, status: array{open: int, closed: int}}
     */
    public function summary(Collection $findings, int $totalCriteria, string $totalLabel): array
    {
        $multiplePlants = $findings->pluck('pabrik')->unique()->count() > 1;

        $rows = $findings
            ->groupBy(fn (NonConformity $finding) => $finding->pabrik.'|'.$finding->departemen)
            ->map(function (Collection $group) use ($totalCriteria, $multiplePlants) {
                $first = $group->first();
                $label = $first->departmentName().($multiplePlants ? ' ('.strtoupper((string) $first->pabrik).')' : '');

                return $this->row($label, $group, $totalCriteria);
            })
            ->sortBy('departemen', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $closed = $findings->filter(fn (NonConformity $finding) => $finding->isClosed())->count();

        return [
            'rows' => $rows,
            'total' => $this->row($totalLabel, $findings, $totalCriteria),
            'status' => ['open' => $findings->count() - $closed, 'closed' => $closed],
        ];
    }

    /**
     * @param  Collection<int, NonConformity>  $findings
     * @return array<string, int|float|string>
     */
    private function row(string $label, Collection $findings, int $totalCriteria): array
    {
        $count = $findings->count();
        $closed = $findings->filter(fn (NonConformity $finding) => $finding->isClosed())->count();
        $closedPercent = $count ? round($closed / $count * 100, 2) : 0.0;

        // a clause found several times counts once per category
        $perCategory = collect(config('smk3.categories'))->mapWithKeys(fn (string $category) => [
            $category => $findings->where('kategori', $category)->pluck('no_klausul')->filter()->unique()->count(),
        ]);
        $totalNc = $perCategory->sum();
        $achievement = $totalCriteria ? round(max(0, $totalCriteria - $totalNc) / $totalCriteria * 100, 2) : 0.0;

        $closedScore = $this->score($closedPercent, 'closed');
        $achievementScore = $this->score($achievement, 'achievement');

        return [
            'departemen' => $label,
            'temuan' => $count,
            'closed' => $closed,
            'open' => $count - $closed,
            'closed_percent' => $closedPercent,
            'closed_score' => $closedScore,
            'minor' => $perCategory['Minor'] ?? 0,
            'major' => $perCategory['Major'] ?? 0,
            'kritis' => $perCategory['Kritis'] ?? 0,
            'total_nc' => $totalNc,
            'total_kriteria' => $totalCriteria,
            'achievement' => $achievement,
            'achievement_score' => $achievementScore,
            'total_score' => $closedScore + $achievementScore,
        ];
    }

    private function score(float $percent, string $band): int
    {
        foreach (config("smk3.report.scores.{$band}") as $minimum => $score) {
            if ($percent >= $minimum) {
                return $score;
            }
        }

        return 0;
    }
}
