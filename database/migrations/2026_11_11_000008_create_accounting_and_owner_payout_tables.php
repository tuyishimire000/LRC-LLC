<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_code', 20)->unique();
            $table->string('name', 120);
            $table->string('account_type', 20); // asset, liability, equity, income, expense
            $table->string('normal_balance', 6); // debit, credit
            $table->boolean('is_control')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number', 50)->unique();
            $table->date('entry_date');
            $table->string('description', 255);
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['source_type', 'source_id'], 'journal_source_unique');
            $table->index(['entry_date', 'status']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('description', 255)->nullable();
            $table->timestamps();
            $table->index(['ledger_account_id', 'property_id']);
        });

        Schema::create('property_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number', 50)->unique();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('management_agreement_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('category', 50);
            $table->string('description', 255);
            $table->date('incurred_on');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('RWF');
            $table->boolean('charge_to_owner')->default(true);
            $table->string('status', 20)->default('pending')->index();
            $table->string('receipt_path')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_note')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'incurred_on', 'status']);
        });

        Schema::create('owner_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('payout_number', 50)->unique();
            $table->foreignId('owner_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('gross_collected', 15, 2);
            $table->decimal('management_fee', 15, 2);
            $table->decimal('owner_expenses', 15, 2);
            $table->decimal('net_amount', 15, 2);
            $table->string('currency', 3)->default('RWF');
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payout_reference', 120)->nullable();
            $table->text('approval_note')->nullable();
            $table->timestamps();
            $table->unique(['owner_id', 'property_id', 'period_start', 'period_end'], 'owner_property_period_payout_unique');
            $table->index(['property_id', 'period_start', 'status']);
        });

        Schema::create('owner_payout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_payout_id')->constrained()->restrictOnDelete();
            $table->string('item_type', 30); // rent_collection, management_fee, owner_expense
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description', 255);
            $table->decimal('amount', 15, 2); // positive for income, negative for deduction
            $table->timestamps();
            $table->index(['owner_payout_id', 'item_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_payout_items');
        Schema::dropIfExists('owner_payouts');
        Schema::dropIfExists('property_expenses');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('ledger_accounts');
    }
};
