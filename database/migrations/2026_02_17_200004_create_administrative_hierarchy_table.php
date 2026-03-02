<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-referential tree: national → admin_area → constituency → ward.
 * Uses materialized path pattern for efficient ancestor/descendant queries.
 * Approver assigned per node enforces hierarchical data access from RBAC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrative_hierarchy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->enum('level', ['national', 'admin_area', 'constituency', 'ward']);
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('administrative_hierarchy')
                ->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('slug');
            $table->string('path', 1000)->nullable();
            $table->integer('depth')->default(0);
            $table->decimal('center_latitude', 10, 8)->nullable();
            $table->decimal('center_longitude', 11, 8)->nullable();
            $table->unsignedInteger('registered_voters')->default(0);
            $table->foreignId('assigned_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['election_id', 'level']);
            $table->index(['election_id', 'parent_id']);
            $table->index('path');
            $table->index('assigned_approver_id');
            $table->unique(['election_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administrative_hierarchy');
    }
};
