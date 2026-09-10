<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;

/**
 * Redefine a senha de um usuário do painel.
 *
 * Existe para o caso de ficar trancado do lado de fora. No dia a dia a troca
 * é pela página de perfil, dentro do painel.
 *
 * A senha é gravada pelo model, então passa pelo cast 'hashed' e vira bcrypt.
 * Inserir usuário direto no banco (console SQL, DB::table()->insert()) pula
 * esse cast e grava a senha em texto puro — o login rejeita com
 * "This password does not use the Bcrypt algorithm".
 */
class DefinirSenha extends Command
{
    protected $signature = 'usuario:senha {email? : E-mail do usuário}';

    protected $description = 'Redefine a senha de um usuário do painel';

    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->escolherUsuario();

        if ($email === null) {
            $this->error('Nenhum usuário cadastrado. Use: php artisan make:filament-user');

            return self::FAILURE;
        }

        $usuario = User::firstWhere('email', $email);

        if (! $usuario) {
            $this->error("Não existe usuário com o e-mail {$email}.");

            return self::FAILURE;
        }

        $nova = password(
            label: "Nova senha para {$usuario->name}",
            required: true,
            validate: fn (string $valor) => strlen($valor) < 8
                ? 'A senha precisa ter pelo menos 8 caracteres.'
                : null,
        );

        // atribuição pelo model: o cast 'hashed' faz o bcrypt
        $usuario->password = $nova;
        $usuario->save();

        $this->info("Senha de {$usuario->email} redefinida.");
        $this->line('Confira o hash: '.substr($usuario->fresh()->password, 0, 7).'... (bcrypt)');

        return self::SUCCESS;
    }

    private function escolherUsuario(): ?string
    {
        $usuarios = User::orderBy('name')->pluck('email', 'email')->all();

        if ($usuarios === []) {
            return null;
        }

        return select(label: 'Qual usuário?', options: $usuarios);
    }
}
