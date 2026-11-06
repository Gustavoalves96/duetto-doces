<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile;
use Filament\Schemas\Components\Component;

/**
 * Só diz ao navegador o que cada campo guarda, para o gerenciador de senhas
 * salvar o e-mail como login em vez de adivinhar.
 */
class EditarPerfil extends EditProfile
{
    protected function getNameFormComponent(): Component
    {
        return parent::getNameFormComponent()->autocomplete('name');
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()->autocomplete('username');
    }
}
