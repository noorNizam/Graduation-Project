<?php

namespace Tests\Unit;

use App\Application\Services\BilingualStemmer;
use PHPUnit\Framework\TestCase;

class BilingualStemmerTest extends TestCase
{
    public function test_arabic_word_forms_converge(): void
    {
        $this->assertSame('سباك', BilingualStemmer::stem('سباكة'));
        $this->assertSame('سباك', BilingualStemmer::stem('السباكة'));
        $this->assertSame('سباك', BilingualStemmer::stem('سباك'));
    }

    public function test_english_word_is_porter_stemmed(): void
    {
        $this->assertSame('plumb', BilingualStemmer::stem('Plumbing'));
        $this->assertSame('servic', BilingualStemmer::stem('services'));
        $this->assertSame('electr', BilingualStemmer::stem('electrical'));
    }

    public function test_blank_word_is_returned_unchanged(): void
    {
        $this->assertSame('', BilingualStemmer::stem(''));
        $this->assertSame('', BilingualStemmer::stem('  '));
    }
}
