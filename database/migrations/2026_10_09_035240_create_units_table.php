<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cluster_id')->constrained('clusters')->cascadeOnDelete();
            $table->string('unit_code', 30)->unique();
            $table->string('unit_type', 100);
            $table->integer('land_size');
            $table->integer('building_size');
            $table->decimal('price', 15, 2);
            $table->enum('status', ['available', 'booking', 'sold_out', 'delivery_process'])->default('available');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
