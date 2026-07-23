<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Storage::fake();

    $this->account = Account::factory()->create();
    $this->user = User::factory()->create([
        'account_id' => $this->account->id,
    ]);
    $this->account->update(['owner_id' => $this->user->id]);
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);
});

/**
 * Mimic the browser client: encodeURIComponent → PHP rawurlencode for the
 * X-File-Name header value.
 */
function postEncodedChunkedUpload(string $fileName, string $content): TestResponse
{
    $size = strlen($content);

    return test()->actingAs(test()->user)->call(
        'POST',
        route('app.assets.store-chunked'),
        [], [], [],
        [
            'HTTP_CONTENT_RANGE' => 'bytes 0-'.($size - 1).'/'.$size,
            'HTTP_X_FILE_NAME' => rawurlencode($fileName),
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        $content,
    );
}

test('chunked upload accepts filename with en-dash when percent-encoded', function () {
    // Customer bug: en-dash (U+2013) is outside ISO-8859-1, so fetch() rejects
    // the raw X-File-Name header. The client percent-encodes; we decode here.
    $fileName = 'Corte 6 – Quantidade ou qualidade_ Os dois..png';
    $content = file_get_contents(__DIR__.'/../fixtures/1x1.png');

    $response = postEncodedChunkedUpload($fileName, $content);

    $response->assertSuccessful();
    $response->assertJson(['done' => true]);
    expect($this->workspace->getMedia('assets')->first()->original_filename)
        ->toBe(strtolower($fileName));
});

test('chunked upload accepts filename with emoji when percent-encoded', function () {
    $fileName = 'launch-🚀-photo.png';
    $content = file_get_contents(__DIR__.'/../fixtures/1x1.png');

    $response = postEncodedChunkedUpload($fileName, $content);

    $response->assertSuccessful();
    $response->assertJson(['done' => true]);
    expect($this->workspace->getMedia('assets')->first()->original_filename)
        ->toBe(strtolower($fileName));
});

test('chunked upload accepts filename with spaces and double-dot extension', function () {
    $fileName = 'my video file..png';
    $content = file_get_contents(__DIR__.'/../fixtures/1x1.png');

    $response = postEncodedChunkedUpload($fileName, $content);

    $response->assertSuccessful();
    expect($this->workspace->getMedia('assets')->first()->original_filename)
        ->toBe('my video file..png');
});

test('chunked upload still accepts plain ascii filename without encoding', function () {
    // Backwards compatible: rawurldecode is a no-op on plain ASCII names.
    $content = file_get_contents(__DIR__.'/../fixtures/1x1.png');
    $size = strlen($content);

    $response = $this->actingAs($this->user)->call(
        'POST',
        route('app.assets.store-chunked'),
        [], [], [],
        [
            'HTTP_CONTENT_RANGE' => 'bytes 0-'.($size - 1).'/'.$size,
            'HTTP_X_FILE_NAME' => 'plain-ascii.png',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        $content,
    );

    $response->assertSuccessful();
    expect($this->workspace->getMedia('assets')->first()->original_filename)
        ->toBe('plain-ascii.png');
});

test('chunked upload rejects unsupported extension even when percent-encoded', function () {
    $response = postEncodedChunkedUpload('malware – payload.exe', str_repeat('x', 100));

    $response->assertUnprocessable();
});
