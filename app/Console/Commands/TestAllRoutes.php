<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class TestAllRoutes extends Command
{
    protected $signature = 'app:test-all-routes';
    protected $description = 'Test all routes internally';

    public function handle()
    {
        $user = User::where('email', 'admin@example.com')->first();
        Auth::login($user);

        $routes = [
            '/',
            '/dashboard',
            '/products',
            '/warehouses',
            '/suppliers',
            '/customers',
            '/inventory/log',
            '/procurement/purchase-orders',
            '/sales/orders',
            '/admin/roles',
            '/admin/users',
            '/logistics/vehicles',
            '/logistics/drivers',
            '/logistics/shipments',
            '/procurement/grn',
            '/sales/fulfillment',
            '/finance/payables',
            '/finance/receivables',
            '/sales/returns',
        ];

        $app = app();
        $errors = 0;

        foreach ($routes as $route) {
            $request = Request::create($route, 'GET');
            
            // Bypass URL issues in testing environment by forcing standard request handling
            $response = $app->handle($request);

            if ($response->getStatusCode() === 200) {
                $this->info("OK: {$route}");
            } else {
                $this->error("FAILED: {$route} - Status {$response->getStatusCode()}");
                if (isset($response->exception) && $response->exception) {
                    $this->error($response->exception->getMessage());
                }
                $errors++;
            }
        }

        if ($errors === 0) {
            $this->info("All routes returned 200 OK!");
        } else {
            $this->error("Found $errors errors.");
        }
    }
}
