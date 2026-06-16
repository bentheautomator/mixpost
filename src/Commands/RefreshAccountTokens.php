<?php

namespace Inovector\Mixpost\Commands;

use Illuminate\Console\Command;
use Inovector\Mixpost\Concerns\AccountsOption;
use Inovector\Mixpost\SocialProviders\Meta\Jobs\RefreshFacebookAccessTokenJob;

class RefreshAccountTokens extends Command
{
    use AccountsOption;

    public $signature = 'mixpost:refresh-tokens {--accounts=}';

    public $description = 'Refresh social account access tokens that are about to expire';

    public function handle(): int
    {
        // Only Facebook issues expiring long-lived tokens. Twitter (OAuth 1.0a) and
        // Mastodon tokens do not expire, so they need no refresh.
        $this->accounts()
            ->where('provider', 'facebook_page')
            ->each(fn ($account) => RefreshFacebookAccessTokenJob::dispatch($account));

        return self::SUCCESS;
    }
}
