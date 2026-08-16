<?php

namespace App\Application\Services;

use TeamTNT\TNTSearch\Stemmer\ArabicStemmer;
use TeamTNT\TNTSearch\Stemmer\PorterStemmer;
use TeamTNT\TNTSearch\Stemmer\StemmerInterface;

class BilingualStemmer implements StemmerInterface
{
    private static bool $arabicInitialized = false;

    public static function stem($word)
    {
        $word = trim((string) $word);

        if ($word === '') {
            return $word;
        }

        if (preg_match('/\p{Arabic}/u', $word)) {
            return self::stemArabic($word);
        }

        return PorterStemmer::stem(mb_strtolower($word));
    }

    public static function normalizeArabic(string $word): string
    {
        $word = preg_replace('/[\x{064B}-\x{0652}]/u', '', $word);
        $word = str_replace(['أ', 'إ', 'آ'], 'ا', $word);

        if (preg_match('/^(و?ال)/u', $word) && mb_strlen($word) >= 6) {
            $word = preg_replace('/^(و?ال)/u', '', $word);
        }

        if (preg_match('/ة$/u', $word) && mb_strlen($word) >= 5) {
            $word = mb_substr($word, 0, -1);
        }

        return $word;
    }

    private static function stemArabic(string $word): string
    {
        if (! self::$arabicInitialized) {
            new ArabicStemmer;
            self::$arabicInitialized = true;
        }

        $normalized = self::normalizeArabic($word);

        if ($normalized === '') {
            return $word;
        }

        return self::arabicStem($normalized);
    }

    private static function arabicStem(string $word): string
    {
        set_error_handler(
            static fn (int $severity): bool => $severity === E_DEPRECATED,
            E_DEPRECATED
        );

        try {
            $stem = ArabicStemmer::stem($word);
        } finally {
            restore_error_handler();
        }

        return is_string($stem) && $stem !== '' ? $stem : $word;
    }
}
