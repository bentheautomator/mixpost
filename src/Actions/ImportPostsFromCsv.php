<?php

namespace Inovector\Mixpost\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inovector\Mixpost\Enums\PostStatus;
use Inovector\Mixpost\Models\Account;
use Inovector\Mixpost\Models\Post;
use Inovector\Mixpost\Util;
use RuntimeException;

class ImportPostsFromCsv
{
    /** @var array<int, string> */
    public array $messages = [];

    public int $created = 0;

    public int $skipped = 0;

    /**
     * @return array{created: int, skipped: int, messages: array<int, string>}
     */
    public function __invoke(string $filePath): array
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new RuntimeException("Unable to open file: {$filePath}");
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                throw new RuntimeException('The CSV file is empty.');
            }

            $header = array_map(fn ($h) => Str::of((string) $h)->trim()->lower()->toString(), $header);

            if ($missing = array_diff(['content', 'accounts'], $header)) {
                throw new RuntimeException('Missing required column(s): '.implode(', ', $missing));
            }

            $line = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $line++;

                if (count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) {
                    continue;
                }

                $this->importRow(array_combine($header, array_pad($row, count($header), null)), $line);
            }
        } finally {
            fclose($handle);
        }

        return [
            'created' => $this->created,
            'skipped' => $this->skipped,
            'messages' => $this->messages,
        ];
    }

    protected function importRow(array $data, int $line): void
    {
        $body = trim((string) ($data['content'] ?? ''));

        if ($body === '') {
            $this->skip("Line {$line}: empty content.");

            return;
        }

        $accountIds = $this->parseIds($data['accounts'] ?? '');
        $existingIds = Account::whereIn('id', $accountIds)->pluck('id')->all();

        if ($unknown = array_diff($accountIds, $existingIds)) {
            $this->skip("Line {$line}: unknown account id(s): ".implode(', ', $unknown).'.');

            return;
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

        $this->created++;
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
            return [PostStatus::SCHEDULED, Util::convertTimeToUTC($scheduledAtRaw)];
        } catch (\Throwable $e) {
            $this->messages[] = "Line {$line}: invalid scheduled_at '{$scheduledAtRaw}', imported as draft.";

            return [PostStatus::DRAFT, null];
        }
    }

    protected function skip(string $message): void
    {
        $this->skipped++;
        $this->messages[] = $message;
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
