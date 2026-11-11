<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owners', function (Blueprint $table) {
            $table->id();
            $table->string('owner_code', 40)->unique();
            $table->string('owner_type', 20)->default('individual');
            $table->string('legal_name');
            $table->string('email')->nullable()->index();
            $table->string('phone', 30)->nullable()->index();
            $table->string('national_id_encrypted')->nullable();
            $table->string('tax_identifier')->nullable();
            $table->text('address')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('property_code', 40)->unique();
            $table->string('management_type', 20)->index(); // lrc_owned | client_managed
            $table->string('name');
            $table->string('property_type', 40)->nullable()->index();
            $table->string('country', 2)->default('RW');
            $table->string('province', 80)->nullable();
            $table->string('district', 80)->nullable()->index();
            $table->string('sector', 80)->nullable();
            $table->string('cell', 80)->nullable();
            $table->string('village', 80)->nullable();
            $table->string('street_address')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('property_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->constrained()->restrictOnDelete();
            $table->decimal('ownership_percentage', 5, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'effective_from', 'effective_to'], 'property_ownership_dates_idx');
            $table->index(['owner_id', 'effective_from', 'effective_to'], 'owner_ownership_dates_idx');
        });

        Schema::create('property_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('unit_code', 40);
            $table->string('unit_type', 40)->nullable();
            $table->string('floor_label', 40)->nullable();
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->unsignedSmallInteger('bathrooms')->nullable();
            $table->decimal('area_sqm', 10, 2)->nullable();
            $table->string('status', 20)->default('vacant')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['property_id', 'unit_code']);
        });

        Schema::create('management_agreements', function (Blueprint $table) {
            $table->id();
            $table->string('agreement_number', 50)->unique();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('fee_type', 20)->default('percentage'); // percentage | fixed
            $table->decimal('fee_value', 15, 2)->default(0);
            $table->string('payout_frequency', 20)->default('monthly');
            $table->unsignedSmallInteger('payout_day')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->string('document_path')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_agreements');
        Schema::dropIfExists('property_units');
        Schema::dropIfExists('property_owners');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('owners');
    }
};
