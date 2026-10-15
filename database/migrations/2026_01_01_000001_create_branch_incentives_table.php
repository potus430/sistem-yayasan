<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salaries', function (Blueprint $table): void {
            $table->json('incentive_details')->nullable()->after('incentives');
        });

        Schema::create('branch_incentives', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('branch_id')->index();
            $table->string('name', 100);
            $table->decimal('default_amount', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->timestamp('synced_at')->nullable();
            $table->unique(['branch_id', 'name']);
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_incentives');

        Schema::table('salaries', function (Blueprint $table): void {
            $table->dropColumn('incentive_details');
        });
    }
};
