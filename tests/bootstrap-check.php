<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$failures = [];

foreach (glob(__DIR__ . '/../app/Models/*.php') as $file) {
    $class = 'App\\Models\\' . basename($file, '.php');
    if (!class_exists($class)) {
        $failures[] = $class;
    }
}

foreach (glob(__DIR__ . '/../app/Http/Controllers/Api/*.php') as $file) {
    $class = 'App\\Http\\Controllers\\Api\\' . basename($file, '.php');
    if (!class_exists($class)) {
        $failures[] = $class;
    }
}

foreach (glob(__DIR__ . '/../database/seeders/*.php') as $file) {
    $class = 'Database\\Seeders\\' . basename($file, '.php');
    if (!class_exists($class)) {
        $failures[] = $class;
    }
}

if ($failures) {
    echo "FAILED TO LOAD:\n" . implode("\n", $failures) . "\n";
    exit(1);
}

echo "ALL CLASSES LOADED OK\n";

$counts = [
    'User' => App\Models\User::count(),
    'SalesProfile' => App\Models\SalesProfile::count(),
    'Project' => App\Models\Project::count(),
    'Cluster' => App\Models\Cluster::count(),
    'Unit' => App\Models\Unit::count(),
    'PaymentScheme' => App\Models\PaymentScheme::count(),
    'CustomerLead' => App\Models\CustomerLead::count(),
    'FollowUpHistory' => App\Models\FollowUpHistory::count(),
    'Booking' => App\Models\Booking::count(),
    'Payment' => App\Models\Payment::count(),
    'SalesTarget' => App\Models\SalesTarget::count(),
    'Notification' => App\Models\Notification::count(),
];

foreach ($counts as $model => $count) {
    echo str_pad($model, 18) . ': ' . $count . "\n";
}

echo "\nBookings:\n";
foreach (App\Models\Booking::with('payments')->get() as $b) {
    printf(
        "  %s | status=%s | total=%s | dp=%s | cicilan=%s | sisa=%s | settled=%s\n",
        $b->booking_code,
        $b->status,
        number_format((float) $b->total_price, 0, ',', '.'),
        number_format((float) $b->down_payment_amount, 0, ',', '.'),
        number_format((float) $b->installment_amount, 0, ',', '.'),
        number_format((float) $b->remaining_amount, 0, ',', '.'),
        $b->settlementStatus()
    );
}

echo "\nKPR breakdown on Rp 1.050.000.000:\n";
print_r(App\Models\PaymentScheme::where('scheme_name', 'KPR')->first()->calculateBreakdown(1050000000));
