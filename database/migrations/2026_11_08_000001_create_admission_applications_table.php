<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('branch_id')->index();
            $table->uuid('academic_year_id')->nullable()->index();
            $table->string('registration_number', 30);
            $table->string('name');
            $table->string('nisn', 30)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('guardian_name');
            $table->string('guardian_phone', 30);
            $table->text('address');
            $table->string('previous_school')->nullable();
            $table->string('applied_class', 50);
            $table->string('status', 20)->default('REGISTERED');
            $table->text('notes')->nullable();
            $table->uuid('student_id')->nullable()->unique();
            $table->timestamps();
            $table->unique(['branch_id', 'registration_number']);
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_applications');
    }
};
