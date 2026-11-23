<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['superadmin', 'manager', 'admin_sales', 'sales', 'consumer'])->default('consumer')->after('email');
            $table->string('phone', 15)->nullable()->after('role');
            $table->text('address')->nullable()->after('phone');
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif')->after('address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'address', 'status']);
        });
    }
};
