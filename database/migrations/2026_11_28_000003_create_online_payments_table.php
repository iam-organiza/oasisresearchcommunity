<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each attempt to pay a charge through Paystack. The amounts are fixed when
 * the member starts (the dollar figure included), and a payment is only
 * recorded once Paystack confirms that exact amount for that reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('currency', 3);
            $table->unsignedInteger('amount');
            $table->unsignedBigInteger('fee_minor');
            $table->unsignedBigInteger('total_minor');
            $table->decimal('naira_per_dollar', 12, 4)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('paystack_id', 40)->nullable();
            $table->string('channel', 40)->nullable();
            $table->string('gateway_response', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payments');
    }
};
