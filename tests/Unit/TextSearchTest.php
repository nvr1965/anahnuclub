<?php

namespace Tests\Unit;

use App\Support\TextSearch;
use PHPUnit\Framework\TestCase;

class TextSearchTest extends TestCase
{
    public function test_terms_are_small_letters_without_russian_endings(): void
    {
        $this->assertSame(['новост'], TextSearch::terms('Новости'));
        $this->assertSame(['новост', 'израил'], TextSearch::terms('  Новости, Израиля! '));
        $this->assertSame(['haifa', 'friends'], TextSearch::terms('Haifa friends'));
        $this->assertSame(['мир'], TextSearch::terms('Мир'));
        $this->assertSame(['c++'], TextSearch::terms('C++'));
    }

    public function test_regex_matches_any_case_and_escapes_symbols(): void
    {
        $this->assertSame('[нН][оО][вВ]', TextSearch::regex('нов'));
        $this->assertSame('[еёЕЁ][лЛ]', TextSearch::regex('ёл'));
        $this->assertSame('[cC]\\+\\+', TextSearch::regex('c++'));
        $this->assertMatchesRegularExpression('/'.TextSearch::regex('новост').'/u', 'Интересные НОВОСТИ');
    }

    public function test_matches_needs_every_term(): void
    {
        $this->assertTrue(TextSearch::matches('Политика конфиденциальности', TextSearch::terms('конфиденциальность')));
        $this->assertFalse(TextSearch::matches('Политика', ['политик', 'мир']));
    }
}
