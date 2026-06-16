<?php

use Inovector\Mixpost\Commands\ImportPosts;
use Inovector\Mixpost\Enums\PostStatus;
use Inovector\Mixpost\Models\Account;
use Inovector\Mixpost\Models\Post;

function writeCsv(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'mixpost_import_').'.csv';
    file_put_contents($path, $contents);

    return $path;
}

it('bulk imports scheduled and draft posts from a csv', function () {
    $account = Account::factory()->create();

    $csv = writeCsv(implode("\n", [
        'content,scheduled_at,accounts',
        "\"Hello world\",2030-01-01 09:00,{$account->id}",
        "\"A draft post\",,{$account->id}",
    ]));

    $this->artisan(ImportPosts::class, ['file' => $csv])->assertExitCode(0);

    expect(Post::count())->toBe(2);

    $scheduled = Post::where('status', PostStatus::SCHEDULED->value)->first();
    expect($scheduled)->not->toBeNull()
        ->and($scheduled->scheduled_at)->not->toBeNull()
        ->and($scheduled->accounts)->toHaveCount(1)
        ->and($scheduled->versions()->first()->content[0]['body'])->toBe('Hello world');

    expect(Post::where('status', PostStatus::DRAFT->value)->count())->toBe(1);

    unlink($csv);
});

it('skips rows referencing unknown accounts', function () {
    $csv = writeCsv("content,scheduled_at,accounts\n\"Hi\",,99999");

    $this->artisan(ImportPosts::class, ['file' => $csv])->assertExitCode(0);

    expect(Post::count())->toBe(0);

    unlink($csv);
});

it('fails when the csv is missing required columns', function () {
    $csv = writeCsv("title,date\n\"x\",\"y\"");

    $this->artisan(ImportPosts::class, ['file' => $csv])->assertExitCode(1);

    unlink($csv);
});

it('fails when the file does not exist', function () {
    $this->artisan(ImportPosts::class, ['file' => '/no/such/file.csv'])->assertExitCode(1);
});
