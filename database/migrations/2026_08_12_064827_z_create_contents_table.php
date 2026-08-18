<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('campaign_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('project_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('retainer_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('special_day_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('content_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('platform_id')->constrained()->onDelete('cascade');
            
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('brief')->nullable();
            $table->text('caption')->nullable();
            
            $table->date('publish_date')->nullable();
            $table->date('due_date')->nullable();
            
            $table->string('priority')->default('medium'); // low, medium, high
            $table->string('status')->default('idea');
            
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for faster lookups
            $table->index('business_id');
            $table->index('client_id');
            $table->index('campaign_id');
            $table->index('status');
            $table->index('publish_date');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};
