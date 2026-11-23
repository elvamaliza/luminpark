<?php

namespace Database\Seeders;

use App\Models\CustomerLead;
use App\Models\SalesProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Empat aktor laporan: Super Admin (role super_admin), Admin Sales, Sales, dan Konsumen.
     * Konsumen tidak memiliki akun login karena seluruh interaksinya lewat halaman publik.
     * Aktor manajer dan admin lama adalah orang yang sama dengan super admin, sehingga perannya
     * sudah dilebur dan tidak ada lagi role manajer pada akun pengguna.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Super Admin', 'email' => 'superadmin@gmail.com', 'role' => User::ROLE_SUPER_ADMIN, 'phone' => '0811000000002'],
            ['name' => 'Admin Sales', 'email' => 'admin.sales@luminpark.com', 'role' => User::ROLE_ADMIN_SALES, 'phone' => '0811000000003'],
            ['name' => 'Budi Sales Utama', 'email' => 'sales@luminpark.com', 'role' => User::ROLE_SALES, 'phone' => '0812345678101', 'sales_code' => 'SL-001', 'whatsapp' => '62812345678101', 'birth_date' => '1995-03-14'],
            ['name' => 'Sari Sales Timur', 'email' => 'sales2@luminpark.com', 'role' => User::ROLE_SALES, 'phone' => '0812345678102', 'sales_code' => 'SL-002', 'whatsapp' => '62812345678102', 'birth_date' => '1997-11-02'],
            ['name' => 'Andi Sales Barat', 'email' => 'sales3@luminpark.com', 'role' => User::ROLE_SALES, 'phone' => '0812345678103', 'sales_code' => 'SL-003', 'whatsapp' => '62812345678103', 'birth_date' => '1993-07-21'],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name'              => $data['name'],
                    'password'          => 'password123',
                    'role'              => $data['role'],
                    'phone'             => $data['phone'],
                    'address'           => 'Jl. Contoh No. 1, Semarang',
                    'status'            => User::STATUS_AKTIF,
                    'must_change_password' => false,
                    'email_verified_at' => now(),
                ]
            );

            if ($data['role'] === User::ROLE_SALES) {
                SalesProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'sales_code'      => $data['sales_code'],
                        'join_date'       => now()->subMonths(8),
                        'birth_date'      => $data['birth_date'],
                        'status'          => 'aktif',
                        'whatsapp_number' => $data['whatsapp'],
                    ]
                );
            }
        }

        // Akun konsumen hasil rancangan lama dihapus: konsumen tidak lagi masuk ke sistem.
        User::where('role', User::ROLE_CONSUMER)->delete();

        // Akun admin dan manajer hasil rancangan lama dihapus: super admin cukup superadmin@gmail.com.
        User::whereIn('email', ['admin@luminpark.com', 'manager@luminpark.com'])->delete();

        // Beberapa lead belum tertangani (dicatat sales dari kunjungan situs web).
        CustomerLead::updateOrCreate(
            ['phone' => '0812000000001'],
            [
                'name'   => 'Calon Konsumen Web',
                'email'  => 'web.lead@example.com',
                'status' => CustomerLead::STATUS_COLD,
                'source' => 'website',
                'note'   => 'Dicatat sales setelah menghubungi narahubung pada halaman publik.',
            ]
        );
    }
}
