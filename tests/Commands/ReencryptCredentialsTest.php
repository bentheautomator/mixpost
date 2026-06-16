<?php

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Inovector\Mixpost\Commands\ReencryptCredentials;
use Inovector\Mixpost\Models\Account;

it('re-encrypts account tokens from an old key to the current key', function () {
    $oldKeyRaw = random_bytes(32);
    $oldEncrypter = new Encrypter($oldKeyRaw, config('app.cipher'));

    $account = Account::factory()->create(['provider' => 'facebook_page']);

    // Simulate the column having been encrypted with a previous APP_KEY.
    DB::table('mixpost_accounts')->where('id', $account->id)->update([
        'access_token' => $oldEncrypter->encryptString(json_encode(['access_token' => 'secret-token'])),
    ]);

    // With the current key the cast can no longer read it (fails soft to null).
    expect(Account::find($account->id)->access_token)->toBeNull();

    $this->artisan(ReencryptCredentials::class, ['--old-key' => 'base64:'.base64_encode($oldKeyRaw)])
        ->assertExitCode(0);

    expect(Account::find($account->id)->access_token->toArray()['access_token'])->toBe('secret-token');
});

it('leaves already-current rows untouched and is idempotent', function () {
    $oldKeyRaw = random_bytes(32);

    $account = Account::factory()->create([
        'provider' => 'facebook_page',
        'access_token' => ['access_token' => 'already-current'],
    ]);

    $this->artisan(ReencryptCredentials::class, ['--old-key' => 'base64:'.base64_encode($oldKeyRaw)])
        ->assertExitCode(0);

    expect(Account::find($account->id)->access_token->toArray()['access_token'])->toBe('already-current');
});

it('fails when no old key is provided', function () {
    $this->artisan(ReencryptCredentials::class)->assertExitCode(1);
});
