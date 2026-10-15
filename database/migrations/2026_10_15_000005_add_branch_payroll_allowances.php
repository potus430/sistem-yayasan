<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->decimal('monthly_transport_allowance', 15, 2)->default(0)->after('phone');
            $table->decimal('substitution_allowance_per_period', 15, 2)->default(0)->after('monthly_transport_allowance');
        });

        Schema::table('salaries', function (Blueprint $table): void {
            $table->json('allowance_details')->nullable()->after('allowances');
        });
    }

    public function down(): void
    {
        Schema::table('salaries', function (Blueprint $table): void {
            $table->dropColumn('allowance_details');
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn(['monthly_transport_allowance', 'substitution_allowance_per_period']);
        });
    }
};
