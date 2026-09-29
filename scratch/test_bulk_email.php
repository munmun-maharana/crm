<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;

$admin = User::first();
$customers = Customer::take(2)->get();
$ids = $customers->pluck('id')->all();

echo "Testing bulkEmail with IDs: " . implode(',', $ids) . "\n";

$req = Request::create('/customers/bulk-email', 'POST', ['ids' => $ids]);
$req->setUserResolver(fn() => $admin);

$controller = $app->make(App\Http\Controllers\CustomerController::class);
$response = $controller->bulkEmail($req);

echo "Status: " . $response->getStatusCode() . "\n";
$data = json_decode($response->getContent(), true);

echo "Subject: " . ($data['subject'] ?? 'N/A') . "\n";
echo "Gmail URL: " . substr($data['gmail_url'] ?? '', 0, 100) . "...\n";
echo "\n--- DECODED BODY ---\n";
parse_str(parse_url($data['gmail_url'] ?? '', PHP_URL_QUERY), $query);
echo ($query['body'] ?? '') . "\n";
echo "\n--- HTML TABLE (Snippet) ---\n";
echo substr($data['html_table'] ?? '', 0, 350) . "...\n";
