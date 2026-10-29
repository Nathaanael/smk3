<?php

namespace App\Services;

use App\Models\NonConformity;
use App\Models\Smk3Criterion;
use Illuminate\Support\Facades\Cache;

/**
 * Suggests SMK3 clauses for a finding description. Three signals are combined:
 *
 *  1. keywords   – words an admin attached to a clause (kata_kunci), matched with typo tolerance;
 *                  covers words that appear nowhere else, e.g. "tikus".
 *  2. similar    – k-nearest-neighbour vote: the past findings most similar to the description
 *                  (BM25) lend their clause number, weighted by similarity.
 *  3. criterion  – BM25 match against the criterion text itself, as a fallback.
 *
 * Everything runs in PHP on a cached index; the data set is a few hundred short texts.
 */
class ClauseSuggester
{
    private const WEIGHTS = ['keyword' => 3.0, 'similar' => 2.0, 'criterion' => 1.0];

    /** Combined score below which a clause is not worth suggesting (weak, one-word matches). */
    private const MIN_SCORE = 0.3;

    /** Past findings that take part in the vote. */
    private const NEIGHBOURS = 10;

    /** BM25 parameters (the usual defaults). */
    private const K1 = 1.2;

    private const B = 0.75;

    /** Indonesian function words and words every finding uses; they say nothing about the clause. */
    private const STOPWORDS = [
        'yang', 'dan', 'di', 'ke', 'dari', 'pada', 'untuk', 'dengan', 'ada', 'adanya', 'terdapat', 'ditemukan',
        'temuan', 'belum', 'sudah', 'masih', 'dalam', 'ini', 'itu', 'atau', 'serta', 'oleh', 'karena', 'akan', 'juga',
        'sebagai', 'tersebut', 'beberapa', 'sehingga', 'agar', 'saat', 'tanpa', 'lebih', 'bisa', 'dapat', 'harus',
        'perlu', 'telah', 'para', 'bila', 'jika', 'maka', 'setiap', 'hanya', 'lain', 'lainnya', 'secara', 'terlihat',
        'berada', 'sedang', 'tapi', 'namun', 'sangat', 'banyak', 'buah', 'unit', 'pcs', 'area', 'bagian', 'kondisi',
        'tidak', 'tdk', 'kurang',
        'the', 'of', 'and',
    ];

    /** Per suggest() call: how many neighbours voted for each clause. @var array<string, int> */
    private array $neighbourCount = [];

    /** Per suggest() call: keywords that matched, per clause. @var array<string, list<string>> */
    private array $keywordMatches = [];

    /**
     * @return list<array{no: string, text: string, score: float, reasons: array{keywords: list<string>, similar: int, criterion: bool}}>
     */
    public function suggest(string $description, int $limit = 5): array
    {
        $query = array_values(array_unique($this->tokens($description)));
        if ($query === []) {
            return [];
        }

        $index = $this->index();
        $signals = [
            'keyword' => $this->keywordScores($query, $index['keywords']),
            'similar' => $this->neighbourScores($query, $index['findings']),
            'criterion' => $this->criterionScores($query, $index['criteria']),
        ];

        $scores = [];
        foreach ($signals as $signal => $values) {
            foreach ($values as $number => $value) {
                $scores[$number] = ($scores[$number] ?? 0) + self::WEIGHTS[$signal] * $value;
            }
        }
        arsort($scores);

        $suggestions = [];
        foreach (array_slice($scores, 0, $limit, true) as $number => $score) {
            if (! isset($index['texts'][$number]) || $score < self::MIN_SCORE) {
                continue;
            }
            $suggestions[] = [
                'no' => (string) $number,
                'text' => $index['texts'][$number],
                'score' => round($score, 3),
                'reasons' => [
                    'keywords' => $this->keywordMatches[$number] ?? [],
                    'similar' => $this->neighbourCount[$number] ?? 0,
                    'criterion' => ($signals['criterion'][$number] ?? 0) > 0,
                ],
            ];
        }

        return $suggestions;
    }

