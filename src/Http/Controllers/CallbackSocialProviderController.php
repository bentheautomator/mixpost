<?php

namespace Inovector\Mixpost\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Inovector\Mixpost\Actions\UpdateOrCreateAccount;
use Inovector\Mixpost\Facades\SocialProviderManager;

class CallbackSocialProviderController extends Controller
{
    public function __invoke(Request $request, UpdateOrCreateAccount $updateOrCreateAccount, string $providerName): RedirectResponse
    {
        $provider = SocialProviderManager::connect($providerName);

        if (empty($provider->getCallbackResponse())) {
            return redirect()->route('mixpost.accounts.index');
        }

        if ($error = $request->get('error')) {
            return redirect()->route('mixpost.accounts.index')->with('error', $error);
        }

        // Protect the OAuth 2.0 callback against CSRF / authorization-code injection by
        // verifying the `state` nonce we issued when building the authorization URL.
        if ($provider->usesOAuthState && ! $provider->validateOAuthState()) {
            return redirect()->route('mixpost.accounts.index')
                ->with('error', 'Invalid authentication state. Please try connecting the account again.');
        }

        if (! $provider->isOnlyUserAccount()) {
            return redirect()->route('mixpost.accounts.entities.index', ['provider' => $providerName])
                ->with('mixpost_callback_response', $provider->getCallbackResponse());
        }

        $accessToken = $provider->requestAccessToken($provider->getCallbackResponse());

        if ($error = Arr::get($accessToken, 'error')) {
            return redirect()->route('mixpost.accounts.index')
                ->with('error', $error);
        }

        $provider->setAccessToken($accessToken);

        $account = $provider->getAccount();

        if ($account->hasError()) {
            return redirect()->route('mixpost.accounts.index')
                ->with('error', "It's something wrong. Try again.");
        }

        $updateOrCreateAccount($providerName, $account->context(), $accessToken);

        return redirect()->route('mixpost.accounts.index');
    }
}
