<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('school_schedule_teachers')) {
            Schema::create('school_schedule_teachers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
                $table->string('code', 20)->index();
                $table->string('name');
                $table->string('subject')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('homeroom_class', 30)->nullable();
                $table->string('piket_day', 20)->nullable();
                $table->timestamps();

                $table->unique(['academic_year_id', 'code']);
            });
        }

        if (! Schema::hasTable('school_schedule_items')) {
            Schema::create('school_schedule_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('day_of_week')->index(); // 1 = Senin, ..., 6 = Sabtu
                $table->string('shift', 20)->default('pagi')->index(); // 'pagi', 'siang'
                $table->unsignedTinyInteger('period_order')->default(1); // 0 = Pembiasaan, 1..8
                $table->string('time_range', 50)->nullable(); // '07.40 - 08.10'
                $table->string('class_name', 20)->index(); // '7A', '8B', '9G'
                $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
                $table->string('teacher_code', 20)->nullable()->index(); // '32', '14A', 'UPACARA', 'ISTIRAHAT'
                $table->string('teacher_name')->nullable();
                $table->string('subject_name')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
                $table->boolean('is_conflict')->default(false)->index();
                $table->text('conflict_notes')->nullable();
                $table->timestamps();

                $table->index(['academic_year_id', 'day_of_week', 'shift'], 'sched_items_ay_dow_shift_idx');
                $table->index(['academic_year_id', 'day_of_week', 'period_order'], 'sched_items_ay_dow_period_idx');
                $table->index(['academic_year_id', 'class_name'], 'sched_items_ay_class_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_schedule_items');
        Schema::dropIfExists('school_schedule_teachers');
    }
};
