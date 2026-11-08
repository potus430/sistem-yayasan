<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_applications', function (Blueprint $table): void {
            $table->string('type', 20)->default('ADMISSION')->after('academic_year_id');
        });

        Schema::table('students', function (Blueprint $table): void {
            $table->string('enrollment_type', 20)->default('NEW')->after('admission_academic_year_id');
        });

        Schema::create('class_capacities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('branch_id')->index();
            $table->uuid('academic_year_id')->index();
            $table->string('class_name', 50);
            $table->unsignedSmallInteger('capacity');
            $table->timestamps();
            $table->timestamp('synced_at')->nullable();
            $table->unique(['branch_id', 'academic_year_id', 'class_name'], 'class_capacity_branch_year_name_unique');
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_capacities');
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn('enrollment_type');
        });
        Schema::table('admission_applications', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};
