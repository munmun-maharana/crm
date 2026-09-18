<?php

use App\Models\InventoryItem;
use App\Models\InventoryType;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$types = InventoryType::ordered()->get()->keyBy('key');
$users = User::orderBy('id')->get();

if ($types->isEmpty() || $users->isEmpty()) {
    echo "Need inventory types and users first.\n";
    exit(1);
}

$admin = $users->firstWhere('role', 'admin') ?? $users->first();
$ops = $users->where('role', 'operation')->values();
$holderA = $ops[0] ?? $users[0];
$holderB = $ops[1] ?? ($users[1] ?? $users[0]);
$holderC = $users[2] ?? $holderA;

$demos = [
    // GPS device
    ['key' => 'gps_device', 'name' => 'Teltonika FMB920', 'code' => 'GPS-920-001', 'qty' => 25, 'status' => 'in_stock', 'held' => $holderA->id],
    ['key' => 'gps_device', 'name' => 'Teltonika FMB125', 'code' => 'GPS-125-014', 'qty' => 12, 'status' => 'in_stock', 'held' => $holderB->id],
    ['key' => 'gps_device', 'name' => 'Concox GT06N', 'code' => 'GPS-GT06-088', 'qty' => 8, 'status' => 'assigned', 'assign' => 'Fleet Corp — Truck 12'],
    ['key' => 'gps_device', 'name' => 'Queclink GV300', 'code' => 'GPS-GV300-003', 'qty' => 3, 'status' => 'damaged', 'held' => $holderA->id, 'notes' => 'Antenna broken'],
    ['key' => 'gps_device', 'name' => 'Jimi VL103', 'code' => 'GPS-VL103-021', 'qty' => 5, 'status' => 'repair', 'held' => $holderB->id, 'notes' => 'Sent to vendor'],

    // SIM
    ['key' => 'sim', 'name' => 'Airtel IoT SIM pack', 'code' => 'SIM-AIR-100', 'qty' => 40, 'status' => 'in_stock', 'held' => $holderA->id],
    ['key' => 'sim', 'name' => 'Jio M2M SIM', 'code' => 'SIM-JIO-55', 'qty' => 18, 'status' => 'in_stock', 'held' => $holderC->id],
    ['key' => 'sim', 'name' => 'VI Data SIM', 'code' => 'SIM-VI-009', 'qty' => 10, 'status' => 'assigned', 'assign' => 'Ramesh Logistics'],
    ['key' => 'sim', 'name' => 'BSNL IoT SIM', 'code' => 'SIM-BSNL-002', 'qty' => 2, 'status' => 'damaged', 'held' => $holderA->id],

    // License key
    ['key' => 'license_key', 'name' => 'Tracking Pro annual', 'code' => 'LIC-PRO-2026-A', 'qty' => 30, 'status' => 'in_stock', 'held' => $admin->id],
    ['key' => 'license_key', 'name' => 'Tracking Pro annual', 'code' => 'LIC-PRO-2026-B', 'qty' => 15, 'status' => 'assigned', 'assign' => 'Shree Transport'],
    ['key' => 'license_key', 'name' => 'Fuel module add-on', 'code' => 'LIC-FUEL-11', 'qty' => 6, 'status' => 'in_stock', 'held' => $holderB->id],
    ['key' => 'license_key', 'name' => 'API access key', 'code' => 'LIC-API-004', 'qty' => 1, 'status' => 'repair', 'held' => $admin->id, 'notes' => 'Key regenerate pending'],

    // Fuel sensor
    ['key' => 'fuel_sensor', 'name' => 'Capacitive fuel sensor 700mm', 'code' => 'FS-CAP-700-01', 'qty' => 14, 'status' => 'in_stock', 'held' => $holderA->id],
    ['key' => 'fuel_sensor', 'name' => 'Capacitive fuel sensor 1000mm', 'code' => 'FS-CAP-1000-07', 'qty' => 9, 'status' => 'in_stock', 'held' => $holderB->id],
    ['key' => 'fuel_sensor', 'name' => 'Ultrasonic fuel sensor', 'code' => 'FS-US-015', 'qty' => 4, 'status' => 'assigned', 'assign' => 'City Cab fleet'],
    ['key' => 'fuel_sensor', 'name' => 'Capacitive fuel sensor 700mm', 'code' => 'FS-CAP-700-19', 'qty' => 2, 'status' => 'damaged', 'held' => $holderC->id, 'notes' => 'Water ingress'],
    ['key' => 'fuel_sensor', 'name' => 'Calibration kit', 'code' => 'FS-CAL-001', 'qty' => 3, 'status' => 'repair', 'held' => $holderA->id],
];

$created = 0;
foreach ($demos as $row) {
    $type = $types[$row['key']] ?? null;
    if (! $type) {
        continue;
    }

    $exists = InventoryItem::where('code', $row['code'])->exists();
    if ($exists) {
        continue;
    }

    $item = InventoryItem::create([
        'inventory_type_id' => $type->id,
        'name' => $row['name'],
        'code' => $row['code'],
        'quantity' => $row['qty'],
        'status' => $row['status'],
        'held_by_user_id' => $row['held'] ?? null,
        'assigned_to_name' => $row['assign'] ?? null,
        'notes' => $row['notes'] ?? null,
    ]);

    $item->recordHistory(
        'created',
        'Demo entry created',
        'Type: '.$type->name.' · Qty: '.$item->quantity.' · Status: '.$item->statusLabel()
    );

    $created++;
}

echo "Demo inventory items added: {$created}\n";
echo 'Total items now: '.InventoryItem::count()."\n";
