<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\Intervention;
use App\Models\LigneDevis;
use App\Models\MvtStock;
use App\Models\Paiement;
use App\Models\Stock;
use App\Models\StockSiege;
use App\Models\User;
use App\Notifications\ActionSensible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Historique avant / après des modèles critiques + détection des actions sensibles
 * (facture modifiée après paiement, paiement supprimé, grosse remise, gros mouvement de stock…).
 */
class Audit
{
    /** Libellés lisibles des modèles suivis (clé = auditable_type enregistré). */
    public const MODELES = [
        'Facture' => 'Facture',
        'Paiement' => 'Paiement',
        'Devis' => 'Devis',
        'LigneDevis' => 'Ligne de devis',
        'Intervention' => 'Dossier',
        'Stock' => 'Pièce (stock)',
        'StockSiege' => 'Stock du siège',
        'MvtStock' => 'Mouvement de stock',
    ];

    /** Champs jamais historisés (bruit ou secrets). */
    private const IGNORES = ['updated_at', 'created_at', 'remember_token', 'password', 'portal_password', 'portal_temp_password'];

    public static function enregistrer(Model $modele, string $evenement): void
    {
        try {
            [$avant, $apres] = self::diff($modele, $evenement);

            // Modification sans changement utile (ex. seul updated_at a bougé)
            if ($evenement === 'modifie' && ! $avant && ! $apres) {
                return;
            }

            $request = app()->runningInConsole() && ! request()->route() ? null : request();
            $user = auth()->user();
            $impersonatorId = $request?->hasSession() ? $request->session()->get('impersonator_id') : null;
            $alerte = self::alerte($modele, $evenement, $avant, $apres);

            $log = AuditLog::create([
                'user_id' => $user instanceof User ? $user->id : null,
                'impersonator_id' => $impersonatorId,
                'auditable_type' => class_basename($modele),
                'auditable_id' => $modele->getKey(),
                'libelle' => mb_strimwidth(self::libelle($modele), 0, 250, '…'),
                'evenement' => $evenement,
                'avant' => $avant ?: null,
                'apres' => $apres ?: null,
                'siege' => self::siege($modele),
                'alerte' => $alerte ? mb_strimwidth($alerte, 0, 190, '…') : null,
                'ip' => $request?->ip(),
            ]);

            // Les actions du super administrateur lui-même (ou faites « en tant que ») ne lui sont pas signalées
            $parSuperAdmin = $impersonatorId || ($user instanceof User && $user->estSuperAdmin());
            if ($alerte && ! $parSuperAdmin) {
                self::prevenir(new ActionSensible('controle', $alerte, [
                    'audit_id' => $log->id,
                    'auteur' => $user instanceof User ? $user->name : 'Système',
                    'libelle' => $log->libelle,
                ]));
            }
        } catch (Throwable $e) {
            // L'audit ne doit jamais bloquer l'action de l'utilisateur.
            Log::warning('Audit impossible : ' . $e->getMessage());
        }
    }

    /** Notifie tous les super administrateurs. */
    public static function prevenir(ActionSensible $notification): void
    {
        $superAdmins = User::query()->where('role', 'admin')->where('is_superadmin', true)->get();
        if ($superAdmins->isNotEmpty()) {
            Notification::send($superAdmins, $notification);
        }
    }

    private static function diff(Model $modele, string $evenement): array
    {
        $filtrer = fn (array $valeurs) => array_diff_key($valeurs, array_flip(self::IGNORES));

        if ($evenement === 'cree') {
            return [[], $filtrer($modele->getAttributes())];
        }
        if ($evenement === 'supprime') {
            return [$filtrer($modele->getOriginal()), []];
        }

        $apres = $filtrer($modele->getChanges());
        $avant = array_intersect_key($modele->getOriginal(), $apres);

        // Ignore les changements purement de format (ex. "100.00" → 100)
        foreach ($apres as $cle => $valeur) {
            if (is_numeric($valeur) && is_numeric($avant[$cle] ?? null) && (float) $valeur === (float) $avant[$cle]) {
                unset($apres[$cle], $avant[$cle]);
            }
        }

        return [$avant, $apres];
    }

