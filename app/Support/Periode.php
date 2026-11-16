<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Période d'analyse choisie sur les pages du super administrateur (?periode=…). */
class Periode
{
    public const CHOIX = [
        'semaine' => 'Cette semaine',
        'mois' => 'Ce mois',
        'mois_precedent' => 'Mois précédent',
        '30j' => '30 derniers jours',
        'trimestre' => '3 derniers mois',
        'annee' => 'Cette année',
    ];

    public function __construct(
        public readonly string $cle,
        public readonly CarbonImmutable $debut,
        public readonly CarbonImmutable $fin,
    ) {}

    public static function depuis(?string $cle): self
    {
        $cle = array_key_exists((string) $cle, self::CHOIX) ? $cle : 'mois';
        $now = CarbonImmutable::now();

        [$debut, $fin] = match ($cle) {
            'semaine' => [$now->startOfWeek(), $now->endOfWeek()],
            'mois_precedent' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            '30j' => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            'trimestre' => [$now->subMonthsNoOverflow(2)->startOfMonth(), $now->endOfMonth()],
            'annee' => [$now->startOfYear(), $now->endOfYear()],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };

        return new self($cle, $debut, $fin);
    }

    public function bornes(): array
    {
        return [$this->debut, $this->fin];
    }

    public function libelle(): string
    {
        return (self::CHOIX[$this->cle] ?? 'Période') . ' (' . $this->debut->format('d/m/Y') . ' – ' . $this->fin->format('d/m/Y') . ')';
    }

    public function versTableau(): array
    {
        return ['cle' => $this->cle, 'libelle' => $this->libelle(), 'choix' => self::CHOIX];
    }
}
