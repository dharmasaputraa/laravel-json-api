<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();

            // Core content
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('body');
            $table->string('excerpt', 500)->nullable();

            // Status
            $table->enum('status', ['draft', 'published', 'archived'])
                  ->default('draft')
                  ->index();

            // Flags & counters
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('views_count')->default(0)->index();
            $table->unsignedInteger('likes_count')->default(0);

            // Dates
            $table->timestamp('published_at')->nullable()->index();

            // Foreign keys
            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('category_id')
                  ->constrained()
                  ->restrictOnDelete();

            $table->timestamps();

            // Composite indexes for common filter combos
            $table->index(['status', 'published_at']);
            $table->index(['status', 'is_featured']);
            $table->index(['user_id', 'status']);
            $table->index(['category_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
