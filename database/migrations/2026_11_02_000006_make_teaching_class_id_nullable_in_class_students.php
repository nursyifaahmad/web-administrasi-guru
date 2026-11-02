<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_students', function (Blueprint $table) {
            $table->unsignedBigInteger('teaching_class_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('class_students', function (Blueprint $table) {
            $table->unsignedBigInteger('teaching_class_id')->nullable(false)->change();
        });
    }
};
