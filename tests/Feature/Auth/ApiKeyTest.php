<?php

test('home redirects to login when unauthenticated', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
