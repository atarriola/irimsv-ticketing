<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the index page shows the login form to guests', function () {
    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});
