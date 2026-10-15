<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_positions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('branch_id')->index();
            $table->uuid('academic_year_id')->index();
            $table->string('name', 100);
            $table->boolean('requires_class')->default(false);
            $table->decimal('monthly_salary_addition', 15, 2)->default(0);
            $table->decimal('monthly_allowance', 15, 2)->default(0);
            $table->decimal('monthly_incentive', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->timestamp('synced_at')->nullable();
            $table->unique(['branch_id', 'academic_year_id', 'name'], 'branch_year_position_name_unique');
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->cascadeOnDelete();
        });

        Schema::create('employee_position_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('branch_id')->index();
            $table->uuid('academic_year_id')->index();
            $table->uuid('employee_id')->index();
            $table->uuid('branch_position_id')->index();
            $table->string('class_name', 50)->nullable();
            $table->string('assignment_scope', 50)->storedAs("COALESCE(class_name, '__SINGLE__')");
            $table->timestamps();
            $table->timestamp('synced_at')->nullable();
            $table->unique(
                ['branch_id', 'academic_year_id', 'employee_id', 'branch_position_id'],
                'employee_branch_year_position_unique'
            );
            $table->unique(
                ['branch_id', 'academic_year_id', 'branch_position_id', 'assignment_scope'],
                'position_assignment_scope_unique'
            );
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('branch_position_id')->references('id')->on('branch_positions')->cascadeOnDelete();
        });

        Schema::table('salaries', function (Blueprint $table): void {
            $table->decimal('position_salary', 15, 2)->default(0)->after('incentive_details');
            $table->json('position_details')->nullable()->after('position_salary');
        });
    }

    public function down(): void
    {
        Schema::table('salaries', function (Blueprint $table): void {
            $table->dropColumn(['position_salary', 'position_details']);
        });

        Schema::dropIfExists('employee_position_assignments');
        Schema::dropIfExists('branch_positions');
    }
};
