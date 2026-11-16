<?php

namespace App\Support;

use RuntimeException;

/** L'opération à annuler a été retouchée depuis : l'annulation doit être confirmée (forcée). */
class ConflitAnnulation extends RuntimeException
{
    public function __construct(public readonly array $conflits)
    {
        parent::__construct('Élément(s) modifié(s) depuis : ' . implode(' ; ', $conflits));
    }
}
