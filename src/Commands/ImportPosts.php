<?php

namespace Inovector\Mixpost\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inovector\Mixpost\Enums\PostStatus;
use Inovector\Mixpost\Models\Account;
use Inovector\Mixpost\Models\Post;
use Inovector\Mixpost\Util;

class ImportPosts extends Command
{
    public $signature = 'mixpost:import-posts {file : Path to a CSV file with columns: content, scheduled_at, accounts (and optional tags)}';

    public $description = 'Bulk import and schedule posts from a CSV file';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! is_file($file) || ! is_readable($file)) {
            $this->error("File not found or not readable: {$file}");

            return self::FAILURE;
        }

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        if ($header === false) {
            $this->error('The CSV file is empty.');
            fclose($handle);

            return self::FAILURE;
        }

        $header = array_map(fn ($h) => Str::of((string) $h)->trim()->lower()->toString(), $header);

        if ($missing = array_diff(['content', 'accounts'], $header)) {
            $this->error('Missing required column(s): '.implode(', ', $missing));
            fclose($handle);

            return self::FAILURE;
        }

        $created = 0;
        $skipped = 0;
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            // Ignore fully blank lines.
            if (count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            $data = array_combine($header, array_pad($row, count($header), null));

            $body = trim((string) ($data['content'] ?? ''));

            if ($body === '') {
                $this->warn("Line {$line}: empty content, skipped.");
                $skipped++;

                continue;
            }

            $accountIds = $this->parseIds($data['accounts'] ?? '');
            $existingIds = Account::whereIn('id', $accountIds)->pluck('id')->all();

            if ($unknown = array_diff($accountIds, $existingIds)) {
                $this->warn("Line {$line}: unknown account id(s): ".implode(', ', $unknown).', skipped.');
                $skipped++;

                continue;
            }

            [$status, $scheduledAt] = $this->resolveSchedule(trim((string) ($data['scheduled_at'] ?? '')), $line);
            $tagIds = $this->parseIds($data['tags'] ?? '');

            DB::transaction(function () use ($status, $scheduledAt, $existingIds, $tagIds, $body) {
                $post = Post::create([
                    'status' => $status,
                    'scheduled_at' => $scheduledAt,
                ]);

                $post->accounts()->attach($existingIds);

                if (! empty($tagIds)) {
                    $post->tags()->attach($tagIds);
                }

                $post->versions()->create([
                    'account_id' => 0,
                    'is_original' => true,
                    'content' => [
                        ['body' => $body, 'media' => []],
                    ],
                ]);
            });

            $created++;
        }

        fclose($handle);

        $this->info("Imported {$created} post(s), skipped {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * @return array{0: PostStatus, 1: \Illuminate\Support\Carbon|null}
     */
    protected function resolveSchedule(string $scheduledAtRaw, int $line): array
    {
        if ($scheduledAtRaw === '') {
            return [PostStatus::DRAFT, null];
        }

        try {
            // Interpret the time in the app timezone (as the UI does) and store as UTC.
            return [PostStatus::SCHEDULED, Util::convertTimeToUTC($scheduledAtRaw)];
        } catch (\Throwable $e) {
            $this->warn("Line {$line}: invalid scheduled_at '{$scheduledAtRaw}', importing as draft.");

            return [PostStatus::DRAFT, null];
        }
    }

    /**
     * @return array<int, int>
     */
    protected function parseIds(?string $value): array
    {
        if (empty($value)) {
            return [];
        }

        return collect(preg_split('/[|,]/', $value))
            ->map(fn ($v) => (int) trim($v))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
