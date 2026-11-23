<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_up_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('customer_leads')->cascadeOnDelete();
            $table->foreignId('sales_id')->constrained('sales_profiles')->cascadeOnDelete();
            $table->dateTime('follow_up_date');
            $table->enum('status_before', ['cold', 'warm', 'hot', 'booking']);
            $table->enum('status_after', ['cold', 'warm', 'hot', 'booking']);
            $table->enum('method', ['whatsapp', 'phone', 'visit', 'email']);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'follow_up_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_histories');
    }
};
