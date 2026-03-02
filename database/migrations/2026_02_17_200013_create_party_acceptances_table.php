<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formal party decision: accepted / accepted_with_reservation / rejected.
 * Visible to IEC staff AND public for transparency.
 * Does NOT block certification - it's advisory to the ward approver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('political_party_id')->constrained()->restrictOnDelete();
            $table->foreignId('party_representative_id')->constrained('party_representatives')->restrictOnDelete();
            $table->foreignId('election_id')->constrained()->restrictOnDelete();
            $table->enum('status', [
                'pending', 'accepted', 'accepted_with_reservation', 'rejected',
            ])->default('pending');
            $table->text('comments')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->boolean('is_final')->default(false);
            $table->timestamps();
            $table->unique(['result_id', 'political_party_id']);
            $table->index('result_id');
            $table->index('election_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_acceptances');
    }
};
