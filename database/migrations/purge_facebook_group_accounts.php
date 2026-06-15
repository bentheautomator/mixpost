<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove deprecated Facebook Group accounts and their related data.
     *
     * Meta removed the Groups publishing API in April 2024 and the feature has been
     * dropped from Mixpost, so any `facebook_group` accounts left over from an older
     * install are dead. This permanently deletes them and the rows that reference them.
     */
    public function up(): void
    {
        if (! Schema::hasTable('mixpost_accounts')) {
            return;
        }

        $accountIds = DB::table('mixpost_accounts')
            ->where('provider', 'facebook_group')
            ->pluck('id');

        if ($accountIds->isEmpty()) {
            return;
        }

        // Tables that reference account_id but do not cascade on delete.
        $relatedTables = [
            'mixpost_post_accounts',
            'mixpost_metrics',
            'mixpost_audience',
            'mixpost_facebook_insights',
            'mixpost_post_versions',
            'mixpost_imported_posts',
        ];

        DB::transaction(function () use ($accountIds, $relatedTables) {
            foreach ($relatedTables as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'account_id')) {
                    DB::table($table)->whereIn('account_id', $accountIds)->delete();
                }
            }

            DB::table('mixpost_accounts')->whereIn('id', $accountIds)->delete();
        });
    }

    /**
     * The deleted data cannot be restored.
     */
    public function down(): void
    {
        //
    }
};
