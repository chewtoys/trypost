<?php

declare(strict_types=1);

use App\Models\User;

/**
 * Inject a real image into the hidden file input and dispatch `change`, driving
 * the same flow a user's file selection would. The plugin's attach() sends
 * localPaths, which Playwright rejects over its websocket connection, so a
 * DataTransfer is used instead. The poll waits for the SPA to mount first.
 */
function selectPhoto(mixed $page): void
{
    $base64 = base64_encode((string) file_get_contents(base_path('tests/fixtures/blue-logo.png')));

    $page->script(<<<JS
        (async () => {
            const findInput = () => document.querySelector('input[type="file"]');
            for (let attempt = 0; attempt < 50 && !findInput(); attempt++) {
                await new Promise((resolve) => setTimeout(resolve, 100));
            }
            const input = findInput();
            const bytes = Uint8Array.from(atob('{$base64}'), (character) => character.charCodeAt(0));
            const file = new File([bytes], 'logo.png', { type: 'image/png' });
            const data = new DataTransfer();
            data.items.add(file);
            input.files = data.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        })();
    JS);
}

/**
 * Capture the next multipart upload the page sends, keeping the uploaded File so
 * the test can decode it. The Pest browser server does not parse multipart
 * bodies (its file handling is an open TODO), so we assert the crop dispatches
 * a valid image rather than that it persists — persistence is covered by
 * ProfileUpdateTest.
 */
function recordUpload(mixed $page): void
{
    $page->script(<<<'JS'
        (() => {
            window.__uploadRequest = null;
            const open = XMLHttpRequest.prototype.open;
            const send = XMLHttpRequest.prototype.send;
            XMLHttpRequest.prototype.open = function (method, url) {
                this.__method = method;
                this.__url = url;
                return open.apply(this, arguments);
            };
            XMLHttpRequest.prototype.send = function (body) {
                if (body instanceof FormData) {
                    const photo = body.get('photo');
                    window.__uploadFile = photo;
                    window.__uploadRequest = {
                        method: this.__method,
                        url: this.__url,
                        keys: [...body.keys()],
                        size: photo instanceof File ? photo.size : 0,
                    };
                }
                return send.apply(this, arguments);
            };
        })();
    JS);
}

test('cropping a selected photo dispatches a valid 512x512 avatar upload', function () {
    $this->actingAs(User::factory()->create());

    $page = visit(route('app.profile.edit'));

    selectPhoto($page);
    recordUpload($page);

    $page->click('@crop-save')
        ->assertNoJavaScriptErrors();

    $request = json_decode((string) $page->script(<<<'JS'
        (async () => {
            for (let attempt = 0; attempt < 80 && !window.__uploadRequest; attempt++) {
                await new Promise((resolve) => setTimeout(resolve, 100));
            }
            if (!window.__uploadRequest) {
                return 'null';
            }
            const bitmap = await createImageBitmap(window.__uploadFile);
            return JSON.stringify({ ...window.__uploadRequest, width: bitmap.width, height: bitmap.height });
        })();
    JS), true);

    expect($request)->not->toBeNull()
        ->and($request['method'])->toBe('POST')
        ->and($request['url'])->toContain(route('app.profile.upload-photo', absolute: false))
        ->and($request['keys'])->toContain('photo')
        ->and($request['size'])->toBeGreaterThan(0)
        ->and($request['width'])->toBe(512)
        ->and($request['height'])->toBe(512);
});
