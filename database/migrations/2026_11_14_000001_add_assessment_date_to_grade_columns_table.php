<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_columns', function (Blueprint $table) {
            $table->date('assessment_date')->nullable()->after('topic_note');
            $table->index(['teaching_class_id', 'assessment_date'], 'idx_grade_cols_class_date');
        });
    }

    public function down(): void
    {
        Schema::table('grade_columns', function (Blueprint $table) {
            $table->dropIndex('idx_grade_cols_class_date');
            $table->dropColumn('assessment_date');
        });
    }
};
