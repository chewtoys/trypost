<?php

declare(strict_types=1);

namespace App\Socialite;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

/**
 * Reddit OAuth2. The token endpoint uses HTTP Basic auth with the app
 * credentials (not the access token), and Reddit requires a unique descriptive
 * User-Agent on every request or it rate-limits/blocks. duration=permanent
 * yields a refresh token that never expires.
 */
class RedditProvider extends AbstractProvider implements ProviderInterface
{
    protected $scopeSeparator = ' ';

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(config('trypost.platforms.reddit.oauth_api').'/authorize', $state);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getCodeFields($state = null): array
    {
        return array_merge(parent::getCodeFields($state), [
            'duration' => 'permanent',
        ]);
    }

    protected function getTokenUrl(): string
    {
        return config('trypost.platforms.reddit.oauth_api').'/access_token';
    }

    /**
     * Reddit's token endpoint authenticates the CLIENT via Basic auth, so the
     * code grant is sent with the app credentials, not a bearer token.
     *
     * @return array<string, mixed>
     */
    public function getAccessTokenResponse($code): array
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'auth' => [config('services.reddit.client_id'), config('services.reddit.client_secret')],
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => config('trypost.platforms.reddit.user_agent'),
            ],
            'form_params' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUrl,
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get(config('trypost.platforms.reddit.api').'/api/v1/me', [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'User-Agent' => config('trypost.platforms.reddit.user_agent'),
            ],
        ]);

        return (array) json_decode((string) $response->getBody(), true);
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user): User
    {
        $icon = (string) data_get($user, 'icon_img', '');
        $icon = $icon !== '' ? strtok($icon, '?') : null;

        return (new User)->setRaw($user)->map([
            'id' => data_get($user, 'id'),
            'nickname' => data_get($user, 'name'),
            'name' => data_get($user, 'name'),
            'avatar' => $icon,
        ]);
    }
}
