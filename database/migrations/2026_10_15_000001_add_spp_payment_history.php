<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spp_billings', function (Blueprint $table): void {
            $table->decimal('paid_amount', 15, 2)->default(0)->after('amount');
            $table->timestamp('paid_at')->nullable()->after('status');
        });

        DB::table('spp_billings')->where('status', 'PAID')->update([
            'paid_amount' => DB::raw('amount'),
            'paid_at' => DB::raw('updated_at'),
        ]);

        Schema::create('spp_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('spp_billing_id')->index();
            $table->uuid('branch_id')->index();
            $table->uuid('received_by')->nullable()->index();
            $table->decimal('amount', 15, 2);
            $table->string('reference', 100)->nullable();
            $table->string('payment_method', 30)->default('TUNAI');
            $table->text('notes')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();
            $table->foreign('spp_billing_id')->references('id')->on('spp_billings')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('received_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spp_payments');

        Schema::table('spp_billings', function (Blueprint $table): void {
            $table->dropColumn(['paid_amount', 'paid_at']);
        });
    }
};
