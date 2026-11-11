<?php

use Illuminate\Support\Carbon;

it('usa o fuso de São Paulo', function () {
    expect(config('app.timezone'))->toBe('America/Sao_Paulo');
});

it('mantém a venda das 22h30 no mesmo dia e no mesmo mês', function () {
    // 01h30 UTC do dia 1º ainda é 22h30 do dia 30 em São Paulo
    Carbon::setTestNow(Carbon::parse('2026-12-01 01:30:00', 'UTC'));

    expect(now()->toDateString())->toBe('2026-11-30')
        ->and(now()->startOfMonth()->toDateString())->toBe('2026-11-01');
});
