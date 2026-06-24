<?php

it('redirects home to active invoices', function () {
    $this->get('/')->assertRedirect(route('invoices.active'));
});
