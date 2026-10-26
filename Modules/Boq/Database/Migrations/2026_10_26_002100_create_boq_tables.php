<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boq_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tn_companies');
            $table->foreignId('contract_id')->constrained('tn_contracts');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 80);            // full path, e.g. II.A.1 (unique per contract)
            $table->string('code_printed', 40)->nullable(); // exactly as printed in the RAB
            $table->text('description');
            $table->string('unit', 20)->nullable();
            $table->decimal('qty', 18, 4)->nullable();
            $table->decimal('unit_price', 18, 2)->nullable();
            $table->decimal('amount', 18, 2);
            $table->decimal('weight_pct', 9, 6)->default(0);
            // material | upah | alat | subkon | lain
            $table->string('category', 20)->nullable();
            $table->unsignedSmallInteger('level')->default(0);
            $table->boolean('is_leaf')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['contract_id', 'code']);
            $table->index('parent_id');
            $table->index(['company_id', 'contract_id']);
        });

        Schema::create('boq_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tn_companies');
            $table->foreignId('boq_item_id')->constrained('boq_items')->cascadeOnDelete();
            $table->date('period');
            $table->decimal('planned_pct', 7, 4);
            $table->timestamps();

            $table->unique(['boq_item_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boq_schedules');
        Schema::dropIfExists('boq_items');
    }
};
