<?php

namespace Tests\Unit;

use App\Support\Html;
use Tests\TestCase;

class HtmlTest extends TestCase
{
    public function test_old_bbcode_images_and_links_render(): void
    {
        $html = Html::clean("Игра\n[IMG]https://files.example.ru/a.jpg[/IMG] [url=https://b.example]сайт[/url] [b]важно[/b]");

        $this->assertStringContainsString('<img src="https://files.example.ru/a.jpg"', $html);
        $this->assertStringContainsString('<a href="https://b.example"', $html);
        $this->assertStringContainsString('<b>важно</b>', $html);
        $this->assertStringNotContainsString('[IMG]', $html);
    }

    public function test_plain_links_still_become_links_and_text_is_escaped(): void
    {
        $html = Html::clean('see https://ya.ru, 2 > 1');

        $this->assertStringContainsString('<a href="https://ya.ru"', $html);
        $this->assertStringContainsString('2 &gt; 1', $html);
    }
}
