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
        if (Schema::hasColumn('class_students', 'nickname')) {
            Schema::table('class_students', function (Blueprint $table) {
                $table->dropColumn('nickname');
            });
        }

        if (Schema::hasColumn('teaching_classes', 'show_nickname_column')) {
            Schema::table('teaching_classes', function (Blueprint $table) {
                $table->dropColumn('show_nickname_column');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('class_students', 'nickname')) {
            Schema::table('class_students', function (Blueprint $table) {
                $table->string('nickname', 80)->nullable()->after('full_name');
            });
        }

        if (! Schema::hasColumn('teaching_classes', 'show_nickname_column')) {
            Schema::table('teaching_classes', function (Blueprint $table) {
                $table->boolean('show_nickname_column')->default(true)->after('schedule_info');
            });
        }
    }
};
