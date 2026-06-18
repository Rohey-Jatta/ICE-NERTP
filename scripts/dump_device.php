<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Change device ID or user ID as needed
$device = App\Models\Device::find(1);
if (!$device) {
    echo "NO_DEVICE\n";
    exit(0);
}

echo "DEVICE_ID:" . $device->id . PHP_EOL;
echo "USER_ID:" . $device->user_id . PHP_EOL;
echo "FINGERPRINT_DATA:" . PHP_EOL;
echo $device->device_fingerprint_data . PHP_EOL;
