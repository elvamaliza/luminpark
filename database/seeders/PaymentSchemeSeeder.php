<?php

namespace Database\Seeders;

use App\Models\PaymentScheme;
use Illuminate\Database\Seeder;

class PaymentSchemeSeeder extends Seeder
{
    public function run(): void
    {
        $schemes = [
            [
                'scheme_name'          => 'KPR',
                'scheme_type'          => PaymentScheme::TYPE_KPR,
                'description'          => 'Kredit pemilikan rumah melalui bank. Uang muka 20%, angsuran pokok + bunga dihitung dengan suku bunga efektif.',
                'down_payment_percent' => 20,
                'installment_months'   => 180,
                'interest_rate'        => 9.5,
            ],
            [
                'scheme_name'          => 'Cash Keras',
                'scheme_type'          => PaymentScheme::TYPE_CASH_KERAS,
                'description'          => 'Pembayaran tunai tanpa kredit bank. Uang muka 50%, sisanya dilunaskan dalam 3 bulan tanpa bunga.',
                'down_payment_percent' => 50,
                'installment_months'   => 3,
                'interest_rate'        => 0,
            ],
            [
                'scheme_name'          => 'Cash Bertahap',
                'scheme_type'          => PaymentScheme::TYPE_CASH_BERTAHAP,
                'description'          => 'Pembayaran tunai dicicil langsung ke pengembang. Uang muka 30%, diangsur 36 bulan tanpa bunga bank.',
                'down_payment_percent' => 30,
                'installment_months'   => 36,
                'interest_rate'        => 0,
            ],
        ];

        foreach ($schemes as $scheme) {
            PaymentScheme::updateOrCreate(
                ['scheme_name' => $scheme['scheme_name']],
                $scheme + ['is_active' => true]
            );
        }
    }
}
