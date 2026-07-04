<?php

declare(strict_types=1);

use App\Models\User;

test('probe upload response', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $base64 = base64_encode((string) file_get_contents(base_path('tests/fixtures/blue-logo.png')));

    $page = visit(route('app.profile.edit'));
    $page->script(<<<JS
        (async () => {
            const findInput = () => document.querySelector('input[type="file"]');
            for (let i = 0; i < 50 && !findInput(); i++) { await new Promise(r => setTimeout(r, 100)); }
            const input = findInput();
            const bytes = Uint8Array.from(atob('{$base64}'), (c) => c.charCodeAt(0));
            const file = new File([bytes], 'logo.png', { type: 'image/png' });
            const dt = new DataTransfer(); dt.items.add(file);
            input.files = dt.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        })();
    JS);
    $page->script(<<<'JS'
        (() => {
            window.__resp = null;
            const oOpen = XMLHttpRequest.prototype.open;
            XMLHttpRequest.prototype.open = function (m, u) { this.__u = u; this.addEventListener('loadend', () => {
                if (String(this.__u).includes('/photo')) window.__resp = { status: this.status, body: (this.responseText || '').slice(0, 300) };
            }); return oOpen.apply(this, arguments); };
        })();
    JS);
    $page->click('@crop-save');
    $info = $page->script(<<<'JS'
        (async () => { for (let i = 0; i < 80 && !window.__resp; i++) { await new Promise(r => setTimeout(r, 100)); } return JSON.stringify(window.__resp || 'NO RESPONSE'); })();
    JS);
    fwrite(STDERR, "\nUPLOAD_RESP => {$info}\n");
    fwrite(STDERR, 'DB_HAS_PHOTO => '.json_encode($user->fresh()->has_photo)."\n");
    expect(true)->toBeTrue();
});