    /**
     * Lower-case word tokens without stopwords; "-nya" is dropped ("kabelnya" -> "kabel").
     *
     * @return list<string>
     */
    public function tokens(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        $tokens = [];

        foreach ($words as $word) {
            if (mb_strlen($word) > 5 && str_ends_with($word, 'nya')) {
                $word = mb_substr($word, 0, -3);
            }
            if (mb_strlen($word) < 2 || ctype_digit($word) || in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            $tokens[] = $word;
        }

        return $tokens;
    }

    /**
     * @return array{
     *     texts: array<string, string>,
     *     keywords: array<string, list<array{keyword: string, tokens: list<string>}>>,
     *     findings: array{docs: list<array{key: string, tf: array<string, int>, length: int}>, df: array<string, int>, avg: float},
     *     criteria: array{docs: list<array{key: string, tf: array<string, int>, length: int}>, df: array<string, int>, avg: float},
     * }
     */
    private function index(): array
    {
        return Cache::remember($this->cacheKey(), now()->addDay(), function () {
            $criteria = Smk3Criterion::query()->get(['no_kriteria', 'kriteria', 'kata_kunci']);
            $known = $criteria->pluck('no_kriteria')->flip();

            $findings = NonConformity::query()->get(['deskripsi_temuan', 'no_klausul'])
                ->filter(fn (NonConformity $finding) => isset($known[trim((string) $finding->no_klausul)]))
                ->map(fn (NonConformity $finding) => [trim((string) $finding->no_klausul), (string) $finding->deskripsi_temuan]);

            return [
                'texts' => $criteria->pluck('kriteria', 'no_kriteria')->all(),
                'keywords' => $criteria->mapWithKeys(fn (Smk3Criterion $criterion) => [
                    $criterion->no_kriteria => array_map(
                        fn (string $keyword) => ['keyword' => $keyword, 'tokens' => $this->tokens($keyword) ?: [$keyword]],
                        $criterion->keywords(),
                    ),
                ])->filter()->all(),
                'findings' => $this->corpus($findings->all()),
                'criteria' => $this->corpus($criteria->map(fn (Smk3Criterion $criterion) => [$criterion->no_kriteria, $criterion->kriteria])->all()),
            ];
        });
    }

    /** Changes whenever a finding or criterion is added, edited or deleted. */
    private function cacheKey(): string
    {
        $stamp = fn (string $model) => $model::query()->toBase()->selectRaw('count(*) as total, max(updated_at) as changed')->first();
        $findings = $stamp(NonConformity::class);
        $criteria = $stamp(Smk3Criterion::class);

        return 'clause-suggester:'.md5(json_encode([$findings, $criteria]));
    }

    /**
     * @param  list<array{0: string, 1: string}>  $documents  [key, text]
     * @return array{docs: list<array{key: string, tf: array<string, int>, length: int}>, df: array<string, int>, avg: float}
     */
    private function corpus(array $documents): array
    {
        $docs = [];
        $df = [];

        foreach ($documents as [$key, $text]) {
            $tokens = $this->tokens($text);
            if ($tokens === []) {
                continue;
            }
            $tf = array_count_values($tokens);
            foreach (array_keys($tf) as $term) {
                $df[$term] = ($df[$term] ?? 0) + 1;
            }
            $docs[] = ['key' => (string) $key, 'tf' => $tf, 'length' => count($tokens)];
        }

        $avg = $docs ? array_sum(array_column($docs, 'length')) / count($docs) : 1.0;

        return ['docs' => $docs, 'df' => $df, 'avg' => $avg];
    }

    /**
     * BM25 score of every document for the query, keyed by document key (best score per key).
     *
     * @param  list<string>  $query
     * @param  array{docs: list<array{key: string, tf: array<string, int>, length: int}>, df: array<string, int>, avg: float}  $corpus
     * @return array<string, float>
     */
    private function bm25(array $query, array $corpus, bool $perDocument = false): array
    {
        $count = count($corpus['docs']);
        $scores = [];

        foreach ($corpus['docs'] as $position => $doc) {
            $score = 0.0;
            foreach ($query as $term) {
                $tf = $doc['tf'][$term] ?? 0;
                if (! $tf) {
                    continue;
                }
                $df = $corpus['df'][$term];
                $idf = log(1 + ($count - $df + 0.5) / ($df + 0.5));
                $score += $idf * ($tf * (self::K1 + 1)) / ($tf + self::K1 * (1 - self::B + self::B * $doc['length'] / $corpus['avg']));
            }
            if ($score <= 0) {
                continue;
            }

            $key = $perDocument ? $position : $doc['key'];
            $scores[$key] = max($scores[$key] ?? 0, $score);
        }

        return $scores;
    }

    /**
     * k-nearest-neighbour vote of the most similar past findings: the share of the vote per clause,
     * scaled by how well the best neighbour covers the description (weak matches count less).
     * Also fills how many neighbours voted for each clause (for the "used in N similar findings" hint).
     *
     * @param  list<string>  $query
     * @return array<string, float>
     */
    private function neighbourScores(array $query, array $corpus): array
    {
        $similar = $this->bm25($query, $corpus, perDocument: true);
        arsort($similar);
        $neighbours = array_slice($similar, 0, self::NEIGHBOURS, true);

        $votes = [];
        $this->neighbourCount = [];
        foreach ($neighbours as $position => $score) {
            $number = $corpus['docs'][$position]['key'];
            $votes[$number] = ($votes[$number] ?? 0) + $score;
            $this->neighbourCount[$number] = ($this->neighbourCount[$number] ?? 0) + 1;
        }

        $best = array_key_first($neighbours);
        $confidence = $best === null ? 0 : $this->share($query, $corpus['docs'][$best]);

        return array_map(fn (float $vote) => $vote * $confidence, $this->normalise($votes));
    }

    /**
     * Criterion text match: the share of the description's words found in the criterion
     * (one word out of four = 0.25), with BM25 only breaking ties between equal shares.
     *
     * @param  list<string>  $query
     * @return array<string, float>
     */
    private function criterionScores(array $query, array $corpus): array
    {
        $bm25 = $this->normalise($this->bm25($query, $corpus, perDocument: true));
        $scores = [];

        foreach ($bm25 as $position => $rank) {
            $doc = $corpus['docs'][$position];
            $scores[$doc['key']] = max($scores[$doc['key']] ?? 0, $this->share($query, $doc) + 0.01 * $rank);
        }

        return $scores;
    }

    /**
     * Share of the query words that appear in a document.
     *
     * @param  list<string>  $query
     * @param  array{tf: array<string, int>}  $doc
     */
    private function share(array $query, array $doc): float
    {
        return count(array_intersect($query, array_keys($doc['tf']))) / count($query);
    }

    /**
     * 1 per matched keyword (max 2) for each clause; also records which keywords matched.
     *
     * @param  list<string>  $query
     * @param  array<string, list<array{keyword: string, tokens: list<string>}>>  $keywords
     * @return array<string, float>
     */
    private function keywordScores(array $query, array $keywords): array
    {
        $scores = [];
        $this->keywordMatches = [];

        foreach ($keywords as $number => $entries) {
            foreach ($entries as $entry) {
                // a phrase keyword ("alat pelindung") needs every word to appear
                $matched = collect($entry['tokens'])->every(fn (string $token) => collect($query)->contains(fn (string $word) => $this->similarWord($word, $token)));
                if ($matched) {
                    $scores[$number] = min(2, ($scores[$number] ?? 0) + 1);
                    $this->keywordMatches[$number][] = $entry['keyword'];
                }
            }
        }

        return $scores;
    }

    /** Same word, a longer form of it ("tikus" / "tikusnya" already stripped, "kabel" / "kabelan"), or one typo away. */
    private function similarWord(string $word, string $keyword): bool
    {
        if ($word === $keyword) {
            return true;
        }
        if (mb_strlen($keyword) >= 4 && str_starts_with($word, $keyword)) {
            return true;
        }

        return mb_strlen($keyword) >= 5 && levenshtein($word, $keyword) <= 1;
    }

    /**
     * @param  array<string, float>  $scores
     * @return array<string, float>
     */
    private function normalise(array $scores): array
    {
        $max = $scores ? max($scores) : 0;

        return $max > 0 ? array_map(fn (float $score) => $score / $max, $scores) : [];
    }
}
