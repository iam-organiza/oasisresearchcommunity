<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Member accounts: every fee is a charge, payments and waivers go against a
 * charge, and every change is logged. Replaces the register's two ticks
 * (induction fee received, induction fee waived): each member gets an
 * induction charge carrying over what the ticks said.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20);
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('grade', 20)->nullable();
            $table->unsignedInteger('amount');
            $table->string('waiver_type', 20)->nullable();
            $table->unsignedInteger('waiver_value')->nullable();
            $table->unsignedInteger('waived_amount')->default(0);
            $table->string('waiver_reason', 500)->nullable();
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('waived_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'kind']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_number', 40)->unique();
            $table->unsignedInteger('amount');
            $table->string('method', 30);
            $table->string('reference', 100)->nullable();
            $table->date('paid_on');
            $table->string('note', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('account_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('charge_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->unsignedInteger('amount')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->timestamp('activated_at')->nullable()->after('joined_on');
        });

        $this->carryOverTicks();

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn(['induction_paid_at', 'fee_waived']);
        });
    }

    /**
     * Every member gets an induction charge at their grade's fee. A waived
     * fee becomes a full waiver; a received fee becomes a payment of the full
     * fee (the tick never recorded an amount or a date paid).
     */
    private function carryOverTicks(): void
    {
        $now = now();
        $receipts = [];

        DB::table('memberships')->orderBy('id')->get()->each(function (object $membership) use ($now, &$receipts) {
            $amount = (int) config("membership.grades.{$membership->grade}.induction_fee", 0);
            $waived = (bool) $membership->fee_waived;

            $chargeId = DB::table('charges')->insertGetId([
                'user_id' => $membership->user_id,
                'membership_id' => $membership->id,
                'kind' => 'induction',
                'grade' => $membership->grade,
                'amount' => $amount,
                'waiver_type' => $waived ? 'full' : null,
                'waived_amount' => $waived ? $amount : 0,
                'waiver_reason' => $waived ? 'Waived before fees and payments were recorded on the website.' : null,
                'waived_at' => $waived ? $now : null,
                'created_by' => $membership->recorded_by,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('account_events')->insert([
                'user_id' => $membership->user_id, 'charge_id' => $chargeId, 'action' => 'charge_added',
                'amount' => $amount, 'created_at' => $now,
            ]);

            if (! $waived && $membership->induction_paid_at !== null && $amount > 0) {
                $year = (int) substr((string) $membership->induction_paid_at, 0, 4);
                $receipts[$year] = ($receipts[$year] ?? 0) + 1;
                $paymentId = DB::table('payments')->insertGetId([
                    'charge_id' => $chargeId,
                    'user_id' => $membership->user_id,
                    'receipt_number' => sprintf('OASIS/RCT/%d/%05d', $year, $receipts[$year]),
                    'amount' => $amount,
                    'method' => 'before_website',
                    'paid_on' => substr((string) $membership->induction_paid_at, 0, 10),
                    'note' => 'Marked as received on the member register.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('account_events')->insert([
                    'user_id' => $membership->user_id, 'charge_id' => $chargeId, 'payment_id' => $paymentId,
                    'action' => 'payment_recorded', 'amount' => $amount, 'created_at' => $now,
                ]);
            }

            $active = $waived || $membership->induction_paid_at !== null;
            DB::table('memberships')->where('id', $membership->id)->update([
                'activated_at' => $active ? ($membership->induction_paid_at ?? $now) : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->timestamp('induction_paid_at')->nullable();
            $table->boolean('fee_waived')->default(false);
        });

        DB::table('memberships')->orderBy('id')->get()->each(function (object $membership) {
            $charge = DB::table('charges')->where('membership_id', $membership->id)
                ->where('kind', 'induction')->whereNull('cancelled_at')->first();
            DB::table('memberships')->where('id', $membership->id)->update([
                'fee_waived' => $charge !== null && $charge->waiver_type === 'full',
                'induction_paid_at' => $charge !== null && $charge->waiver_type !== 'full' ? $membership->activated_at : null,
            ]);
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn('activated_at');
        });
        Schema::dropIfExists('account_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('charges');
    }
};
