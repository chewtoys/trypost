<?php

declare(strict_types=1);

use App\Services\Automation\FeedParser;

beforeEach(function () {
    $this->parser = new FeedParser;
});

it('parses an Atom (YouTube) feed with aliases and raw namespaced fields', function () {
    $item = $this->parser->parse(feedFixture('youtube_atom'))[0];

    // Aliases resolve cross-format: link comes from the Atom href attribute,
    // date from <published>, author from <author><name>.
    expect($item['title'])->toBe('Ninguém aguenta o TikTok Shop');
    expect($item['link'])->toBe('https://www.youtube.com/watch?v=bIQVeW4sTcE');
    expect($item['date'])->toBe('2026-06-15T20:24:32+00:00');
    expect($item['author'])->toBe('Beta Boechat');
    expect($item['key'])->toBe('yt:video:bIQVeW4sTcE');

    // Raw namespaced fields stay reachable.
    expect($item['yt_videoId'])->toBe('bIQVeW4sTcE');
    expect(data_get($item, 'media_group.media_thumbnail.url'))
        ->toBe('https://i3.ytimg.com/vi/bIQVeW4sTcE/hqdefault.jpg');
    expect(data_get($item, 'media_group.media_description'))
        ->toBe('Descrição completa do vídeo aqui.');
});

it('parses an RSS 2.0 feed with dc and content namespaces', function () {
    $item = $this->parser->parse(feedFixture('rss_namespaces'))[0];

    expect($item['title'])->toBe('Polish is here');
    expect($item['link'])->toBe('https://feedback.changelogfy.com/en/polish-is-here');
    expect($item['author'])->toBe('Paulo Castellano');
    expect($item['key'])->toBe('16092');

    // Extension fields the old parser threw away.
    expect($item['dc_creator'])->toBe('Paulo Castellano');
    expect($item['content_encoded'])->toBe('<p>You can now serve your changelogs fully translated in Polish.</p>');
    expect($item['categories'])->toBe(['Feature', 'i18n']);
});

it('parses a podcast feed exposing the enclosure and itunes fields', function () {
    $item = $this->parser->parse(feedFixture('podcast'))[0];

    expect(data_get($item, 'enclosure.url'))->toBe('https://cdn.changelog.com/the-changelog-682.mp3');
    expect(data_get($item, 'enclosure.type'))->toBe('audio/mpeg');
    expect($item['itunes_duration'])->toBe('1:46:28');
    expect($item['key'])->toBe('changelog.com/1/2829');
});

it('preserves backward-compatible RSS 2.0 alias keys', function () {
    $item = $this->parser->parse(feedFixture('rss_namespaces'))[0];

    // Automations saved before multi-format support reference these exact keys.
    expect($item)->toHaveKeys(['title', 'link', 'description', 'pubDate', 'key']);
    expect($item['pubDate'])->toBe($item['date']);
});

it('keeps a falsy element from a tag that only carries attributes', function () {
    $item = $this->parser->parse(feedFixture('podcast'))[0];

    // <itunes:image href="..."/> has no text — the attribute object is exposed.
    expect(data_get($item, 'itunes_image.href'))->toBe('https://cdn.changelog.com/cover.png');
});

it('returns null for a body that is not a valid feed', function () {
    expect($this->parser->parse('this is not xml at all'))->toBeNull();
});

it('returns an empty list for a valid but empty feed', function () {
    $empty = '<?xml version="1.0"?><rss version="2.0"><channel></channel></rss>';

    expect($this->parser->parse($empty))->toBe([]);
});
