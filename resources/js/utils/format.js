// Formats communs des pages du super administrateur.
const nf = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });

export const nombre = (n) => nf.format(Math.round(Number(n) || 0));
export const fcfa = (n) => `${nombre(n)} FCFA`;
export const compact = (v) => {
    const n = Number(v) || 0;
    if (Math.abs(n) >= 1e6) return `${(n / 1e6).toFixed(1).replace('.', ',')} M`;
    if (Math.abs(n) >= 1e3) return `${Math.round(n / 1e3)} k`;
    return nombre(n);
};
export const dateHeure = (d) => (d ? new Date(d).toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—');
export const date = (d) => (d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : '—');
export const pourcent = (n) => (n === null || n === undefined ? '—' : `${n} %`);
export const duree = (minutes) => {
    if (minutes === null || minutes === undefined) return '—';
    if (minutes < 60) return `${minutes} min`;
    const h = Math.floor(minutes / 60);
    if (h < 48) return `${h} h ${String(minutes % 60).padStart(2, '0')}`;
    return `${Math.round(h / 24)} j`;
};
