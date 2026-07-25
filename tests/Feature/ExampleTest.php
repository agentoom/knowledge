<?php

test('home page redirects to login when unauthenticated', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});
