<?php

namespace Inovector\Mixpost\Commands;

use Illuminate\Console\Command;
use Inovector\Mixpost\Actions\ImportPostsFromCsv;

class ImportPosts extends Command
{
    public $signature = 'mixpost:import-posts {file : Path to a CSV file with columns: content, scheduled_at, accounts (and optional tags)}';

    public $description = 'Bulk import and schedule posts from a CSV file';

    public function handle(ImportPostsFromCsv $import): int
    {
        $file = $this->argument('file');

        if (! is_file($file) || ! is_readable($file)) {
            $this->error("File not found or not readable: {$file}");

            return self::FAILURE;
        }

        try {
            $result = $import($file);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($result['messages'] as $message) {
            $this->warn($message);
        }

        $this->info("Imported {$result['created']} post(s), skipped {$result['skipped']}.");

        return self::SUCCESS;
    }
}
