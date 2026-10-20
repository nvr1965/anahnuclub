<?php

namespace Tests\Feature;

use App\Console\Commands\ImportJoomla;
use App\Models\User;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

class ImportJoomlaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.joomla' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        $old = Schema::connection('joomla');
        $old->create('users', function ($t) {
            $t->integer('id');
            $t->string('name');
            $t->string('username');
            $t->string('email');
            $t->string('password');
            $t->text('params')->nullable();
            $t->boolean('block')->default(false);
            $t->string('activation')->default('');
            $t->string('registerDate')->nullable();
            $t->string('lastvisitDate')->nullable();
        });
        $old->create('community_users', function ($t) {
            $t->integer('userid');
            $t->string('avatar')->nullable();
            $t->string('cover')->nullable();
            $t->string('status')->nullable();
        });
        $old->create('user_usergroup_map', function ($t) {
            $t->integer('user_id');
            $t->integer('group_id');
        });
    }

    private function oldMember(int $id, string $username, string $email, string $name = 'Old Name'): void
    {
        DB::connection('joomla')->table('users')->insert([
            'id' => $id, 'name' => $name, 'username' => $username, 'email' => $email,
            'password' => bcrypt('old-password'), 'registerDate' => '2015-01-01 10:00:00',
        ]);
    }

    private function importUsers(): void
    {
        $command = app(ImportJoomla::class);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));
        $set = fn ($name, $value) => (fn () => $this->{$name} = $value)->call($command);
        $set('old', DB::connection('joomla'));
        (fn () => $this->importUsers())->call($command);
    }

    public function test_a_re_run_keeps_what_members_changed_here()
    {
        $this->oldMember(5, 'anna', 'anna@example.com');
        $this->importUsers();
        User::find(5)->forceFill(['name' => 'Anna New', 'password' => bcrypt('new-password'), 'is_admin' => true])->save();

        $this->importUsers();

        $anna = User::find(5);
        $this->assertSame('Anna New', $anna->name);
        $this->assertTrue($anna->is_admin);
        $this->assertTrue(password_verify('new-password', $anna->password));
    }

    public function test_an_old_member_whose_id_is_taken_here_stops_the_import()
    {
        User::factory()->create(['id' => 7, 'email' => 'dana@example.com', 'username' => null]);
        $this->oldMember(7, 'boris', 'boris@example.com');

        $this->expectExceptionMessage('#7 boris@example.com');
        $this->importUsers();
    }

    public function test_a_member_who_changed_their_email_here_is_still_the_same_person()
    {
        $this->oldMember(9, 'vera', 'vera@example.com');
        $this->importUsers();
        User::find(9)->update(['email' => 'vera.new@example.com']);

        $this->importUsers();

        $this->assertSame('vera.new@example.com', User::find(9)->email);
    }

    public function test_old_picture_links_lead_to_the_copied_pictures()
    {
        $file = storage_path('app/public/joomla/images/photos/test-pic.jpg');
        @mkdir(dirname($file), 0777, true);
        file_put_contents($file, 'x');

        $this->get('/images/photos/test-pic.jpg')->assertRedirect('/storage/joomla/images/photos/test-pic.jpg');
        $this->get('/images/photos/missing.jpg')->assertNotFound();
        $this->get('/images/../../.env')->assertNotFound();

        unlink($file);
    }
}
