<?php

declare(strict_types=1);

namespace App\Services;

use const PREG_SPLIT_NO_EMPTY;

/**
 * BM25 scorer with per-field weighting and snippet extraction.
 *
 * Standard BM25 formula per field f, term t, document d:
 *   score += weight_f × IDF(t) × (tf × (k1+1)) / (tf + k1 × (1 - b + b × dl/avgdl))
 *
 * k1 = 1.5  — term-frequency saturation (higher = slower saturation)
 * b  = 0.75 — length normalisation (1 = full normalisation, 0 = none)
 */
class BM25Scorer
{
    private const float K1 = 1.5;
    private const float B = 0.75;

    // Context window size (chars) around each match for extracts
    private const int EXTRACT_WINDOW = 80;

    /**
     * Score a set of documents against the given terms.
     *
     * Each document is an array of fields:
     *   [ 'fieldName' => ['text' => string, 'weight' => float], ... ]
     *
     * Returns array<docKey, array{score: float, extracts: string[]}>
     *
     * @param array<string|int, array<string, array{text: string, weight: float}>> $documents
     * @param string[] $terms (already lowercased)
     *
     * @return array<string|int, array{score: float, extracts: string[]}>
     */
    public function score(array $documents, array $terms): array
    {
        if ($documents === [] || $terms === []) {
            return array_map(fn(): array => ['score' => 0.0, 'extracts' => []], $documents);
        }

        $fieldNames = array_keys(reset($documents));
        $N = count($documents);

        // ── 1. Average document length per field ──────────────────────────────
        $avgdl = [];
        foreach ($fieldNames as $field) {
            $total = 0;
            foreach ($documents as $doc) {
                $total += $this->wordCount($doc[$field]['text'] ?? '');
            }
            $avgdl[$field] = $N > 0 ? $total / $N : 1.0;
        }

        // ── 2. Document frequency per (term, field) ───────────────────────────
        $df = [];
        foreach ($terms as $term) {
            foreach ($fieldNames as $field) {
                $count = 0;
                foreach ($documents as $doc) {
                    if ($this->tf(mb_strtolower($doc[$field]['text'] ?? ''), $term) > 0) {
                        ++$count;
                    }
                }
                $df[$term][$field] = $count;
            }
        }

        // ── 3. Score each document ────────────────────────────────────────────
        $results = [];
        foreach ($documents as $key => $doc) {
            $score = 0.0;
            $extracts = [];

            foreach ($fieldNames as $field) {
                $rawText = $doc[$field]['text'] ?? '';
                $text = mb_strtolower($rawText);
                $weight = $doc[$field]['weight'];
                $dl = $this->wordCount($rawText);
                $avg = max($avgdl[$field], 1);
                $lenNorm = 1 - self::B + self::B * ($dl / $avg);

                foreach ($terms as $term) {
                    $tf = $this->tf($text, $term);
                    if ($tf === 0) {
                        continue;
                    }

                    // IDF with smoothing (Robertson/Sparck Jones variant)
                    $dfVal = $df[$term][$field];
                    $idf = log(($N - $dfVal + 0.5) / ($dfVal + 0.5) + 1);

                    // Saturated TF
                    $tfSat = ($tf * (self::K1 + 1)) / ($tf + self::K1 * $lenNorm);

                    $score += $weight * $idf * $tfSat;

                    // Extract snippet from this field around each match
                    foreach ($this->extractSnippets($rawText, $term) as $snippet) {
                        $extracts[] = $snippet;
                    }
                }
            }

            $results[$key] = [
                'score' => round($score, 4),
                'extracts' => array_values(array_unique($extracts)),
            ];
        }

        return $results;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function wordCount(string $text): int
    {
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return max(1, count($words ?: []));
    }

    private function tf(string $lowerText, string $lowerTerm): int
    {
        return substr_count($lowerText, $lowerTerm);
    }

    /**
     * Returns up to 2 snippets per term showing context around each match.
     *
     * @return string[]
     */
    private function extractSnippets(string $text, string $lowerTerm): array
    {
        $lower = mb_strtolower($text);
        $termLen = mb_strlen($lowerTerm);
        $snippets = [];
        $pos = 0;
        $found = 0;

        while ($found < 2 && ($pos = mb_strpos($lower, $lowerTerm, $pos)) !== false) {
            $start = max(0, $pos - self::EXTRACT_WINDOW);
            $end = min(mb_strlen($text), $pos + $termLen + self::EXTRACT_WINDOW);

            $snippet = ($start > 0 ? '…' : '') .
                mb_substr($text, $start, $end - $start) .
                ($end < mb_strlen($text) ? '…' : '');

            $snippets[] = $snippet;
            $pos += $termLen;
            ++$found;
        }

        return $snippets;
    }
}
