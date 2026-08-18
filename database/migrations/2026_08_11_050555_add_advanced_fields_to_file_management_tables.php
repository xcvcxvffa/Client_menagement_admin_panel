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
        Schema::table('folders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->softDeletes();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->renameColumn('uploaded_by', 'user_id');
            $table->renameColumn('name', 'original_name');
            $table->renameColumn('size', 'file_size');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stored_name')->nullable();
            $table->string('disk')->default('public');
            $table->string('extension')->nullable();
            $table->string('file_type')->nullable();
            $table->boolean('is_starred')->default(false);
            $table->boolean('is_shared')->default(false);
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['client_id']);
            $table->dropForeign(['project_id']);
            $table->dropColumn(['user_id', 'client_id', 'project_id', 'deleted_at']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['project_id']);
            $table->dropColumn([
                'client_id', 'project_id', 'stored_name', 'disk',
                'extension', 'file_type', 'is_starred', 'is_shared', 'deleted_at'
            ]);
        });
        
        Schema::table('documents', function (Blueprint $table) {
            $table->renameColumn('user_id', 'uploaded_by');
            $table->renameColumn('original_name', 'name');
            $table->renameColumn('file_size', 'size');
        });
    }
};
