<?php

use App\Models\Project;
use App\Models\User;
use Laravel\Dusk\Browser;

it('sends a guest to the login screen', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/chat')
            ->waitForLocation('/login')
            ->assertSee('Sign in');
    });
});

it('shows an error when the credentials are wrong', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->visit('/login')
            ->waitFor('input[type=email]')
            ->type('input[type=email]', $user->email)
            ->type('input[type=password]', 'wrong-password')
            ->press('Sign in')
            ->waitForText('These credentials do not match our records.')
            ->assertPathIs('/login');
    });
});

it('signs in and opens the chat with the projects', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);
    Project::factory()->create(['name' => 'Demo Shop', 'slug' => 'demo-shop']);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->visit('/login')
            ->waitFor('input[type=email]')
            ->type('input[type=email]', $user->email)
            ->type('input[type=password]', 'password')
            ->press('Sign in')
            ->waitForLocation('/chat')
            ->waitForText('Ada Lovelace')
            ->waitFor('select option[value=demo-shop]')
            ->assertSelected('select', 'demo-shop');
    });
});

it('signs out and returns to the login screen', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/chat')
            ->waitForText('Log out')
            ->press('Log out')
            ->waitForLocation('/login')
            ->visit('/chat')
            ->waitForLocation('/login');
    });
});
