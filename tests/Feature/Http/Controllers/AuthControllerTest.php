<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

describe('login', function () {
    it('signs the user in and returns them', function () {
        $user = User::factory()->create();

        postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJson(['id' => $user->id, 'email' => $user->email]);

        assertAuthenticatedAs($user);
    });

    it('returns 422 when the password is wrong', function () {
        $user = User::factory()->create();

        postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'These credentials do not match our records.']);

        assertGuest();
    });

    it('returns 422 when the credentials are missing', function () {
        postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email' => 'The email field is required.',
                'password' => 'The password field is required.',
            ]);
    });

    it('returns 422 when the email is not an email address', function () {
        postJson('/api/login', ['email' => 'not-an-email', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The email field must be a valid email address.']);
    });

    it('returns 429 after five attempts in a minute', function () {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertUnprocessable();
        }

        postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertTooManyRequests();

        assertGuest();
    });
});

describe('user', function () {
    it('returns the authenticated user', function () {
        $user = User::factory()->create();

        actingAs($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJson(['id' => $user->id, 'name' => $user->name, 'email' => $user->email]);
    });

    it('does not expose the password hash or remember token', function () {
        actingAs(User::factory()->create())
            ->getJson('/api/user')
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('remember_token');
    });

    it('returns 401 for a guest', function () {
        getJson('/api/user')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    });
});

describe('logout', function () {
    it('signs the user out and returns 204', function () {
        actingAs(User::factory()->create())
            ->postJson('/api/logout')
            ->assertNoContent();

        assertGuest('web');
    });

    it('returns 401 for a guest', function () {
        postJson('/api/logout')->assertUnauthorized();
    });
});
