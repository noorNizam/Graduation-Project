<?php

namespace Tests\Feature;

use App\Infrastructure\Models\QuerySynonym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordNetImportTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE_DIR = __DIR__.'/../fixtures/wordnet';

    public function test_import_creates_cross_lingual_pairs(): void
    {
        $this->artisan('wordnet:import', ['directory' => self::FIXTURE_DIR])
            ->assertExitCode(0);

        $this->assertDatabaseCount('query_synonyms', 6);

        $plumbing = QuerySynonym::where('word', 'plumbing')->first();
        $this->assertEquals('en', $plumbing->language);
        $this->assertContains('سباك', $plumbing->synonyms);

        $spabak = QuerySynonym::where('word', 'سباك')->first();
        $this->assertEquals('ar', $spabak->language);
        $this->assertContains('plumbing', $spabak->synonyms);
        $this->assertContains('plumber', $spabak->synonyms);
    }

    public function test_import_collapses_arabic_inflections(): void
    {
        $this->artisan('wordnet:import', ['directory' => self::FIXTURE_DIR])
            ->assertExitCode(0);

        $this->assertNull(QuerySynonym::where('word', 'السباكة')->first());
        $this->assertNull(QuerySynonym::where('word', 'سباكة')->first());
        $this->assertNotNull(QuerySynonym::where('word', 'سباك')->first());
    }

    public function test_import_is_idempotent(): void
    {
        $this->artisan('wordnet:import', ['directory' => self::FIXTURE_DIR])->assertExitCode(0);
        $this->artisan('wordnet:import', ['directory' => self::FIXTURE_DIR])->assertExitCode(0);

        $this->assertDatabaseCount('query_synonyms', 6);
    }

    public function test_import_fails_without_files(): void
    {
        $empty = storage_path('app/wordnet-empty');
        if (! is_dir($empty)) {
            mkdir($empty, 0777, true);
        }

        $this->artisan('wordnet:import', ['directory' => $empty])
            ->expectsOutputToContain('No .tsv files found')
            ->assertExitCode(1);

        @rmdir($empty);
    }
}
