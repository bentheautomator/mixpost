<?php

namespace Inovector\Mixpost\Concerns\Model;

use Illuminate\Database\Eloquent\Builder;
use Inovector\Mixpost\Util;

/**
 * Opt-in per-user ownership for multi-tenant installs.
 *
 * When `mixpost.multi_tenant` is disabled (the default) this trait is completely
 * inert — no scope is applied and `user_id` is never set, so behaviour is identical
 * to the single-tenant default. When enabled, records are stamped with and scoped to
 * the authenticated user. Queries made without an authenticated user (e.g. queue
 * workers publishing posts) are never scoped, so background processing is unaffected.
 */
trait BelongsToOwner
{
    public static function bootBelongsToOwner(): void
    {
        static::creating(function ($model) {
            if (static::multiTenantEnabled() && empty($model->user_id) && auth()->check()) {
                $model->user_id = auth()->id();
            }
        });

        static::addGlobalScope('owner', function (Builder $query) {
            if (static::multiTenantEnabled() && auth()->check()) {
                $query->where($query->getModel()->getTable().'.user_id', auth()->id());
            }
        });
    }

    protected static function multiTenantEnabled(): bool
    {
        return (bool) Util::config('multi_tenant', false);
    }
}
