<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportedLoginTest extends TestCase
{
    use RefreshDatabase;

    private function importedUser(array $attributes = []): void
    {
        // Same shape as a Joomla row: a raw $2y$ bcrypt hash written straight to the table.
        DB::table('users')->insert(array_merge([
            'id' => 107,
            'name' => 'Николай',
            'username' => 'Nick V Reisswich',
            'email' => 'old@example.com',
            'password' => password_hash('joomla-secret', PASSWORD_BCRYPT, ['cost' => 10]),
            'locale' => 'ru',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    public function test_old_members_log_in_with_their_joomla_password_and_email()
    {
        $this->importedUser();

        $this->post('/login', ['email' => 'old@example.com', 'password' => 'joomla-secret'])->assertRedirect('/feed');
        $this->assertAuthenticatedAs(User::find(107));
    }

    public function test_old_members_log_in_with_their_username()
    {
        $this->importedUser();

        $this->post('/login', ['email' => 'Nick V Reisswich', 'password' => 'joomla-secret']);
        $this->assertAuthenticated();
    }

    public function test_blocked_members_cannot_log_in()
    {
        $this->importedUser(['blocked' => true]);

        $this->post('/login', ['email' => 'old@example.com', 'password' => 'joomla-secret']);
        $this->assertGuest();
    }
}
