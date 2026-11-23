<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code', 30)->unique();
            $table->foreignId('lead_id')->constrained('customer_leads')->cascadeOnDelete();
            $table->foreignId('sales_id')->constrained('sales_profiles')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units');
            $table->foreignId('payment_scheme_id')->constrained('payment_schemes');
            $table->date('booking_date');
            $table->decimal('total_price', 15, 2);
            $table->decimal('down_payment_amount', 15, 2);
            $table->decimal('installment_amount', 15, 2);
            $table->decimal('remaining_amount', 15, 2);
            $table->enum('status', ['menunggu dp', 'dp lunas', 'cicilan', 'lunas'])->default('menunggu dp');
            $table->enum('delivery_status', ['belum proses', 'sedang proses', 'selesai'])->default('belum proses');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('booking_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
