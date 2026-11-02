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
        Schema::create('school_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->date('event_date');
            $table->date('event_end_date')->nullable();
            $table->string('title', 120);
            $table->string('type', 30)->default('libur_nasional'); // libur_nasional, kegiatan_sekolah, ujian_asesmen, libur_semester
            $table->boolean('is_holiday')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['academic_year_id', 'event_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_events');
    }
};
