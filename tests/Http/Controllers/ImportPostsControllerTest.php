<?php

use Illuminate\Http\UploadedFile;
use Inovector\Mixpost\Models\Account;
use Inovector\Mixpost\Models\Post;
use Inovector\Mixpost\Models\User;

beforeEach(function () {
    test()->user = User::factory()->create();
});

it('imports posts from an uploaded csv', function () {
    $this->actingAs(test()->user);

    $account = Account::factory()->create();

    $file = UploadedFile::fake()->createWithContent(
        'posts.csv',
        "content,scheduled_at,accounts\n\"Hello\",2030-01-01 09:00,{$account->id}"
    );

    $this->post(route('mixpost.posts.import'), ['file' => $file])->assertRedirect();

    expect(Post::count())->toBe(1)
        ->and(Post::first()->versions()->first()->content[0]['body'])->toBe('Hello');
});

it('validates that a csv file is provided', function () {
    $this->actingAs(test()->user);

    $this->postJson(route('mixpost.posts.import'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

it('prevents unauthorized users from importing', function () {
    $this->postJson(route('mixpost.posts.import'), [])->assertUnauthorized();
});
