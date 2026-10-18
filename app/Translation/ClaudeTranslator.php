<?php

namespace App\Translation;

use Anthropic\Client;
use App\Models\Language;
use RuntimeException;

class ClaudeTranslator implements Translator
{
    public function __construct(private Client $client, private string $model) {}

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
    {
        $target = Language::find($targetLocale)?->name ?? $targetLocale;

        $message = $this->client->messages->create(
            model: $this->model,
            maxTokens: 16000,
            outputConfig: ['effort' => 'low'],
            system: 'You translate posts and comments from a community social network. '
                .'Translate the user\'s text into '.$target.'. Keep the tone, names, emoji, links and any HTML tags exactly as they are. '
                .'If the text is already in '.$target.', return it unchanged. Reply with the translation only, no notes or quotes.',
            messages: [['role' => 'user', 'content' => $text]],
        );

        if ($message->stopReason === 'refusal') {
            throw new RuntimeException('Translation refused');
        }
        if ($message->stopReason === 'max_tokens') {
            // Never store a translation cut off halfway; the post stays in its original language.
            throw new RuntimeException('Translation too long');
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                return trim($block->text);
            }
        }

        throw new RuntimeException('Empty translation response');
    }

    public function name(): string
    {
        return 'claude';
    }
}
