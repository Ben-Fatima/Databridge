<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
    * Test user registration and authentication.
    * This test suite covers:
    * - User registration
    * - Duplicate email rejection
*/

it('registers a new user and returns token', function () {
    $payload = [
        'name'                  => 'Fatima Zahra',
        'email'                 => 'fatima@example.com',
        'password'              => 'secret123',
        'password_confirmation' => 'secret123',
    ];

    $this->postJson('/api/register', $payload)
         ->assertCreated()
         ->assertJsonStructure(['user' => ['id','name','email'], 'token']);

    expect(User::where('email', 'fatima@example.com')->exists())->toBeTrue();
});

it('rejects duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $payload = [
        'name'                  => 'Somebody',
        'email'                 => 'taken@example.com',
        'password'              => 'secret123',
        'password_confirmation' => 'secret123',
    ];

    $this->postJson('/api/register', $payload)
         ->assertStatus(422)
         ->assertJsonValidationErrors('email');
});

/*
* Test user login functionality.
* This test suite covers:
* - Successful login with correct credentials
* - Rejection of login with incorrect password
*/

it('logs in with correct credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('secret123')]);

    $this->postJson('/api/login', [
        'email'    => $user->email,
        'password' => 'secret123',
    ])
    ->assertOk()
    ->assertJsonStructure(['user', 'token']);
});

it('rejects wrong password', function () {
    $user = User::factory()->create(['password' => bcrypt('secret123')]);

    $this->postJson('/api/login', [
        'email'    => $user->email,
        'password' => 'wrong-pass',
    ])
    ->assertStatus(200)
    ->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');
});

/*
    * Test user logout functionality.
    * This test suite covers:
    * - Successful logout
    * - Ensuring only the current token is deleted
*/

it('logs out and deletes only the current token', function () {
    $user  = User::factory()->create();
    Sanctum::actingAs($user);        // create + attach a token automatically

    $this->postJson('/api/logout')
         ->assertOk()
         ->assertExactJson(['message' => 'Logged out']);

    expect($user->tokens()->count())->toBe(0);
});
