<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_teacher_hourly_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('branch_id');
            $table->uuid('academic_year_id');
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'academic_year_id'], 'branch_teacher_hourly_rate_unique');
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->cascadeOnDelete();
        });

        Schema::table('salaries', function (Blueprint $table): void {
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->unsignedSmallInteger('teaching_hours')->default(0);
            $table->decimal('hourly_rate', 15, 2)->default(0);
        });

        DB::table('salaries')
            ->join('employees', 'employees.id', '=', 'salaries.employee_id')
            ->update(['salaries.base_salary' => DB::raw('employees.base_salary')]);
    }

    public function down(): void
    {
        Schema::table('salaries', function (Blueprint $table): void {
            $table->dropColumn(['base_salary', 'teaching_hours', 'hourly_rate']);
        });

        Schema::dropIfExists('branch_teacher_hourly_rates');
    }
};
