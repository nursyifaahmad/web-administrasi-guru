<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('logo_left_size')->default(92)->after('logo_left_data');
            $table->unsignedSmallInteger('logo_right_size')->default(92)->after('logo_right_data');
        });
    }

    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropColumn(['logo_left_size', 'logo_right_size']);
        });
    }
};
