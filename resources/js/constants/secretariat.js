// Libellés et aides d'affichage partagés par l'espace secrétaire et l'espace admin « Secrétariat ».

export const PRIORITES = {
    haute: { label: 'Haute', classe: 'bg-red-100 text-red-700' },
    normale: { label: 'Normale', classe: 'bg-gray-100 text-gray-700' },
    basse: { label: 'Basse', classe: 'bg-sky-50 text-sky-700' },
};

export const STATUTS = {
    a_faire: { label: 'À faire', classe: 'bg-gray-100 text-gray-700' },
    en_cours: { label: 'En cours', classe: 'bg-amber-100 text-amber-800' },
    terminee: { label: 'Terminée', classe: 'bg-emerald-100 text-emerald-800' },
};

const HEURE = 3600 * 1000;
const JOUR = 24 * HEURE;

/** Durée lisible : « 2 j 3 h », « 5 h 20 min », « 12 min ». */
export function dureeLisible(ms) {
    const abs = Math.abs(ms);
    const j = Math.floor(abs / JOUR);
    const h = Math.floor((abs % JOUR) / HEURE);
    const min = Math.floor((abs % HEURE) / 60000);
    if (j > 0) return h > 0 ? `${j} j ${h} h` : `${j} j`;
    if (h > 0) return min > 0 ? `${h} h ${min} min` : `${h} h`;
    return `${Math.max(min, 1)} min`;
}

/**
 * État du délai d'une tâche.
 * niveau : 'termine' | 'retard' | 'urgent' (< 24 h) | 'proche' (< 3 j) | 'ok'
 */
export function etatDelai(tache, maintenant = Date.now()) {
    const echeance = new Date(tache.echeance).getTime();
    const reste = echeance - maintenant;

    if (tache.statut === 'terminee') {
        const fin = tache.terminee_at ? new Date(tache.terminee_at).getTime() : maintenant;
        return fin <= echeance
            ? { niveau: 'termine', texte: 'Terminée dans les délais', classe: 'text-emerald-700' }
            : { niveau: 'termine', texte: `Terminée avec ${dureeLisible(fin - echeance)} de retard`, classe: 'text-amber-700' };
    }
    if (reste < 0) return { niveau: 'retard', texte: `En retard de ${dureeLisible(reste)}`, classe: 'text-red-700' };
    if (reste < JOUR) return { niveau: 'urgent', texte: `Reste ${dureeLisible(reste)}`, classe: 'text-amber-700' };
    if (reste < 3 * JOUR) return { niveau: 'proche', texte: `Reste ${dureeLisible(reste)}`, classe: 'text-amber-600' };
    return { niveau: 'ok', texte: `Reste ${dureeLisible(reste)}`, classe: 'text-gray-600' };
}

export const dateHeure = (value) =>
    value
        ? new Date(value).toLocaleString('fr-FR', { weekday: 'short', day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
        : '—';

/** Valeur pour <input type="datetime-local"> (heure locale). */
export function versInputDateHeure(value) {
    const d = value ? new Date(value) : new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export const progression = (tache) => {
    const total = tache.items?.length ?? 0;
    const faits = tache.items?.filter((i) => i.fait).length ?? 0;
    return { total, faits, pourcent: total ? Math.round((faits / total) * 100) : (tache.statut === 'terminee' ? 100 : 0) };
};
