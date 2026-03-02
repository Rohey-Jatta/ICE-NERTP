<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elections - root entity. All other IEC tables scope to this.
 * Status controls what actions are permitted system-wide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['presidential', 'parliamentary', 'local_government', 'by_election']);
            $table->text('description')->nullable();
            $table->string('legal_instrument')->nullable();
            $table->date('nomination_start_date')->nullable();
            $table->date('nomination_end_date')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->date('results_deadline')->nullable();
            $table->enum('status', [
                'draft', 'configured', 'active',
                'results_pending', 'certifying', 'certified', 'archived',
            ])->default('draft');
            $table->boolean('requires_party_acceptance')->default(true);
            $table->boolean('allow_provisional_public_display')->default(true);
            $table->integer('gps_validation_radius_meters')->default(100);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('configured_by')->nullable()->constrained('users');
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
            $table->index('type');
            $table->index('start_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elections');
    }
};
