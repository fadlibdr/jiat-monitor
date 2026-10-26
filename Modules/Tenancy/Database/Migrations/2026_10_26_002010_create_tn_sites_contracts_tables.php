<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tn_sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tn_companies');
            $table->string('code', 20);
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('province', 100)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('tn_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tn_companies');
            $table->foreignId('site_id')->constrained('tn_sites');
            $table->string('number', 100);
            $table->string('employer_name');
            $table->decimal('value_dpp', 18, 2);
            $table->decimal('ppn_rate', 7, 4)->default(11);
            $table->decimal('value_total', 18, 2);
            $table->date('signed_at')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tn_contracts');
        Schema::dropIfExists('tn_sites');
    }
};
