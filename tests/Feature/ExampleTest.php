<?php

test('the application returns a successful response', function () {
    $response = $this->get('/ecommerce');

    $response->assertStatus(200);
});
