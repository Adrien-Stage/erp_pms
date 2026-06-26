<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Tenant;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

// Bind a fake request targeting business dashboard
$request = Request::create('/business/dashboard', 'GET');
$app->instance('request', $request);

$user = User::where('role', User::ROLE_OWNER)->first();
if ($user) {
    Auth::login($user);
}

$tabs = ['dashboard', 'establishments', 'analytics', 'employees', 'revenue'];
$allPassed = true;

foreach ($tabs as $tab) {
    try {
        $html = view('admin.dashboard', [
            'activeTab' => $tab,
            'subTab' => 'logs',
            'logs' => AuditLog::paginate(20),
            'users' => User::paginate(10),
            'tenants' => $user ? $user->tenants()->get() : Tenant::all(),
            'allUsers' => User::all(),
            'auditStats' => [
                'total_logs' => 0,
                'total_users' => 0,
                'active_users' => 0,
                'inactive_users' => 0,
                'total_tenants' => 0,
                'active_tenants' => 0,
                'access_denied' => 0,
                'failed_logins' => 0,
            ]
        ])->render();
        echo "SUCCESS: Rendered tab '$tab'\n";
    } catch (\Throwable $e) {
        $allPassed = false;
        echo "ERROR on tab '$tab': " . $e->getMessage() . "\n";
        echo $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

if ($allPassed) {
    echo "\nALL_BUSINESS_TABS_RENDERED_SUCCESSFULLY\n";
} else {
    echo "\nSOME_TABS_FAILED\n";
}

