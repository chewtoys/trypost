<?php

declare(strict_types=1);

namespace App\Services\Automation;

use SimplePie\Item;
use SimplePie\SimplePie;
use Throwable;

/**
 * Parses an RSS 2.0 / Atom 1.0 / RDF feed body into normalized item arrays.
 *
 * Each item carries two merged layers:
 *  - **Aliases**: stable cross-format keys (title, link, date, content, author, …)
 *    resolved through SimplePie's normalized getters, so a downstream node works the
 *    same whether the feed is RSS or Atom.
 *  - **Raw**: every namespaced extension tag (yt:videoId, media:thumbnail,
 *    dc:creator, content:encoded, …) flattened with a `prefix_localname` convention,
 *    so feed-specific data stays reachable via `{{ fetched.<field> }}`.
 *
 * Aliases win on key collisions. The HTTP fetch and SSRF guard stay in the caller;
 * the body is handed to SimplePie via `set_raw_data()` so the SSRF guard isn't bypassed.
 */
class FeedParser
{
    /**
     * Namespace URI → short prefix for the raw layer.
     */
    private const NS_PREFIX = [
        'http://search.yahoo.com/mrss/' => 'media',
        'http://www.youtube.com/xml/schemas/2015' => 'yt',
        'http://purl.org/dc/elements/1.1/' => 'dc',
        'http://purl.org/dc/terms/' => 'dc',
        'http://purl.org/rss/1.0/modules/content/' => 'content',
        'http://www.itunes.com/dtds/podcast-1.0.dtd' => 'itunes',
        'https://podcastindex.org/namespace/1.0' => 'podcast',
        'http://purl.org/rss/1.0/modules/slash/' => 'slash',
        'http://wellformedweb.org/CommentAPI/' => 'wfw',
        'http://www.georss.org/georss' => 'georss',
    ];

    /**
     * Namespaces treated as the feed "core" — their tags get no prefix, so RSS and
     * Atom land on the same raw key names (and are then overridden by aliases).
     */
    private const NS_CORE = [
        '',
        'http://www.w3.org/2005/Atom',
        'http://purl.org/rss/1.0/',
        'http://backend.userland.com/rss2',
        'http://my.netscape.com/rdf/simple/0.9/',
    ];

    /**
     * @return list<array<string, mixed>>|null Null when the body is not a usable feed
     *                                         (invalid XML, or any parse failure).
     */
    public function parse(string $body): ?array
    {
        try {
            $feed = new SimplePie;
            $feed->enable_cache(false);
            $feed->set_raw_data($body);

            if (! @$feed->init()) {
                return null;
            }

            return array_map(fn (Item $item): array => $this->normalize($item), $feed->get_items());
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(Item $item): array
    {
        $enclosure = $item->get_enclosure();

        $aliases = [
            'key' => $item->get_id(false, false) ?: $item->get_permalink(),
            'title' => $item->get_title(),
            'link' => $item->get_permalink(),
            'date' => $item->get_date('c'),
            'pubDate' => $item->get_date('c'),
            'content' => $item->get_content(),
            'description' => $item->get_description(),
            'author' => $item->get_author()?->get_name(),
            'id' => $item->get_id(false, false),
            'image' => $enclosure?->get_thumbnail() ?: null,
            'categories' => array_values(array_filter(array_map(
                fn ($category) => $category->get_label(),
                $item->get_categories() ?? [],
            ))),
            'enclosure' => $enclosure === null ? null : array_filter([
                'url' => $enclosure->get_link(),
                'type' => $enclosure->get_type(),
                'length' => $enclosure->get_length(),
            ], fn ($value) => $value !== null),
        ];

        // Aliases win on collision, so merge them over the raw layer.
        return array_merge($this->rawFields($item), $aliases);
    }

    /**
     * @return array<string, mixed>
     */
    private function rawFields(Item $item): array
    {
        return $this->flattenChildren((array) data_get($item->data, 'child', []));
    }

    /**
     * @param  array<string, mixed>  $children  Namespace-URI keyed tag map.
     * @return array<string, mixed>
     */
    private function flattenChildren(array $children): array
    {
        $out = [];

        foreach ($children as $namespace => $tags) {
            $prefix = $this->prefixFor((string) $namespace);

            foreach ($tags as $tag => $nodes) {
                $key = $prefix === '' ? (string) $tag : "{$prefix}_{$tag}";
                $values = array_map(fn ($node) => $this->flattenNode((array) $node), $nodes);
                $out[$key] = count($values) === 1 ? $values[0] : $values;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function flattenNode(array $node): mixed
    {
        $attribs = $this->attributes($node);
        $children = (array) data_get($node, 'child', []);

        if ($children !== []) {
            return array_merge($this->flattenChildren($children), $attribs);
        }

        $text = trim((string) data_get($node, 'data', ''));

        return match (true) {
            $text !== '' && $attribs === [] => $text,
            $text === '' && $attribs !== [] => $attribs,
            $text !== '' && $attribs !== [] => array_merge(['_text' => $text], $attribs),
            default => $text,
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, string>
     */
    private function attributes(array $node): array
    {
        $out = [];

        foreach ((array) data_get($node, 'attribs', []) as $attrs) {
            foreach ((array) $attrs as $name => $value) {
                $out[(string) $name] = (string) $value;
            }
        }

        return $out;
    }

    private function prefixFor(string $namespace): string
    {
        if (in_array($namespace, self::NS_CORE, true)) {
            return '';
        }

        return self::NS_PREFIX[$namespace] ?? '';
    }
}
