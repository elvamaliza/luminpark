<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_id')->constrained('sales_profiles')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('target_units')->default(0);
            $table->decimal('target_amount', 15, 2)->default(0);
            $table->integer('achieved_units')->default(0);
            $table->decimal('achieved_amount', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['sales_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_targets');
    }
};
