<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('scheme_name', 50)->unique();
            $table->text('description')->nullable();
            $table->enum('scheme_type', ['KPR', 'cash keras', 'cash bertahap']);
            $table->decimal('down_payment_percent', 5, 2);
            $table->integer('installment_months');
            $table->decimal('interest_rate', 5, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_schemes');
    }
};
