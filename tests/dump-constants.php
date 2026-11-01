<?php
// Daftar konstanta status tiap model — dipakai frontend untuk badge dan dropdown.
require __DIR__ . '/../vendor/autoload.php';
foreach (['Unit', 'Booking', 'Payment', 'CustomerLead', 'SalesProfile'] as $model) {
    $class = 'App\\Models\\' . $model;
    echo "== {$model}\n";
    foreach ((new ReflectionClass($class))->getConstants() as $name => $value) {
        echo '  ' . $name . ' = ' . json_encode($value) . "\n";
    }
}
