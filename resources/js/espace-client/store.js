import { reactive } from 'vue'

// ===== Adresses =====
export const BASE = '/espace-client'
export const ROUTES = { home: BASE, dashboard: BASE + '/tableau-de-bord' }

// ===== Tarifs et coordonnees (a modifier) =====
export const TARIFS = {
  depannage: [
    { id: 'leger', nom: 'Dépanneuse légère', info: 'Citadine, berline', base: 15000, km: 1000 },
    { id: 'lourd', nom: 'Dépanneuse plateau', info: '4x4, pick-up, utilitaire', base: 25000, km: 1500 },
  ],
  convoyage: [
    { id: 'aller', nom: 'Aller simple', info: 'Domicile vers garage', base: 3000, km: 400, mult: 1 },
    { id: 'ar', nom: 'Aller-retour', info: 'Enlèvement et restitution', base: 5000, km: 400, mult: 2 },
  ],
}
export const GARAGE = [5.3364, -4.0267] // Coordonnees du garage a ajuster
export const ETAPES = ['Réception sur parc', 'Diagnostic atelier', 'Validation du devis', 'Réparation en cours', 'Contrôle qualité', 'Prêt à être restitué']

// ===== Utilitaires =====
export const fcfa = n => Math.round(n).toLocaleString('fr-FR') + ' FCFA'
export const totalDevis = d => d.lignes.reduce((s, l) => s + l[1], 0)
export const now = () => new Date().toLocaleString('fr-FR')
export const go = s => { window.location.hash = s }

// ===== Stockage (mode demo : localStorage). A remplacer par vos appels API. =====
const read = () => { try { return JSON.parse(localStorage.getItem('gk_db') || '{"users":[]}') } catch { return { users: [] } } }
const write = d => localStorage.setItem('gk_db', JSON.stringify(d))

export const state = reactive({ user: null, toasts: [] })

export function loadSession() {
  const id = localStorage.getItem('gk_session')
  state.user = id ? (read().users.find(u => u.id === id) || null) : null
  return state.user
}
export function save() {
  if (!state.user) return
  const d = read(); const i = d.users.findIndex(u => u.id === state.user.id)
  if (i >= 0) { d.users[i] = JSON.parse(JSON.stringify(state.user)); write(d) }
}
export function toast(t) {
  const id = Date.now() + Math.random()
  state.toasts.push({ id, t })
  setTimeout(() => { state.toasts = state.toasts.filter(x => x.id !== id) }, 3500)
}
export function notify(t) {
  state.user.notifs.unshift({ t, d: now(), lu: false }); save(); toast(t)
}

function seed(u) {
  u.devis = [{ ref: 'DV-2026-001', date: new Date().toLocaleDateString('fr-FR'), objet: 'Révision complète',
    lignes: [['Vidange moteur & filtres', 32500], ['Plaquettes de frein AV', 45000], ["Main d'oeuvre", 20000]], statut: 'En attente' }]
  u.reparations = []; u.suivi = { etape: 0, actif: false }
  u.missions = []; u.rdv = []; u.releves = []
  u.messages = { accueil: [{ me: false, t: 'Bienvenue chez Garage Kagnan. Comment pouvons-nous vous aider ?', d: now() }], atelier: [], facturation: [], depannage: [] }
  u.notifs = [{ t: 'Bienvenue dans votre Espace Client. Votre premier devis est disponible.', d: now(), lu: false }]
  return u
}

// Retournent un message d'erreur, ou null si tout va bien
export function signup(f) {
  const d = read()
  if (d.users.some(u => u.email === f.email || u.tel === f.tel)) return 'Un compte existe déjà avec cet email ou ce téléphone.'
  const u = seed({ id: 'C' + Date.now(), nom: f.nom, tel: f.tel, email: f.email, password: f.password,
    vehicules: [{ modele: f.vehicule, immat: f.immat }], adresse: '', prefs: { sms: true, email: true } })
  d.users.push(u); write(d); localStorage.setItem('gk_session', u.id)
  return null
}
export function login(id, password) {
  const u = read().users.find(u => (u.email === id || u.tel === id) && u.password === password)
  if (!u) return 'Identifiants incorrects.'
  localStorage.setItem('gk_session', u.id)
  return null
}
export function logout() { localStorage.removeItem('gk_session'); window.location.href = ROUTES.home }
export function deleteAccount() {
  const d = read(); d.users = d.users.filter(x => x.id !== state.user.id); write(d); logout()
}
