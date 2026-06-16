<?php

namespace Inovector\Mixpost\SocialProviders\Meta\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Inovector\Mixpost\Facades\SocialProviderManager;
use Inovector\Mixpost\Models\Account;
use Inovector\Mixpost\Support\Log;

class RefreshFacebookAccessTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $deleteWhenMissingModels = true;

    // Refresh once the long-lived token is within this many days of expiring.
    public int $refreshWithinDays = 7;

    public Account $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function handle(): void
    {
        $token = $this->account->access_token?->toArray() ?? [];

        if (empty($token['access_token'])) {
            return;
        }

        // Skip while the token is comfortably far from expiring. Tokens with no known
        // expiry are refreshed so that an `expires_at` is recorded going forward.
        $expiresAt = $token['expires_at'] ?? null;

        if ($expiresAt && Carbon::createFromTimestamp($expiresAt, 'UTC')
            ->greaterThan(Carbon::now('UTC')->addDays($this->refreshWithinDays))) {
            return;
        }

        $response = SocialProviderManager::connect('facebook_page')
            ->useAccessToken($token)
            ->requestLongLivedAccessToken();

        // Guard: only update on a clearly successful response. Never overwrite a valid
        // token with an error or empty payload.
        if (! is_array($response) || empty($response['access_token'])) {
            $message = Arr::get($response, 'error.message', 'Unknown error');
            $code = Arr::get($response, 'error.code');

            Log::error("Failed to refresh Facebook access token for account {$this->account->id}: {$message}");

            // 190 = the token is invalid/expired; the account must be reconnected.
            if ((int) $code === 190) {
                $this->account->setUnauthorized();
            }

            return;
        }

        $newToken = array_merge($token, ['access_token' => $response['access_token']]);

        if (! empty($response['token_type'])) {
            $newToken['token_type'] = $response['token_type'];
        }

        if (! empty($response['expires_in'])) {
            $newToken['expires_at'] = Carbon::now('UTC')->addSeconds((int) $response['expires_in'])->timestamp;
        }

        $this->account->updateAccessToken($newToken);

        if ($this->account->isUnauthorized()) {
            $this->account->setAuthorized();
        }
    }
}
