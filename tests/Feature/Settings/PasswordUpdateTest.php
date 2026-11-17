<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/settings/password')
        ->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/password');

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/settings/password')
        ->put('/settings/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect('/settings/password');
});

test('a Google sign-up (no password of their own) sets one without a current password', function () {
    $user = User::factory()->create(['google_id' => 'g-1']);
    $user->forceFill(['password_set_at' => null])->save();
    expect($user->fresh()->hasOwnPassword())->toBeFalse();

    $this->actingAs($user)->get('/settings/password')
        ->assertInertia(fn ($page) => $page->where('hasPassword', false));

    $this->actingAs($user)->from('/settings/password')->put('/settings/password', [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasNoErrors();

    $user->refresh();
    expect(Hash::check('new-password', $user->password))->toBeTrue()
        ->and($user->hasOwnPassword())->toBeTrue();

    // From now on the current password is asked for.
    $this->actingAs($user)->from('/settings/password')->put('/settings/password', [
        'password' => 'other-password',
        'password_confirmation' => 'other-password',
    ])->assertSessionHasErrors('current_password');
});

test('an account with its own password must give the current one', function () {
    $user = User::factory()->create();
    expect($user->hasOwnPassword())->toBeTrue();

    $this->actingAs($user)->get('/settings/password')
        ->assertInertia(fn ($page) => $page->where('hasPassword', true));

    $this->actingAs($user)->from('/settings/password')->put('/settings/password', [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('Google sign-up stores a random password that is not counted as their own', function () {
    $googleUser = new Laravel\Socialite\Two\User;
    $googleUser->map(['id' => 'g-42', 'name' => 'Ali', 'email' => 'ali@example.com', 'avatar' => null]);
    $googleUser->token = 'token';

    $provider = Mockery::mock();
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($googleUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

    $user = User::where('email', 'ali@example.com')->firstOrFail();
    expect($user->password)->not->toBeNull()
        ->and($user->password_set_at)->toBeNull()
        ->and($user->hasOwnPassword())->toBeFalse();
});
