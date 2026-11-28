<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Article HTML, kept to what an article needs: text formatting, lists,
 * links, pictures and tables. Scripts, event handlers, forms and embeds are
 * dropped, so nothing typed into the editor can run on the public site.
 * Inline styles stay: imported articles carry their colours and alignment
 * that way, and CSS cannot run code.
 */
final class ArticleHtml
{
    private const BLOCKS = ['p', 'br', 'hr', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'code', 'ul', 'ol', 'li', 'figure', 'figcaption', 'div'];

    private const INLINE = ['span', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'mark', 'small'];

    private const TABLE = ['table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col'];

    private static ?HtmlSanitizer $sanitizer = null;

    public static function clean(?string $html): string
    {
        return self::sanitizer()->sanitize((string) $html);
    }

    private static function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer !== null) {
            return self::$sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias()
            ->withMaxInputLength(2_000_000);

        foreach ([...self::BLOCKS, ...self::INLINE] as $element) {
            $config = $config->allowElement($element, ['class', 'style']);
        }

        foreach (self::TABLE as $element) {
            $config = $config->allowElement($element, ['class', 'style', 'colspan', 'rowspan', 'scope', 'span']);
        }

        $config = $config
            ->allowElement('a', ['href', 'title', 'target', 'class'])
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height', 'class', 'style'])
            // Links that open a new tab cannot reach back into this one.
            ->forceAttribute('a', 'rel', 'noopener noreferrer');

        return self::$sanitizer = new HtmlSanitizer($config);
    }
}
