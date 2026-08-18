<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'domain_cost')) {
                $table->decimal('domain_cost', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('projects', 'domain_purchased_at')) {
                $table->date('domain_purchased_at')->nullable();
            }
            if (!Schema::hasColumn('projects', 'domain_expires_at')) {
                $table->date('domain_expires_at')->nullable();
            }
            if (!Schema::hasColumn('projects', 'domain_auto_renew')) {
                $table->boolean('domain_auto_renew')->default(false);
            }

            if (!Schema::hasColumn('projects', 'hosting_provider')) {
                $table->string('hosting_provider')->nullable();
            }
            if (!Schema::hasColumn('projects', 'hosting_cost')) {
                $table->decimal('hosting_cost', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('projects', 'hosting_purchased_at')) {
                $table->date('hosting_purchased_at')->nullable();
            }
            if (!Schema::hasColumn('projects', 'hosting_expires_at')) {
                $table->date('hosting_expires_at')->nullable();
            }
            if (!Schema::hasColumn('projects', 'hosting_auto_renew')) {
                $table->boolean('hosting_auto_renew')->default(false);
            }

            if (!Schema::hasColumn('projects', 'domain_hosting_notes')) {
                $table->text('domain_hosting_notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'domain_cost', 'domain_purchased_at', 'domain_expires_at', 'domain_auto_renew',
                'hosting_provider', 'hosting_cost', 'hosting_purchased_at', 'hosting_expires_at', 'hosting_auto_renew',
                'domain_hosting_notes'
            ]);
        });
    }
};
