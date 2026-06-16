<?php

use Inovector\Mixpost\Models\Account;
use Inovector\Mixpost\Models\Post;
use Inovector\Mixpost\Models\User;

it('isolates records per user when multi-tenant is enabled', function () {
    config()->set('mixpost.multi_tenant', true);

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice);
    $aliceAccount = Account::factory()->create();
    $alicePost = Post::factory()->create();

    $this->actingAs($bob);
    $bobAccount = Account::factory()->create();

    // Bob only sees his own records.
    expect(Account::pluck('id')->all())->toBe([$bobAccount->id])
        ->and(Post::count())->toBe(0);

    // Alice only sees hers.
    $this->actingAs($alice);
    expect(Account::pluck('id')->all())->toBe([$aliceAccount->id])
        ->and(Post::pluck('id')->all())->toBe([$alicePost->id]);

    // Ownership is stamped automatically.
    expect($aliceAccount->fresh()->user_id)->toBe($alice->id)
        ->and($bobAccount->fresh()->user_id)->toBe($bob->id);
});

it('does not scope or stamp records when multi-tenant is disabled (default)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Account::factory()->count(2)->create();

    expect(Account::count())->toBe(2)
        ->and(Account::first()->user_id)->toBeNull();
});

it('does not scope queries that run without an authenticated user (e.g. workers)', function () {
    config()->set('mixpost.multi_tenant', true);

    $user = User::factory()->create();
    $this->actingAs($user);
    Account::factory()->create();

    // Simulate a queue worker context: no authenticated user.
    auth()->logout();

    expect(Account::count())->toBe(1);
});
