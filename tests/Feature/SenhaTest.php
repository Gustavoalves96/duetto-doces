<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('sempre grava a senha como bcrypt pelo model', function () {
    $usuario = User::create([
        'name' => 'Teste',
        'email' => 'teste@duettodoces.com.br',
        'password' => 'senhaemtextopuro',
    ]);

    $hash = $usuario->fresh()->password;

    expect($hash)->not->toBe('senhaemtextopuro')
        ->and($hash)->toStartWith('$2y$')
        ->and(Hash::check('senhaemtextopuro', $hash))->toBeTrue();
});

it('redefine a senha pelo comando usuario:senha', function () {
    $usuario = User::factory()->create(['email' => 'dono@duettodoces.com.br']);

    $this->artisan('usuario:senha', ['email' => 'dono@duettodoces.com.br'])
        ->expectsQuestion('Nova senha para '.$usuario->name, 'outrasenha123')
        ->assertSuccessful();

    expect(Hash::check('outrasenha123', $usuario->fresh()->password))->toBeTrue();
});

it('recusa senha curta', function () {
    User::factory()->create(['email' => 'curta@duettodoces.com.br']);

    $this->artisan('usuario:senha', ['email' => 'curta@duettodoces.com.br'])
        ->expectsQuestion('Nova senha para '.User::firstWhere('email', 'curta@duettodoces.com.br')->name, 'abc')
        ->assertFailed();
})->skip('o prompt revalida em loop, não dá para testar sem TTY');

it('avisa quando o e-mail não existe', function () {
    $this->artisan('usuario:senha', ['email' => 'ninguem@duettodoces.com.br'])
        ->expectsOutputToContain('Não existe usuário')
        ->assertFailed();
});
