<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spp_class_fees', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('branch_id')->index();
            $table->uuid('academic_year_id')->index();
            $table->uuid('school_class_id')->index();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->unique(['branch_id', 'academic_year_id', 'school_class_id'], 'spp_class_fee_unique');
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->cascadeOnDelete();
            $table->foreign('school_class_id')->references('id')->on('school_classes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spp_class_fees');
    }
};
