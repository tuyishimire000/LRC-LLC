<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rent_charges', function (Blueprint $table) {
            $table->id();
            $table->string('charge_number', 50)->unique();
            $table->foreignId('lease_id')->constrained()->restrictOnDelete();
            $table->date('billing_period');
            $table->date('charged_on');
            $table->date('due_on');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('RWF');
            $table->string('status', 20)->default('open')->index();
            $table->text('description')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['lease_id', 'billing_period'], 'rent_charge_lease_period_unique');
            $table->index(['due_on', 'status']);
        });

        Schema::create('rent_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 50)->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('RWF');
            $table->string('payment_method', 30);
            $table->string('external_reference', 120)->nullable()->unique();
            $table->date('paid_on');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_note')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'paid_on']);
        });

        Schema::create('rent_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rent_payment_id')->constrained('rent_payments')->restrictOnDelete();
            $table->foreignId('rent_charge_id')->constrained('rent_charges')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->unique(['rent_payment_id', 'rent_charge_id'], 'rent_payment_charge_unique');
            $table->index('rent_charge_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_payment_allocations');
        Schema::dropIfExists('rent_payments');
        Schema::dropIfExists('rent_charges');
    }
};