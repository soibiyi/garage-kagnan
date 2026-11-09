// Libellés et styles partagés par les sections de l'espace client.
const LIBELLES = {
  reception: 'Réception du véhicule',
  atelier: 'À l’atelier',
  en_cours: 'Intervention en cours',
  diagnostic: 'Diagnostic',
  attente_accord: 'En attente de votre accord',
  accepte: 'Devis accepté',
  facture: 'Facturé',
  livre: 'Terminé',
  termine: 'Terminé',
  annule: 'Annulé',
}

export const libelleStatut = statut => LIBELLES[statut] || statut?.replaceAll('_', ' ') || 'Statut non renseigné'

export const classeStatut = statut => ({
  attente_accord: 'st-wait',
  accepte: 'st-ok',
  livre: 'st-ok',
  termine: 'st-ok',
  facture: 'st-ok',
  annule: 'st-no',
})[statut] || 'st-info'

export const estInterventionTerminee = statut => ['livre', 'termine', 'facture'].includes(statut)

export const formatDate = value => value ? new Date(value).toLocaleDateString('fr-FR') : '—'
