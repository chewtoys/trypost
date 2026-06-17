<?php

declare(strict_types=1);

use App\Exceptions\Social\ErrorCategory;
use App\Exceptions\Social\RedditPublishException;
use Illuminate\Support\Facades\Http;

test('HTTP 401 maps to Permission category', function () {
    $response = Http::response(['message' => 'Unauthorized'], 401);
    $fakeResponse = Http::fake(['*' => $response])->post('https://oauth.reddit.com/api/submit');

    $exception = RedditPublishException::fromApiResponse($fakeResponse);

    expect($exception->category)->toBe(ErrorCategory::Permission)
        ->and($exception->userMessage)->toBe('Reddit rejected the request. Check that the account is connected and has permission to post.')
        ->and($exception->platformErrorCode)->toBe('401');
});

test('HTTP 403 maps to Permission category', function () {
    $response = Http::response(['message' => 'Forbidden'], 403);
    $fakeResponse = Http::fake(['*' => $response])->post('https://oauth.reddit.com/api/submit');

    $exception = RedditPublishException::fromApiResponse($fakeResponse);

    expect($exception->category)->toBe(ErrorCategory::Permission)
        ->and($exception->platformErrorCode)->toBe('403');
});

test('HTTP 429 maps to RateLimit category', function () {
    $response = Http::response([], 429);
    $fakeResponse = Http::fake(['*' => $response])->post('https://oauth.reddit.com/api/submit');

    $exception = RedditPublishException::fromApiResponse($fakeResponse);

    expect($exception->category)->toBe(ErrorCategory::RateLimit)
        ->and($exception->userMessage)->toBe('Reddit rate limit reached. Please try again shortly.')
        ->and($exception->platformErrorCode)->toBe('429');
});

test('HTTP 500 maps to ServerError category', function () {
    $response = Http::response([], 500);
    $fakeResponse = Http::fake(['*' => $response])->post('https://oauth.reddit.com/api/submit');

    $exception = RedditPublishException::fromApiResponse($fakeResponse);

    expect($exception->category)->toBe(ErrorCategory::ServerError)
        ->and($exception->userMessage)->toBe('Reddit is temporarily unavailable. Please try again later.')
        ->and($exception->platformErrorCode)->toBe('500');
});

test('json errors array uses first error human message', function () {
    $response = Http::response([
        'jquery' => [],
        'errors' => [['SUBREDDIT_NOEXIST', 'that subreddit does not exist', 'sr']],
    ], 200);
    $fakeResponse = Http::fake(['*' => $response])->post('https://oauth.reddit.com/api/submit');

    $exception = RedditPublishException::fromApiResponse($fakeResponse);

    expect($exception->category)->toBe(ErrorCategory::Unknown)
        ->and($exception->userMessage)->toBe('that subreddit does not exist');
});

test('empty errors array falls back to generic message', function () {
    $response = Http::response(['jquery' => [], 'errors' => []], 200);
    $fakeResponse = Http::fake(['*' => $response])->post('https://oauth.reddit.com/api/submit');

    $exception = RedditPublishException::fromApiResponse($fakeResponse);

    expect($exception->category)->toBe(ErrorCategory::Unknown)
        ->and($exception->userMessage)->toBe('An unknown Reddit error occurred (HTTP 200).');
});

test('previous throwable is forwarded', function () {
    $previous = new RuntimeException('original cause');
    $exception = new RedditPublishException(
        userMessage: 'something failed',
        category: ErrorCategory::Unknown,
        previous: $previous,
    );

    expect($exception->getPrevious())->toBe($previous)
        ->and($exception->getMessage())->toBe('something failed');
});

test('platform returns reddit', function () {
    $response = Http::response(['errors' => []], 400);
    $fakeResponse = Http::fake(['*' => $response])->post('https://oauth.reddit.com/api/submit');

    $exception = RedditPublishException::fromApiResponse($fakeResponse);

    expect($exception->platform())->toBe('reddit');
});

test('json.errors submit wrapper message takes priority over top-level errors', function () {
    $response = Http::response([
        'json' => ['errors' => [['SUBREDDIT_BANNED', 'that subreddit is banned', 'sr']]],
        'errors' => [['OTHER', 'fallback message', 'x']],
    ], 400);
    $fakeResponse = Http::fake(['*' => $response])->post('https://oauth.reddit.com/api/submit');

    $exception = RedditPublishException::fromApiResponse($fakeResponse);

    expect($exception->userMessage)->toBe('that subreddit is banned');
});

test('explanation field is used for api errors without errors array', function () {
    $response = Http::response([
        'reason' => 'BAD_SR_NAME',
        'explanation' => 'that name is taken',
        'message' => 'Bad Request',
    ], 400);
    $fakeResponse = Http::fake(['*' => $response])->post('https://oauth.reddit.com/api/submit');

    $exception = RedditPublishException::fromApiResponse($fakeResponse);

    expect($exception->userMessage)->toBe('that name is taken');
});

test('message field is used when no errors or explanation present', function () {
    $response = Http::response(['message' => 'Forbidden'], 404);
    $fakeResponse = Http::fake(['*' => $response])->post('https://oauth.reddit.com/api/submit');

    $exception = RedditPublishException::fromApiResponse($fakeResponse);

    expect($exception->userMessage)->toBe('Forbidden');
});
