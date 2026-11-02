<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->string('gov_header')->default('PEMERINTAH KABUPATEN KARAWANG');
            $table->string('department_header')->default('DINAS PENDIDIKAN, PEMUDA DAN OLAHRAGA');
            $table->string('school_name')->default('SMP NEGERI 2 LEMAHABANG');
            $table->string('npsn', 30)->default('20214811');
            $table->string('nss', 40)->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code', 15)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('website', 120)->nullable();
            $table->longText('logo_left_data')->nullable();
            $table->longText('logo_right_data')->nullable();
            $table->string('default_city', 80)->default('Lemahabang');
            $table->string('headmaster_name')->default('Teti Kusmiyati, S.Pd., M.M.');
            $table->string('headmaster_nip', 40)->nullable()->default('19720514 199802 2 003');
            $table->longText('headmaster_signature')->nullable();
            $table->unsignedSmallInteger('global_default_kktp')->default(75);
            $table->json('available_grade_levels')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('year_name', 30); // 2026/2027
            $table->string('semester', 20); // Ganjil, Genap
            $table->boolean('is_active')->default(false)->index();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->unique(['year_name', 'semester']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 25)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('default_kktp')->default(75);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subject_kktps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->string('grade_level', 20); // VII, VIII, IX
            $table->unsignedSmallInteger('kktp_value')->default(75);
            $table->timestamps();

            $table->unique(['subject_id', 'grade_level']);
        });

        Schema::create('teaching_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->string('grade_level', 20); // VII, VIII, IX
            $table->string('class_name', 60); // VII-A, VIII-B, dst.
            $table->string('schedule_info')->nullable();
            $table->string('homeroom_teacher_name');
            $table->string('homeroom_teacher_nip', 40)->nullable();
            $table->longText('homeroom_teacher_signature')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['teacher_id', 'academic_year_id']);
        });

        Schema::create('class_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_class_id')->constrained('teaching_classes')->cascadeOnDelete();
            $table->unsignedSmallInteger('order_number')->default(1);
            $table->string('nis_nisn', 40)->nullable();
            $table->string('full_name');
            $table->string('nickname', 80)->nullable();
            $table->string('gender', 2)->default('L'); // L, P
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['teaching_class_id', 'order_number']);
        });

        Schema::create('daily_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_class_id')->constrained('teaching_classes')->cascadeOnDelete();
            $table->date('journal_date');
            $table->string('lesson_hours', 50); // misal: 1-2, 3-4
            $table->text('activity_material');
            $table->unsignedSmallInteger('present_count')->default(0);
            $table->unsignedSmallInteger('absent_count')->default(0);
            $table->string('attendance_breakdown')->nullable();
            $table->boolean('is_attendance_overridden')->default(false);
            $table->text('notes')->nullable();
            $table->longText('signature_data')->nullable();
            $table->timestamps();

            $table->index(['teaching_class_id', 'journal_date']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_class_id')->constrained('teaching_classes')->cascadeOnDelete();
            $table->foreignId('class_student_id')->constrained('class_students')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('status', 5); // '.', 's', 'i', 'a', 'd'
            $table->timestamps();

            $table->unique(['teaching_class_id', 'class_student_id', 'attendance_date'], 'uniq_class_student_att_date');
            $table->index(['teaching_class_id', 'attendance_date']);
        });

        Schema::create('grade_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_class_id')->constrained('teaching_classes')->cascadeOnDelete();
            $table->string('type', 10); // PH, NT
            $table->string('label', 50); // PH 1, PH 2, NT 1
            $table->string('topic_note', 120)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['teaching_class_id', 'type', 'sort_order']);
        });

        Schema::create('grade_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_class_id')->unique()->constrained('teaching_classes')->cascadeOnDelete();
            $table->decimal('weight_ph', 5, 2)->default(25.00);
            $table->decimal('weight_attendance', 5, 2)->default(10.00);
            $table->decimal('weight_nt', 5, 2)->default(20.00);
            $table->decimal('weight_sts', 5, 2)->default(20.00);
            $table->decimal('weight_sas', 5, 2)->default(25.00);
            $table->unsignedTinyInteger('rounding_precision')->default(2);
            $table->string('agreement_note')->nullable();
            $table->timestamps();
        });

        Schema::create('student_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_class_id')->constrained('teaching_classes')->cascadeOnDelete();
            $table->foreignId('class_student_id')->constrained('class_students')->cascadeOnDelete();
            $table->decimal('attendance_score', 6, 2)->nullable();
            $table->decimal('sts_score', 6, 2)->nullable();
            $table->decimal('sas_score', 6, 2)->nullable();
            $table->json('dynamic_scores')->nullable();
            $table->decimal('avg_ph', 6, 2)->default(0);
            $table->decimal('avg_nt', 6, 2)->default(0);
            $table->decimal('final_score', 6, 2)->default(0);
            $table->timestamps();

            $table->unique(['teaching_class_id', 'class_student_id'], 'uniq_class_student_grade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_grades');
        Schema::dropIfExists('grade_weights');
        Schema::dropIfExists('grade_columns');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('daily_journals');
        Schema::dropIfExists('class_students');
        Schema::dropIfExists('teaching_classes');
        Schema::dropIfExists('subject_kktps');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('academic_years');
        Schema::dropIfExists('school_settings');
    }
};
