<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Support\Facades\Route;

/**
 * api/users dan api/users/{id} mengembalikan email + api_token user, jadi wajib login
 * (sebelumnya terdaftar di grup api tanpa auth dan bisa dibuka siapa saja).
 */
class UserApiAuthTest extends TestCase
{
    protected function defineRoutes($router)
    {
        Route::get('/login', fn () => 'login page')->name('login');
    }

    private function makeUser(string $email = 'budi@meindo.test'): User
    {
        return User::create(['name' => 'Budi', 'email' => $email, 'api_token' => md5($email)]);
    }

    public function test_guest_cannot_list_users(): void
    {
        $this->makeUser();

        $this->getJson('/api/users')->assertUnauthorized();
        $this->get('/api/users')->assertRedirect(route('login'));
    }

    public function test_guest_cannot_show_user_by_id_or_email(): void
    {
        $user = $this->makeUser();

        $this->getJson('/api/users/'.$user->id)->assertUnauthorized();
        $this->getJson('/api/users/'.$user->email)->assertUnauthorized();
    }

    public function test_logged_in_user_can_list_and_show_users(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->getJson('/api/users')
            ->assertOk()
            ->assertJsonPath('data.0.email', $user->email);

        $this->actingAs($user)->getJson('/api/users/'.$user->id)
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->actingAs($user)->getJson('/api/users/'.$user->email)
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->actingAs($user)->getJson('/api/users/999')->assertNotFound();
    }
}
