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
        Schema::table('teaching_classes', function (Blueprint $table) {
            $table->json('teaching_days')->nullable()->after('schedule_info');
            $table->boolean('only_teaching_days')->default(true)->after('show_nickname_column');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_classes', function (Blueprint $table) {
            $table->dropColumn(['teaching_days', 'only_teaching_days']);
        });
    }
};
