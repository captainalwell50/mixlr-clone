<?php

namespace Tests\Unit;

use App\Services\Bible\KjvBibleService;
use Tests\TestCase;

class KjvBibleServiceTest extends TestCase
{
    public function test_resolves_john_3_16(): void
    {
        $bible = new KjvBibleService;
        $resolved = $bible->resolve('John 3:16');

        $this->assertNotNull($resolved);
        $this->assertSame('John 3:16', $resolved['ref']);
        $this->assertStringContainsString('God so loved the world', $resolved['text']);
    }

    public function test_resolves_alias_and_range(): void
    {
        $bible = new KjvBibleService;
        $resolved = $bible->resolve('1 Cor 13:4-5');

        $this->assertNotNull($resolved);
        $this->assertSame('1 Corinthians 13:4-5', $resolved['ref']);
        $this->assertStringContainsString('Charity suffereth long', $resolved['text']);
    }

    public function test_invalid_ref_returns_null(): void
    {
        $bible = new KjvBibleService;

        $this->assertNull($bible->resolve('NotABook 1:1'));
        $this->assertNull($bible->resolve('John 999:1'));
    }

    public function test_normalize_book_aliases(): void
    {
        $bible = new KjvBibleService;

        $this->assertSame('Psalms', $bible->normalizeBookName('Ps'));
        $this->assertSame('1 John', $bible->normalizeBookName('1 Jn'));
        $this->assertSame('Revelation', $bible->normalizeBookName('Rev'));
    }
}
