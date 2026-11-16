<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\Intervention;
use App\Models\LigneDevis;
use App\Models\Paiement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Annulation, par le super administrateur, d'une action tracée dans le journal d'audit.
 *
 * Toute l'opération (le « lot » : tout ce qu'une même requête a créé / modifié / supprimé) est
 * défaite d'un coup, de la dernière trace à la première, dans une transaction :
 *  - création    → l'élément est supprimé ;
 *  - modification → les anciennes valeurs sont remises ;
 *  - suppression → l'élément est recréé à l'identique (même identifiant).
 *
 * Si un élément a été retouché depuis par une autre opération, l'annulation est refusée
 * (sauf si elle est forcée) pour ne pas écraser ce travail sans le savoir.
 * Les annulations sont elles-mêmes tracées dans le journal (auteur : le super administrateur).
 */
class Annulation
{
    /** Traces non encore annulées de l'opération à laquelle appartient $log (la plus récente d'abord). */
    public static function operation(AuditLog $log): Collection
    {
        if (! $log->lot) {
            return collect($log->annule_le ? [] : [$log]);
        }

        return AuditLog::query()->where('lot', $log->lot)->whereNull('annule_le')->orderByDesc('id')->get();
    }

    /**
     * Éléments modifiés depuis par une autre opération (conflits). Liste de libellés, vide si aucun.
     */
    public static function conflits(Collection $traces): array
    {
        $conflits = [];
        foreach ($traces as $t) {
            $plusRecente = AuditLog::query()
                ->where('auditable_type', $t->auditable_type)
                ->where('auditable_id', $t->auditable_id)
                ->where('id', '>', $t->id)
                ->whereNull('annule_le')
                ->when($t->lot, fn ($q) => $q->where(fn ($w) => $w->whereNull('lot')->orWhere('lot', '!=', $t->lot)))
                ->with('user:id,name')
                ->latest('id')
                ->first();
            if ($plusRecente) {
                $conflits[$t->libelle] = "{$t->libelle} (retouché le " . $plusRecente->created_at?->format('d/m/Y H:i') . ' par ' . ($plusRecente->user?->name ?? 'Système') . ')';
            }
        }

        return array_values($conflits);
    }

    /**
     * Annule l'opération. Retourne le nombre de traces défaites.
     *
     * @throws RuntimeException message lisible en cas d'impossibilité
     */
    public static function annuler(AuditLog $log, User $par, bool $forcer = false): int
    {
        if ($log->annule_le) {
            throw new RuntimeException('Cette action a déjà été annulée.');
        }

        $traces = self::operation($log);
        if ($traces->isEmpty()) {
            throw new RuntimeException('Rien à annuler pour cette opération.');
        }

        if (! $forcer && ($conflits = self::conflits($traces))) {
            throw new ConflitAnnulation($conflits);
        }

        DB::transaction(function () use ($traces, $par) {
            foreach ($traces as $trace) {
                self::defaire($trace);
            }

            AuditLog::query()->whereIn('id', $traces->pluck('id'))->update(['annule_le' => now(), 'annule_par' => $par->id]);
        });

        return $traces->count();
    }

    private static function defaire(AuditLog $t): void
    {
        $classe = Audit::CLASSES[$t->auditable_type] ?? null;
        if (! $classe) {
            throw new RuntimeException("« {$t->libelle} » : ce type d'élément ne peut pas être restauré.");
        }

        /** @var Model|null $actuel */
        $actuel = $classe::find($t->auditable_id);

        try {
            match ($t->evenement) {
                'cree' => self::supprimerCree($t, $actuel),
                'modifie' => self::remettreValeurs($t, $actuel),
                'supprime' => self::recreer($t, $classe, $actuel),
                default => throw new RuntimeException("Événement inconnu : {$t->evenement}"),
            };
        } catch (QueryException $e) {
            throw new RuntimeException("« {$t->libelle} » n'a pas pu être restauré : un élément dont il dépend (dossier, devis, facture…) "
                . "n'existe plus. Annulez d'abord la suppression de cet élément parent.", 0, $e);
        }
    }

    private static function supprimerCree(AuditLog $t, ?Model $actuel): void
    {
        if (! $actuel) {
            return; // déjà supprimé depuis
        }

        // La base supprime les enfants en cascade sans trace : on refuse plutôt que de les perdre.
        $enfants = match (true) {
            $actuel instanceof Intervention => Devis::where('intervention_id', $actuel->id)->count() + Facture::where('intervention_id', $actuel->id)->count(),
            $actuel instanceof Devis => LigneDevis::where('devis_id', $actuel->id)->count(),
            $actuel instanceof Facture => Paiement::where('facture_id', $actuel->id)->count(),
            default => 0,
        };
        if ($enfants > 0) {
            throw new RuntimeException("« {$t->libelle} » contient encore {$enfants} élément(s) créés par une autre opération : annulez-les d'abord.");
        }

        $actuel->delete();
    }

    private static function remettreValeurs(AuditLog $t, ?Model $actuel): void
    {
        if (! $actuel) {
            throw new RuntimeException("« {$t->libelle} » n'existe plus : annulez d'abord sa suppression.");
        }
        $avant = self::brut($t->avant ?? []);
        if (! $avant) {
            return;
        }

        $actuel->setRawAttributes(array_merge($actuel->getAttributes(), $avant));
        $actuel->save();
    }

    private static function recreer(AuditLog $t, string $classe, ?Model $actuel): void
    {
        if ($actuel) {
            throw new RuntimeException("« {$t->libelle} » existe déjà (n° {$t->auditable_id}) : il a sans doute été restauré.");
        }
        $valeurs = self::brut($t->avant ?? []);
        if (! $valeurs) {
            throw new RuntimeException("« {$t->libelle} » : aucune donnée enregistrée pour le restaurer.");
        }

        /** @var Model $modele */
        $modele = new $classe();
        $modele->setRawAttributes($valeurs + [$modele->getKeyName() => $t->auditable_id]);
        $modele->save();
    }

    /**
     * Valeurs prêtes à être écrites en base. Les anciennes traces contenaient des valeurs « castées »
     * (tableaux, dates ISO) : on les remet au format de la base.
     */
    private static function brut(array $valeurs): array
    {
        foreach ($valeurs as $cle => $v) {
            if (is_array($v)) {
                $valeurs[$cle] = json_encode($v, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($v)) {
                $valeurs[$cle] = (int) $v;
            } elseif (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:?\d{2})$/', $v)) {
                $valeurs[$cle] = Carbon::parse($v)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
            }
        }

        return $valeurs;
    }
}
