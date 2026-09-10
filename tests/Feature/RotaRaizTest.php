<?php

use function Pest\Laravel\get;

it('manda a raiz para o painel', function () {
    get('/')->assertRedirect('/admin');
});
