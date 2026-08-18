<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('billing_cycle')->nullable()->after('budget');
            $table->integer('allocated_hours')->nullable()->after('billing_cycle');
            $table->date('renewal_date')->nullable()->after('due_at');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->decimal('hours_spent', 8, 2)->nullable()->after('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['billing_cycle', 'allocated_hours', 'renewal_date']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('hours_spent');
        });
    }
};
