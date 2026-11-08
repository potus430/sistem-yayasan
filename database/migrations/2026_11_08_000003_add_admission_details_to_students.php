<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->uuid('admission_academic_year_id')->nullable()->index();
            $table->string('gender', 20)->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('previous_school')->nullable();
            $table->foreign('admission_academic_year_id')
                ->references('id')
                ->on('academic_years')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropForeign(['admission_academic_year_id']);
            $table->dropColumn([
                'admission_academic_year_id',
                'gender',
                'birth_place',
                'birth_date',
                'guardian_name',
                'guardian_phone',
                'address',
                'previous_school',
            ]);
        });
    }
};
