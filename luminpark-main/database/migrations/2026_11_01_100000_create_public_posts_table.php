<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kabar terbaru yang tampil pada seksi "Kabar Terbaru" di halaman publik.
     * Sebelumnya isi seksi ini kerasandi di PublicInfoController.
     */
    public function up(): void
    {
        Schema::create('public_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('content');
            $table->date('posted_on');
            $table->string('image')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->index(['is_published', 'posted_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_posts');
    }
};
