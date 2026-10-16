<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Bot;

/**
 * Builds Send API message payloads. Lengths are clipped to Meta's limits, since
 * one overlong title makes the whole message fail.
 *
 * @see https://developers.facebook.com/docs/messenger-platform/send-messages/templates
 */
final class MessageFactory
{
    public const RATIO_SQUARE = 'square';

    public const RATIO_HORIZONTAL = 'horizontal';

    private const MAX_TEXT = 2000;

    private const MAX_BUTTON_TEXT = 640;

    private const MAX_TITLE = 80;

    private const MAX_BUTTON_TITLE = 20;

    private const MAX_BUTTONS = 3;

    private const MAX_ELEMENTS = 10;

    /** @return array<string, mixed> */
    public function text(string $text): array
    {
        return ['text' => $this->clip($text, self::MAX_TEXT)];
    }

    /**
     * @param array<int, array<string, string>> $buttons
     *
     * @return array<string, mixed>
     */
    public function buttons(string $text, array $buttons): array
    {
        return [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'button',
                    'text' => $this->clip($text, self::MAX_BUTTON_TEXT),
                    'buttons' => array_slice(array_values($buttons), 0, self::MAX_BUTTONS),
                ],
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $elements
     *
     * @return array<string, mixed>
     */
    public function carousel(array $elements, string $ratio = self::RATIO_SQUARE): array
    {
        return [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'generic',
                    'image_aspect_ratio' => $ratio,
                    'elements' => array_slice(array_values($elements), 0, self::MAX_ELEMENTS),
                ],
            ],
        ];
    }

    /**
     * @param array<int, array<string, string>> $buttons
     *
     * @return array<string, mixed>
     */
    public function element(string $title, string $subtitle = '', ?string $imageUrl = null, array $buttons = []): array
    {
        $element = ['title' => $this->clip($title, self::MAX_TITLE)];

        if (trim($subtitle) !== '') {
            $element['subtitle'] = $this->clip($subtitle, self::MAX_TITLE);
        }

        if ($imageUrl !== null && $imageUrl !== '') {
            $element['image_url'] = $imageUrl;
        }

        if ($buttons !== []) {
            $element['buttons'] = array_slice(array_values($buttons), 0, self::MAX_BUTTONS);
        }

        return $element;
    }

    /**
     * @param array<string, scalar> $payload
     *
     * @return array<string, string>
     */
    public function postbackButton(string $title, array $payload): array
    {
        return [
            'type' => 'postback',
            'title' => $this->clip($title, self::MAX_BUTTON_TITLE),
            'payload' => json_encode($payload, \JSON_THROW_ON_ERROR),
        ];
    }

    /** @return array<string, string> */
    public function urlButton(string $title, string $url): array
    {
        return [
            'type' => 'web_url',
            'title' => $this->clip($title, self::MAX_BUTTON_TITLE),
            'url' => $url,
            'webview_height_ratio' => 'full',
        ];
    }

    private function clip(string $text, int $length): string
    {
        $text = trim($text);

        return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
    }
}
