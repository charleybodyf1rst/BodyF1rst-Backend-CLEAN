<?php

namespace App\Helpers;

class CSPHelper
{
    /**
     * Get the current request's CSP nonce.
     *
     * @return string|null
     */
    public static function getNonce(): ?string
    {
        $request = request();
        return $request ? $request->attributes->get('csp_nonce') : null;
    }

    /**
     * Generate a nonce attribute for script/style tags.
     *
     * @return string
     */
    public static function nonceAttr(): string
    {
        $nonce = self::getNonce();
        return $nonce ? "nonce=\"{$nonce}\"" : '';
    }

    /**
     * Render a nonce-aware tag, optionally escaping the contents.
     */
    private static function renderNonceTag(string $tag, string $content, bool $escape = true): string
    {
        $nonceAttr = self::nonceAttr();
        $body = $escape
            ? htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            : $content;
        $attribute = $nonceAttr ? " {$nonceAttr}" : '';

        return "<{$tag}{$attribute}>{$body}</{$tag}>";
    }

    /**
     * Generate a script tag with nonce. Content is escaped by default.
     */
    public static function scriptWithNonce(string $content, bool $escape = true): string
    {
        return self::renderNonceTag('script', $content, $escape);
    }

    /**
     * Alias for rendering escaped script content.
     */
    public static function scriptWithNonceSafe(string $content): string
    {
        return self::renderNonceTag('script', $content, true);
    }

    /**
     * Render trusted script content without escaping.
     */
    public static function scriptWithNonceRaw(string $content): string
    {
        return self::renderNonceTag('script', $content, false);
    }

    /**
     * Generate a style tag with nonce. Content is escaped by default.
     */
    public static function styleWithNonce(string $content, bool $escape = true): string
    {
        return self::renderNonceTag('style', $content, $escape);
    }

    /**
     * Alias for rendering escaped style content.
     */
    public static function styleWithNonceSafe(string $content): string
    {
        return self::renderNonceTag('style', $content, true);
    }

    /**
     * Render trusted style content without escaping.
     */
    public static function styleWithNonceRaw(string $content): string
    {
        return self::renderNonceTag('style', $content, false);
    }
}
