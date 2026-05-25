<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LoginAndConsoleTest extends DuskTestCase
{
    /**
     * A Dusk test example.
     */
    public function testLoginAndNoErrors(): void
    {
        $this->browse(function (Browser $browser) {
            // Check login
            $browser->visit('/login')
                    ->assertSee('Log in')
                    ->type('email', 'admin@example.com')
                    ->type('password', 'password')
                    ->press('LOG IN')
                    ->waitForLocation('/dashboard')
                    ->assertPathIs('/dashboard')
                    ->assertSee('Admin Dashboard');

            // Collect browser logs to ensure no JS errors
            $logs = $browser->driver->manage()->getLog('browser');
            $errors = [];
            foreach ($logs as $log) {
                if ($log['level'] === 'SEVERE') {
                    $errors[] = $log['message'];
                }
            }

            $this->assertEmpty($errors, 'Browser console has errors: ' . implode("\n", $errors));
        });
    }
}
