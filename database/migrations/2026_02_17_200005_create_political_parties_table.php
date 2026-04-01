<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('political_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('abbreviation', 20);
            $table->string('slug');
            $table->string('registration_number')->nullable();
            $table->string('color', 7)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('election_id');
            $table->unique(['election_id', 'abbreviation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('political_parties');
    }
};
