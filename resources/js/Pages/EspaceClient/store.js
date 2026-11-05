import { reactive } from 'vue'
import axios from 'axios'

export const BASE = '/espace-client'
export const ROUTES = { home: BASE, dashboard: BASE + '/tableau-de-bord' }
export const ETAPES = ['Réception sur parc', 'Diagnostic atelier', 'Validation du devis', 'Réparation en cours', 'Contrôle qualité', 'Prêt à être restitué']
export const state = reactive({ user: null, toasts: [] })

export const fcfa = amount => Math.round(Number(amount || 0)).toLocaleString('fr-FR') + ' FCFA'
export const go = section => { window.location.hash = section }

const statutDevis = {
  en_attente: 'En attente',
  accepte: 'Accepté',
  valide: 'Accepté',
  refuse: 'Refusé',
}

function mapClient(client) {
  const vehicles = client.vehicules.map(vehicule => ({
    id: vehicule.id,
    marque: vehicule.marque || '',
    modele: `${vehicule.marque || ''} ${vehicule.modele || ''}`.trim() || 'Véhicule',
    immat: vehicule.immatriculation,
    expiration_assurance: vehicule.expiration_assurance,
    expiration_sicta: vehicule.expiration_sicta,
    kilometrage: vehicule.interventions[0]?.kilometrage ?? null,
  }))
  const interventions = client.vehicules.flatMap(vehicule =>
    vehicule.interventions.map(intervention => ({
      ...intervention,
      vehicule: {
        id: vehicule.id,
        modele: `${vehicule.marque || ''} ${vehicule.modele || ''}`.trim() || 'Véhicule',
        immat: vehicule.immatriculation,
      },
      facture: intervention.facture ? {
        ...intervention.facture,
        paiements: intervention.facture.paiements || [],
      } : null,
    }))
  )
  interventions.sort((a, b) => new Date(b.date_reception || 0) - new Date(a.date_reception || 0))
  const devis = interventions.flatMap(intervention => {
    if (!intervention.devis) return []
    const lignes = intervention.devis.lignes.map(line => ({
      ...line,
      quantite: Number(line.quantite),
      pu_net: Number(line.pu_net),
      remise: Number(line.remise),
      montant_ht: Number(line.montant_ht),
      montant_ttc: Number(line.montant_ttc),
    }))

    return [{
      id: intervention.devis.id,
      ref: `DV-${String(intervention.devis.id).padStart(6, '0')}`,
      date: intervention.devis.created_at,
      objet: `${intervention.vehicule.modele || ''}`.trim() || 'Intervention atelier',
      statut: statutDevis[intervention.devis.statut] || intervention.devis.statut,
      lignes,
      petite_fourniture_active: intervention.devis.petite_fourniture_active,
      petite_fourniture_montant: intervention.devis.petite_fourniture_montant === null
        ? null
        : Number(intervention.devis.petite_fourniture_montant),
      numero_ot: intervention.numero_ot,
      selectionPossible: ['en_attente', 'accepte'].includes(intervention.devis.statut)
        && ['attente_accord', 'accepte'].includes(intervention.statut)
        && !intervention.facture?.paiements?.length,
    }]
  })

  const activeIntervention = interventions.find(({ statut }) => !['termine', 'facture', 'annule'].includes(statut))
  const lastIntervention = interventions[0]

  return {
    id: client.id,
    identifier: client.identifier,
    nom: `${client.prenom} ${client.nom}`.trim(),
    prenom: client.prenom,
    nomFamille: client.nom,
    tel: client.telephone,
    email: client.email || '',
    adresse: client.adresse || '',
    siege: client.siege?.nom || client.siege?.code || '',
    demandes: client.demandes || [],
    vehicules: vehicles,
    interventions,
    devis,
    reparations: interventions
      .filter(({ statut }) => ['termine', 'facture'].includes(statut))
      .map(intervention => ({
        date: intervention.date_reception,
        objet: intervention.numero_ot || 'Intervention atelier',
        lieu: 'Garage Kagnan',
        montant: Number(intervention.facture?.montant_total || intervention.devis?.montant_total_ttc || 0),
      })),
    suivi: {
      actif: Boolean(activeIntervention),
      statut: activeIntervention?.statut || null,
      objet: activeIntervention?.numero_ot || 'Intervention atelier',
    },
    dernierKilometrage: lastIntervention?.kilometrage ?? null,
  }
}

export async function loadSession() {
  try {
    const { data } = await axios.get(`${BASE}/api/me`)
    state.user = mapClient(data.client)
    return true
  } catch (error) {
    state.user = null
    if (error.response?.status !== 401 && error.response?.status !== 423) throw error
    return false
  }
}

export async function logout() {
  await axios.post(`${BASE}/deconnexion`)
  state.user = null
  window.location.href = ROUTES.home
}

export function toast(message) {
  const id = Date.now() + Math.random()
  state.toasts.push({ id, t: message })
  setTimeout(() => {
    state.toasts = state.toasts.filter(item => item.id !== id)
  }, 3500)
}
