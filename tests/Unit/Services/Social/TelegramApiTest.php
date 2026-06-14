<?php

declare(strict_types=1);

use App\Services\Social\Telegram\TelegramApi;

beforeEach(function () {
    config([
        'trypost.platforms.telegram.bot_token' => 'TESTTOKEN',
        'trypost.platforms.telegram.api' => 'https://api.telegram.org',
    ]);
});

it('builds a method endpoint from the token and host', function () {
    expect(TelegramApi::endpoint('sendMessage'))
        ->toBe('https://api.telegram.org/botTESTTOKEN/sendMessage');
});

it('builds a file download url', function () {
    expect(TelegramApi::fileUrl('photos/file_1.jpg'))
        ->toBe('https://api.telegram.org/file/botTESTTOKEN/photos/file_1.jpg');
});

it('trims a trailing slash from the configured host', function () {
    config(['trypost.platforms.telegram.api' => 'https://api.telegram.org/']);

    expect(TelegramApi::endpoint('getChat'))
        ->toBe('https://api.telegram.org/botTESTTOKEN/getChat');
});
