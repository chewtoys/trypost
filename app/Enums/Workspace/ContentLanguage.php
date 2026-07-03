<?php

declare(strict_types=1);

namespace App\Enums\Workspace;

/**
 * Languages a workspace can pick for AI-generated content. This is the single
 * source of truth for the supported set: request validation, the brand
 * analyzer's structured-output enum, homepage language detection, the AI image
 * prompt's language name, and the picker options in the UI all derive from it.
 *
 * The string value is the language code stored on the workspace and passed
 * straight to the content prompts (`content_language`). This is unrelated to
 * the application's UI locale — we are not translating the interface here.
 */
enum ContentLanguage: string
{
    case English = 'en';
    case PortugueseBrazil = 'pt-BR';
    case Spanish = 'es';
    case French = 'fr';
    case German = 'de';
    case Italian = 'it';
    case Dutch = 'nl';
    case Polish = 'pl';
    case Greek = 'el';
    case Japanese = 'ja';
    case Korean = 'ko';
    case Chinese = 'zh';
    case Russian = 'ru';
    case Turkish = 'tr';
    case Arabic = 'ar';

    public const DEFAULT = self::English;

    /**
     * The language's own name, shown in the content-language picker.
     */
    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::PortugueseBrazil => 'Português (Brasil)',
            self::Spanish => 'Español',
            self::French => 'Français',
            self::German => 'Deutsch',
            self::Italian => 'Italiano',
            self::Dutch => 'Nederlands',
            self::Polish => 'Polski',
            self::Greek => 'Ελληνικά',
            self::Japanese => '日本語',
            self::Korean => '한국어',
            self::Chinese => '中文',
            self::Russian => 'Русский',
            self::Turkish => 'Türkçe',
            self::Arabic => 'العربية',
        };
    }

    /**
     * The English name of the language, injected into AI image prompts so the
     * in-image text is rendered in the workspace's content language.
     */
    public function englishName(): string
    {
        return match ($this) {
            self::English => 'English',
            self::PortugueseBrazil => 'Brazilian Portuguese',
            self::Spanish => 'Spanish',
            self::French => 'French',
            self::German => 'German',
            self::Italian => 'Italian',
            self::Dutch => 'Dutch',
            self::Polish => 'Polish',
            self::Greek => 'Greek',
            self::Japanese => 'Japanese',
            self::Korean => 'Korean',
            self::Chinese => 'Chinese',
            self::Russian => 'Russian',
            self::Turkish => 'Turkish',
            self::Arabic => 'Arabic',
        };
    }

    /**
     * Whether the language is written right-to-left, which drives the `dir`
     * attribute on the document root so the whole UI mirrors for the locale.
     */
    public function isRtl(): bool
    {
        return $this === self::Arabic;
    }

    /**
     * Resolve a raw `<html lang>` value (e.g. "pt-PT", "zh-Hans") to a supported
     * language by matching its primary subtag, or null if none is supported.
     */
    public static function fromHtmlLang(string $lang): ?self
    {
        $subtag = self::primarySubtag($lang);

        if (strlen($subtag) < 2) {
            return null;
        }

        foreach (self::cases() as $language) {
            if (self::primarySubtag($language->value) === $subtag) {
                return $language;
            }
        }

        return null;
    }

    /**
     * The lowercased primary subtag of a BCP 47 language tag ("pt-BR" => "pt").
     */
    private static function primarySubtag(string $tag): string
    {
        return strtolower(explode('-', trim($tag), 2)[0]);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $language) => $language->value, self::cases());
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $language) => ['value' => $language->value, 'label' => $language->label()],
            self::cases(),
        );
    }
}
