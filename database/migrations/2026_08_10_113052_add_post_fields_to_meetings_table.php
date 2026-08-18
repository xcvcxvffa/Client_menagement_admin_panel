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
        Schema::table('meetings', function (Blueprint $table) {
            $table->enum('type', ['meeting', 'post'])->default('meeting')->after('id');
            $table->string('platform', 50)->nullable()->after('type');
            $table->text('content')->nullable()->after('platform');
            $table->string('media', 255)->nullable()->after('content');
            $table->enum('status', ['draft', 'pending', 'approved', 'scheduled', 'published'])->nullable()->after('media');
            $table->unsignedBigInteger('assigned_to')->nullable()->after('status');
            
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['type', 'platform', 'content', 'media', 'status', 'assigned_to']);
        });
    }
};
