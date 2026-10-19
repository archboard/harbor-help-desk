<?php

test('home redirects to the ticket list', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect('/tickets');
});
