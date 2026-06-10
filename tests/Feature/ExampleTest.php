<?php

it('redirects home to dashboard', function () {
    $this->get('/')->assertRedirect(route('dashboard'));
});
