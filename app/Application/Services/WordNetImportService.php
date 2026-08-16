<?php

namespace App\Application\Services;

use App\Domain\Repositories\QuerySynonymRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WordNetImportService
{
    public function __construct(
        private QuerySynonymRepositoryInterface $repository
    ) {}

    /**
     * Imports cross-lingual WordNet synonym pairs from tab-separated files.
     *
     * Expected format (one pair per line, "#" comments allowed):
     *   <synset_id>\t<language>\t<lemma>
     * where language is "ar" or "en". Lemmas are normalized before storage,
     * so Arabic inflected forms (e.g. "السباكة" / "سباكة") collapse to a
     * single lookup key and become cross-lingual siblings (e.g. "سباك" -> "plumbing").
     */
    public function importFromDirectory(string $directory): array
    {
        $files = glob($directory.'/*.tsv') ?: [];

        if ($files === []) {
            return ['success' => false, 'message' => "No .tsv files found in {$directory}"];
        }

        $synsets = $this->readFiles($files);

        $rows = [];
        foreach ($synsets as $words) {
            $normalizedByLanguage = [];
            foreach ($words as $language => $lemmas) {
                foreach ($lemmas as $lemma) {
                    $normalized = $this->normalize($lemma);

                    if ($normalized !== '') {
                        $normalizedByLanguage[$language][] = $normalized;
                    }
                }
            }

            $siblings = [];
            foreach ($normalizedByLanguage as $list) {
                foreach ($list as $word) {
                    $siblings[$word] = true;
                }
            }

            $siblings = array_keys($siblings);

            if (count($siblings) < 2) {
                continue;
            }

            foreach ($normalizedByLanguage as $language => $list) {
                foreach ($list as $word) {
                    $synonyms = array_values(array_diff($siblings, [$word]));

                    $rows[$word]['word'] = $word;
                    $rows[$word]['language'] ??= $language;
                    $rows[$word]['synonyms'] = array_values(array_unique(array_merge(
                        $rows[$word]['synonyms'] ?? [],
                        $synonyms
                    )));
                }
            }
        }

        if ($rows === []) {
            return ['success' => false, 'message' => 'No cross-lingual synonym pairs could be extracted'];
        }

        DB::transaction(function () use ($rows) {
            $now = now()->toDateTimeString();
            $this->repository->truncate();
            $this->repository->insertRows(array_map(function (array $row) use ($now) {
                return [
                    'word' => $row['word'],
                    'language' => $row['language'],
                    'synonyms' => json_encode(array_values(array_unique($row['synonyms']))),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $rows));
        });

        return [
            'success' => true,
            'data' => [
                'synsets' => count($synsets),
                'words' => count($rows),
            ],
        ];
    }

    /**
     * @return array<string, array<string, array<int, string>>>
     */
    private function readFiles(array $files): array
    {
        $synsets = [];

        foreach ($files as $file) {
            $handle = fopen($file, 'r');

            if ($handle === false) {
                Log::warning("WordNetImportService: could not open {$file}");

                continue;
            }

            $lineNumber = 0;

            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $line = trim($line);

                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                $parts = explode("\t", $line);

                if (count($parts) !== 3) {
                    Log::warning("WordNetImportService: malformed line {$lineNumber} in {$file}");

                    continue;
                }

                [$synsetId, $language, $lemma] = $parts;
                $language = mb_strtolower(trim($language));
                $lemma = trim($lemma);

                if (! in_array($language, ['ar', 'en'], true) || $lemma === '') {
                    continue;
                }

                $synsets[$synsetId][$language][] = $lemma;
            }

            fclose($handle);
        }

        return $synsets;
    }

    private function normalize(string $word): string
    {
        $word = trim($word);

        if ($word === '') {
            return '';
        }

        return preg_match('/\p{Arabic}/u', $word)
            ? BilingualStemmer::normalizeArabic($word)
            : mb_strtolower($word);
    }
}
