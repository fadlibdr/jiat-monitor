<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tn_companies', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('name');
            $table->string('npwp', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('doc_prefix', 12)->nullable();
            $table->timestamps();
        });

        Schema::create('tn_company_user', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('tn_companies')->cascadeOnDelete();
            // site_staff | project_admin | finance | company_manager | system_admin
            $table->string('role', 30);
            $table->json('site_ids')->nullable();
            $table->timestamps();

            $table->primary(['user_id', 'company_id']);
        });

        Schema::create('tn_management_viewers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('role', 20); // holding_viewer | direksi
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tn_management_viewers');
        Schema::dropIfExists('tn_company_user');
        Schema::dropIfExists('tn_companies');
    }
};
