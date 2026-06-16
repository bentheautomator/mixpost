<?php

namespace Inovector\Mixpost\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inovector\Mixpost\Facades\ServiceManager;

class ReencryptCredentials extends Command
{
    public $signature = 'mixpost:reencrypt-credentials {--old-key= : The previous APP_KEY the data was encrypted with}';

    public $description = 'Re-encrypt stored account tokens and service credentials after an APP_KEY rotation';

    public function handle(): int
    {
        $oldKeyOption = $this->option('old-key');

        if (! $oldKeyOption) {
            $this->error('The --old-key option is required (the APP_KEY used before the rotation).');

            return self::FAILURE;
        }

        try {
            $oldEncrypter = new Encrypter($this->parseKey($oldKeyOption), config('app.cipher'));
        } catch (\Throwable $e) {
            $this->error('Invalid --old-key: '.$e->getMessage());

            return self::FAILURE;
        }

        $targets = [
            ['table' => 'mixpost_accounts', 'column' => 'access_token', 'label' => 'Account tokens'],
            ['table' => 'mixpost_services', 'column' => 'configuration', 'label' => 'Service credentials'],
        ];

        foreach ($targets as $target) {
            $this->reencryptTable($oldEncrypter, $target['table'], $target['column'], $target['label']);
        }

        // The service layer caches the encrypted configuration; clear it so the freshly
        // re-encrypted ciphertext is used on the next read.
        ServiceManager::forgetAll();

        $this->info('Done. Stored credentials now use the current APP_KEY.');

        return self::SUCCESS;
    }

    protected function reencryptTable(Encrypter $oldEncrypter, string $table, string $column, string $label): void
    {
        $reencrypted = 0;
        $skipped = 0;
        $failed = 0;

        $rows = DB::table($table)->whereNotNull($column)->get(['id', $column]);

        foreach ($rows as $row) {
            $ciphertext = $row->{$column};

            if (empty($ciphertext)) {
                continue;
            }

            // Already encrypted with the current key? Leave it untouched (idempotent).
            try {
                Crypt::decryptString($ciphertext);
                $skipped++;

                continue;
            } catch (DecryptException $e) {
                // Expected: not encrypted with the current key.
            }

            try {
                $plain = $oldEncrypter->decryptString($ciphertext);
            } catch (DecryptException $e) {
                $failed++;
                $this->warn("Could not decrypt {$table}#{$row->id} with the provided old key; skipped.");

                continue;
            }

            DB::table($table)->where('id', $row->id)->update([
                $column => Crypt::encryptString($plain),
            ]);

            $reencrypted++;
        }

        $this->line("{$label}: {$reencrypted} re-encrypted, {$skipped} already current, {$failed} failed.");
    }

    protected function parseKey(string $key): string
    {
        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }

        return $key;
    }
}