    /** Règles des actions sensibles. Retourne le motif de l'alerte, ou null. */
    private static function alerte(Model $m, string $evenement, array $avant, array $apres): ?string
    {
        $fcfa = fn ($n) => number_format((float) $n, 0, ',', ' ') . ' FCFA';

        if ($m instanceof Facture) {
            if ($evenement === 'supprime') {
                return 'Facture supprimée (' . $fcfa($avant['montant_total'] ?? 0) . ', déjà payé ' . $fcfa($avant['montant_paye'] ?? 0) . ')';
            }
            if ($evenement === 'modifie' && array_key_exists('montant_total', $apres) && (float) ($avant['montant_paye'] ?? $m->getOriginal('montant_paye') ?? 0) > 0) {
                return 'Montant de facture modifié après paiement : ' . $fcfa($avant['montant_total']) . ' → ' . $fcfa($apres['montant_total']);
            }
        }

        if ($m instanceof Paiement) {
            if ($evenement === 'supprime') {
                return 'Paiement supprimé : ' . $fcfa($avant['montant'] ?? 0);
            }
            if ($evenement === 'modifie' && array_key_exists('montant', $apres)) {
                return 'Paiement modifié : ' . $fcfa($avant['montant']) . ' → ' . $fcfa($apres['montant']);
            }
        }

        if ($m instanceof Devis) {
            $etaitAccepte = ($avant['statut'] ?? $m->getOriginal('statut') ?? $m->statut) === 'accepte';
            if ($evenement === 'supprime' && $etaitAccepte) {
                return 'Devis accepté supprimé (' . $fcfa($avant['montant_total_ttc'] ?? 0) . ')';
            }
            if ($evenement === 'modifie' && $etaitAccepte && array_key_exists('montant_total_ttc', $apres)) {
                return 'Devis accepté modifié : ' . $fcfa($avant['montant_total_ttc']) . ' → ' . $fcfa($apres['montant_total_ttc']);
            }
        }

        if ($m instanceof LigneDevis && $evenement !== 'supprime') {
            $seuil = (float) Parametres::get('alerte_remise_pct');
            $remise = (float) ($m->remise ?? 0);
            $remiseAvant = (float) ($avant['remise'] ?? 0);
            if ($seuil > 0 && $remise >= $seuil && ($evenement === 'cree' || array_key_exists('remise', $apres)) && $remise > $remiseAvant) {
                return 'Remise de ' . rtrim(rtrim(number_format($remise, 2, ',', ''), '0'), ',') . ' % sur « ' . mb_strimwidth((string) $m->designation, 0, 60, '…') . ' »';
            }
        }

        if ($m instanceof MvtStock) {
            if ($evenement === 'cree' && (int) $m->quantite >= (int) Parametres::get('alerte_mvt_stock_qte')) {
                return "Gros mouvement de stock : {$m->type} de {$m->quantite} pièce(s)" . ($m->siege ? " ({$m->siege})" : '');
            }
            if ($evenement === 'supprime') {
                return 'Mouvement de stock annulé : ' . ($avant['type'] ?? '') . ' de ' . ($avant['quantite'] ?? '?') . ' pièce(s)';
            }
        }

        if ($m instanceof Stock) {
            if ($evenement === 'supprime') {
                return 'Pièce supprimée du stock (' . ($avant['quantite'] ?? 0) . ' en stock)';
            }
            if ($evenement === 'modifie' && array_key_exists('quantite', $apres)) {
                $baisse = (int) $avant['quantite'] - (int) $apres['quantite'];
                if ($baisse >= (int) Parametres::get('alerte_mvt_stock_qte')) {
                    return "Quantité en stock diminuée à la main : {$avant['quantite']} → {$apres['quantite']}";
                }
            }
        }

        if ($m instanceof Intervention && $evenement === 'supprime') {
            return 'Dossier supprimé';
        }

        return null;
    }

    private static function libelle(Model $m): string
    {
        $type = self::MODELES[class_basename($m)] ?? class_basename($m);

        $detail = match (true) {
            $m instanceof Facture => $m->numero_facture,
            $m instanceof Intervention => $m->numero_ot,
            $m instanceof Stock => trim(($m->designation_piece ?? '') . ' ' . ($m->reference ? "({$m->reference})" : '')),
            $m instanceof LigneDevis => mb_strimwidth((string) $m->designation, 0, 60, '…'),
            $m instanceof StockSiege, $m instanceof MvtStock => $m->siege,
            default => null,
        };

        return trim($type . ' ' . ($detail ?: '#' . $m->getKey()));
    }

    private static function siege(Model $m): ?string
    {
        try {
            return match (true) {
                $m instanceof Intervention, $m instanceof StockSiege, $m instanceof MvtStock => $m->siege,
                $m instanceof Facture, $m instanceof Devis => Intervention::whereKey($m->intervention_id)->value('siege'),
                $m instanceof Paiement => Intervention::whereKey(Facture::whereKey($m->facture_id)->value('intervention_id'))->value('siege'),
                $m instanceof LigneDevis => Intervention::whereKey(Devis::whereKey($m->devis_id)->value('intervention_id'))->value('siege'),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }
}
