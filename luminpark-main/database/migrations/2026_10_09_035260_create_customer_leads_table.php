<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_id')->nullable()->constrained('sales_profiles')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('name', 100);
            $table->string('phone', 15);
            $table->string('email', 100)->nullable();
            $table->text('address')->nullable();
            $table->enum('status', ['cold', 'warm', 'hot', 'booking'])->default('cold');
            $table->string('interested_unit', 50)->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->text('note')->nullable();
            $table->date('last_follow_up')->nullable();
            $table->string('source', 30)->default('manual');
            $table->timestamps();

            $table->index('status');
            $table->index('sales_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_leads');
    }
};
