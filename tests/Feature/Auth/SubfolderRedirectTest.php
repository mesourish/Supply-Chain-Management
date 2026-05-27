<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class SubfolderRedirectTest extends TestCase
{
    /**
     * Test that accessing the root route '/' redirects to '/login'.
     */
    public function test_root_route_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    /**
     * Test that the middleware corrects an intended URL that is missing the subdirectory path.
     */
    public function test_middleware_corrects_missing_subdirectory_in_intended_url(): void
    {
        // 1. Force config to a subdirectory structure
        Config::set('app.url', 'http://localhost/scm-erp');

        // 2. Put an intended URL missing the subdirectory into the session
        $this->withSession([
            'url.intended' => 'http://localhost/sales/quotations'
        ]);

        // 3. Make a request to a page that triggers the middleware stack (e.g., login route)
        $response = $this->get('/login');

        // 4. Assert that the session's intended URL was rewritten to include the subdirectory
        $response->assertSessionHas('url.intended', 'http://localhost/scm-erp/sales/quotations');
    }

    /**
     * Test that the middleware leaves a correct intended URL unchanged.
     */
    public function test_middleware_ignores_already_correct_intended_url(): void
    {
        Config::set('app.url', 'http://localhost/scm-erp');

        $this->withSession([
            'url.intended' => 'http://localhost/scm-erp/sales/quotations'
        ]);

        $response = $this->get('/login');

        $response->assertSessionHas('url.intended', 'http://localhost/scm-erp/sales/quotations');
    }

    /**
     * Test that the middleware does nothing when the app is not in a subdirectory.
     */
    public function test_middleware_does_nothing_if_no_app_subdirectory(): void
    {
        Config::set('app.url', 'http://localhost');

        $this->withSession([
            'url.intended' => 'http://localhost/sales/quotations'
        ]);

        $response = $this->get('/login');

        $response->assertSessionHas('url.intended', 'http://localhost/sales/quotations');
    }
}
