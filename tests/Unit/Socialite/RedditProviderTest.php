<?php

declare(strict_types=1);

use App\Socialite\RedditProvider;
use Illuminate\Http\Request;

test('reddit auth url requests a permanent token with the configured scopes', function () {
    config([
        'trypost.platforms.reddit.oauth_api' => 'https://www.reddit.com/api/v1',
        'services.reddit.client_id' => 'cid',
        'services.reddit.redirect' => 'https://trypost.test/accounts/reddit/callback',
    ]);

    $request = Request::create('/connect/reddit', 'GET');
    $request->setLaravelSession(app('session.store'));

    $provider = new RedditProvider($request, 'cid', 'secret', 'https://trypost.test/accounts/reddit/callback');
    $url = $provider->scopes(['identity', 'submit'])->redirect()->getTargetUrl();

    expect($url)->toContain('https://www.reddit.com/api/v1/authorize')
        ->toContain('duration=permanent')
        ->toContain('client_id=cid')
        ->toContain('scope=identity');
});
