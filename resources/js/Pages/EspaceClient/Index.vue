<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import './espace-client.css'
import './landing.css'
import { loadSession, ROUTES } from './store.js'

const props = defineProps({
  sieges: {
    type: Object,
    required: true,
  },
})

const open = ref(false)
const busy = ref(false)
const year = new Date().getFullYear()
const authMode = ref('login')
const credentials = ref({ identifier: '', password: '' })
const loginError = ref('')
const registration = ref({
  prenom: '',
  nom: '',
  telephone: '',
  siege: '',
  email: '',
  password: '',
  password_confirmation: '',
  add_vehicle: false,
  vehicle_marque: '',
  vehicle_modele: '',
  vehicle_immatriculation: '',
  vehicle_vin: '',
  vehicle_expiration_assurance: '',
  vehicle_expiration_sicta: '',
})
const registrationError = ref('')
const mustChangePassword = ref(false)
const passwordForm = ref({ current_password: '', password: '', password_confirmation: '' })
const passwordError = ref('')

/* ---------- Interface ---------- */
const showPwd = ref(false)
const scrolled = ref(false)
const guideTab = ref('vehicules')
const faqOpen = ref(0)

const guideTabs = [
  { id: 'vehicules', label: 'Suivre mes véhicules', icon: 'fa-car-side' },
  { id: 'devis', label: 'Comprendre mes devis', icon: 'fa-file-invoice' },
  { id: 'compte', label: 'Mon compte et sécurité', icon: 'fa-lock' },
]

const faq = [
  { q: 'Qui peut créer un compte ?', a: 'Tout client du Garage Kagnan. Il suffit d’indiquer votre prénom, votre nom, votre téléphone et le siège auquel vous souhaitez être rattaché. L’adresse email est facultative.' },
  { q: 'Dois-je ajouter un véhicule dès l’inscription ?', a: 'Non. L’ajout d’un véhicule est facultatif à la création du compte. Si vous le faites, le modèle, l’immatriculation, le VIN et les dates d’assurance et de SICTA sont obligatoires ; la marque est facultative.' },
  { q: 'Où trouver mon identifiant client ?', a: 'Votre identifiant client (de la forme GK-KEY-XXXXXX-ID-123) vous permet de vous reconnecter. Il est associé à votre dossier : notez-le et conservez-le. En cas de doute, demandez-le au garage.' },
  { q: 'J’ai reçu un mot de passe provisoire, que faire ?', a: 'Connectez-vous avec cet identifiant et ce mot de passe : le site vous demandera aussitôt de choisir votre mot de passe personnel (10 caractères minimum). Le mot de passe provisoire ne fonctionne qu’une seule fois.' },
  { q: 'Les informations sont-elles à jour ?', a: 'Les données affichées sont celles saisies par le Garage Kagnan sur votre dossier. Elles apparaissent dans votre espace dès que le garage les enregistre.' },
  { q: 'Comment modifier mes coordonnées ?', a: 'Depuis la rubrique « Mes informations » de votre espace, vous pouvez vérifier et mettre à jour votre téléphone et votre email.' },
]

const demoLines = ref([
  { l: 'Plaquettes de frein avant', p: 28000, on: true },
  { l: 'Main-d’œuvre freinage', p: 15000, on: true },
  { l: 'Huile moteur 5W30 (5 L)', p: 22000, on: true },
  { l: 'Filtre à huile', p: 6500, on: false },
])
const demoTotal = computed(() => demoLines.value.reduce((s, x) => s + (x.on ? x.p : 0), 0))
const fcfa = (n) => new Intl.NumberFormat('fr-FR').format(n).replace(/\s/g, '\u00A0') + '\u00A0FCFA'

const pwdScore = computed(() => {
  const p = registration.value.password
  if (!p) return 0
  let s = 0
  if (p.length >= 10) s++
  if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s++
  if (/\d/.test(p)) s++
  if (/[^A-Za-z0-9]/.test(p) || p.length >= 14) s++
  return Math.max(s, 1)
})
const pwdLabels = ['', 'Trop faible', 'Moyen', 'Bon', 'Excellent']
const pwdState = computed(() => {
  const { password: a, password_confirmation: b } = registration.value
  if (!b) return ''
  return a === b ? 'good' : 'bad'
})
const newPwdState = computed(() => {
  const { password: a, password_confirmation: b } = passwordForm.value
  if (!b) return ''
  return a === b ? 'good' : 'bad'
})

function goTo(id) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function openGuide(tab) {
  guideTab.value = tab
  goTo('guide')
}

function openAuth(mode = 'login', siege = '') {
  authMode.value = mode
  loginError.value = ''
  registrationError.value = ''
  if (siege) registration.value.siege = siege
  open.value = true
}

function onKey(e) {
  if (e.key === 'Escape') open.value = false
}

function onScroll() {
  scrolled.value = window.scrollY > 8
}

function ensureIcons() {
  if (document.querySelector('link[href*="font-awesome"],link[href*="fontawesome"]')) return
  const link = document.createElement('link')
  link.rel = 'stylesheet'
  link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css'
  document.head.appendChild(link)
}

watch(open, (v) => { document.body.style.overflow = v ? 'hidden' : '' })

async function login() {
  busy.value = true
  loginError.value = ''

  try {
    const { data } = await axios.post(`${ROUTES.home}/connexion`, credentials.value)
    if (data.must_change_password) {
      passwordForm.value.current_password = credentials.value.password
      mustChangePassword.value = true
      return
    }

    window.location.href = ROUTES.dashboard
  } catch (error) {
    loginError.value = error.response?.data?.message || 'Connexion impossible. Réessayez.'
  } finally {
    busy.value = false
  }
}

async function register() {
  busy.value = true
  registrationError.value = ''

  const data = { ...registration.value }
  if (!data.add_vehicle) {
    delete data.vehicle_marque
    delete data.vehicle_modele
    delete data.vehicle_immatriculation
    delete data.vehicle_vin
    delete data.vehicle_expiration_assurance
    delete data.vehicle_expiration_sicta
  }

  try {
    await axios.post(`${ROUTES.home}/inscription`, data)
    window.location.href = ROUTES.dashboard
  } catch (error) {
    registrationError.value = error.response?.data?.message
      || Object.values(error.response?.data?.errors || {}).flat()[0]
      || 'Impossible de créer votre compte. Réessayez.'
  } finally {
    busy.value = false
  }
}

async function changePassword() {
  busy.value = true
  passwordError.value = ''

  try {
    await axios.patch(`${ROUTES.home}/api/mot-de-passe`, passwordForm.value)
    window.location.href = ROUTES.dashboard
  } catch (error) {
    passwordError.value = error.response?.data?.message
      || Object.values(error.response?.data?.errors || {}).flat()[0]
      || 'Impossible de modifier le mot de passe.'
  } finally {
    busy.value = false
  }
}

onMounted(async () => {
  document.title = 'Espace Client — Garage Kagnan'
  ensureIcons()
  window.addEventListener('keydown', onKey)
  window.addEventListener('scroll', onScroll, { passive: true })

  try {
    if (await loadSession()) window.location.href = ROUTES.dashboard
  } catch {
    open.value = true
    loginError.value = 'Impossible de vérifier votre session. Réessayez.'
  }

  if (location.hash === '#connexion') openAuth()
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKey)
  window.removeEventListener('scroll', onScroll)
  document.body.style.overflow = ''
})
</script>

<template>
  <div class="ec landing">
    <header class="topbar" :class="{ scrolled }">
      <a href="/" class="brand"><img src="/images/logo-kagnan.png" alt="Garage Kagnan" class="brand-logo"></a>
      <nav aria-label="Navigation principale">
        <div class="links">
          <a href="#fonctionnalites" @click.prevent="goTo('fonctionnalites')">Fonctionnalités</a>
          <a href="#guide" @click.prevent="goTo('guide')">Guide d’utilisation</a>
          <a href="#sieges" @click.prevent="goTo('sieges')">Nos sièges</a>
          <a href="#faq" @click.prevent="goTo('faq')">Questions fréquentes</a>
        </div>
        <button class="btn btn-ghost" @click="openAuth('login')">Se connecter</button>
        <button class="btn btn-primary" @click="openAuth('register')">Créer mon compte</button>
      </nav>
    </header>

    <section class="hero">
      <div class="hero-text">
        <span class="tag"><i class="fa-solid fa-circle-user"></i> Espace Client Garage Kagnan</span>
        <h1>Retrouvez le suivi de vos véhicules, <span class="accent">en toute simplicité.</span></h1>
        <p>Consultez les informations de vos véhicules, l’avancement des interventions et vos devis enregistrés au Garage Kagnan.</p>
        <div class="hero-cta">
          <button class="btn btn-primary btn-lg" @click="openAuth('register')">Créer mon compte <i class="fa-solid fa-arrow-right go"></i></button>
          <button class="btn btn-ghost btn-lg" @click="openAuth('login')">J’ai déjà un compte</button>
        </div>
        <div class="hero-cta hero-note">
          <span><i class="fa-solid fa-mobile-screen"></i> Sur téléphone et ordinateur</span>
          <span><i class="fa-solid fa-shield-halved"></i> Accès protégé par mot de passe</span>
        </div>
      </div>
      <div class="hero-card">
        <div class="mock-head"><i class="fa-solid fa-gauge-high"></i> Votre espace client</div>
        <div class="mock-vehicle">
          <span class="plate">2381 GK 01</span>
          <small>Toyota Corolla</small>
        </div>
        <div class="mock-steps">
          <div class="mock-step done" style="--i:0"><span class="d"><i class="fa-solid fa-check"></i></span>Véhicules enregistrés</div>
          <div class="mock-step done" style="--i:1"><span class="d"><i class="fa-solid fa-check"></i></span>Historique des interventions</div>
          <div class="mock-step active" style="--i:2"><span class="d"><i class="fa-solid fa-file-invoice"></i></span>Devis et état de suivi</div>
        </div>
        <div class="mock-notif"><i class="fa-solid fa-circle-info"></i> Votre compte crée automatiquement votre dossier client.</div>
        <small class="mock-tag">Aperçu illustratif</small>
      </div>
    </section>

    <div class="trust">
      <div><i class="fa-solid fa-clock"></i> Consultable à toute heure</div>
      <div><i class="fa-solid fa-folder-open"></i> Un dossier unique par client</div>
      <div><i class="fa-solid fa-user-lock"></i> Vos données restent privées</div>
      <div><i class="fa-solid fa-headset"></i> Le garage reste joignable</div>
    </div>

    <section id="fonctionnalites" class="sec">
      <div class="features">
        <div class="sec-head">
          <h2>Les informations de votre garage, au même endroit</h2>
          <p class="sub">Les données affichées sont celles associées à votre dossier client.</p>
        </div>
        <div class="grid">
          <div class="feat"><div class="ico"><i class="fa-solid fa-car-side"></i></div><h3>Vos véhicules</h3><p>Retrouvez les véhicules enregistrés sur votre fiche client.</p><button class="feat-link" @click="openGuide('vehicules')">Voir comment ça marche <i class="fa-solid fa-arrow-right"></i></button></div>
          <div class="feat"><div class="ico"><i class="fa-solid fa-screwdriver-wrench"></i></div><h3>Suivi atelier</h3><p>Consultez l’état des interventions réalisées sur vos véhicules.</p><button class="feat-link" @click="openGuide('vehicules')">Voir comment ça marche <i class="fa-solid fa-arrow-right"></i></button></div>
          <div class="feat"><div class="ico"><i class="fa-solid fa-file-invoice"></i></div><h3>Vos devis</h3><p>Consultez les devis saisis par le Garage Kagnan.</p><button class="feat-link" @click="openGuide('devis')">Voir comment ça marche <i class="fa-solid fa-arrow-right"></i></button></div>
          <div class="feat"><div class="ico"><i class="fa-solid fa-clock-rotate-left"></i></div><h3>Historique</h3><p>Retrouvez les interventions et devis liés à votre dossier.</p><button class="feat-link" @click="openGuide('devis')">Voir comment ça marche <i class="fa-solid fa-arrow-right"></i></button></div>
          <div class="feat"><div class="ico"><i class="fa-solid fa-address-card"></i></div><h3>Vos informations</h3><p>Vérifiez et mettez à jour vos coordonnées de contact.</p><button class="feat-link" @click="openGuide('compte')">Voir comment ça marche <i class="fa-solid fa-arrow-right"></i></button></div>
          <div class="feat"><div class="ico"><i class="fa-solid fa-user-plus"></i></div><h3>Inscription libre</h3><p>Créez votre compte en ligne ; l’ajout d’un véhicule pourra se faire plus tard.</p><button class="feat-link" @click="openAuth('register')">Créer mon compte <i class="fa-solid fa-arrow-right"></i></button></div>
        </div>
      </div>
    </section>

    <section id="etapes" class="sec">
      <div class="sec-head">
        <h2>Prêt en trois étapes</h2>
        <p>Pas d’application à installer : tout se passe depuis votre navigateur.</p>
      </div>
      <div class="flow">
        <div class="flow-step"><span class="num"><i class="fa-solid fa-user-plus"></i></span><div><h3>Créez votre compte</h3><p>Prénom, nom, téléphone et siège de rattachement. Une minute suffit.</p></div></div>
        <div class="flow-step"><span class="num"><i class="fa-solid fa-folder-open"></i></span><div><h3>Votre dossier est prêt</h3><p>Un dossier client est créé automatiquement au siège choisi. Vous pouvez y ajouter un véhicule.</p></div></div>
        <div class="flow-step"><span class="num"><i class="fa-solid fa-gauge-high"></i></span><div><h3>Suivez tout en ligne</h3><p>Véhicules, interventions et devis sont consultables à chaque connexion.</p></div></div>
      </div>
    </section>

    <section id="guide" class="sec">
      <div class="sec-head">
        <h2>Le guide d’utilisation</h2>
        <p>Tout ce qu’il faut savoir avant votre première connexion. Essayez l’exemple de devis : cochez et décochez les lignes.</p>
      </div>
      <div class="guide">
        <div class="guide-tabs" role="tablist">
          <button v-for="t in guideTabs" :key="t.id" role="tab" :aria-selected="guideTab === t.id" :class="{ on: guideTab === t.id }" @click="guideTab = t.id">
            <i class="fa-solid" :class="t.icon"></i> {{ t.label }}
          </button>
        </div>

        <Transition name="ec-page" mode="out-in">
          <div v-if="guideTab === 'vehicules'" key="v" class="guide-panel" role="tabpanel">
            <div>
              <h3>Suivre mes véhicules</h3>
              <p class="guide-intro">Chaque véhicule rattaché à votre fiche apparaît avec son immatriculation et l’avancement de l’intervention en cours.</p>
              <ul class="points">
                <li><i class="fa-solid fa-car-side"></i><div><strong>Une fiche par véhicule</strong><span>Marque, modèle et immatriculation, tels qu’enregistrés par le garage.</span></div></li>
                <li><i class="fa-solid fa-list-check"></i><div><strong>Des étapes claires</strong><span>De la réception à la livraison, vous voyez où en est votre véhicule sans appeler.</span></div></li>
                <li><i class="fa-solid fa-clock-rotate-left"></i><div><strong>Un historique conservé</strong><span>Les interventions passées restent consultables dans votre dossier.</span></div></li>
              </ul>
            </div>
            <div class="sample">
              <div class="sample-label"><i class="fa-solid fa-eye"></i> Exemple d’affichage</div>
              <div class="sample-card">
                <div class="row-line"><span class="plate">2381 GK 01</span><span class="st st-info">En atelier</span></div>
                <ul class="steps">
                  <li class="ok"><i class="fa-solid fa-circle-check"></i> Réception du véhicule</li>
                  <li class="ok"><i class="fa-solid fa-circle-check"></i> Diagnostic</li>
                  <li class="now"><i class="fa-solid fa-gear fa-spin"></i> Réparation en cours</li>
                  <li><i class="fa-regular fa-circle"></i> Contrôle final</li>
                </ul>
                <div class="bar"><span class="fill" style="width:62%"></span></div>
              </div>
            </div>
          </div>

          <div v-else-if="guideTab === 'devis'" key="d" class="guide-panel" role="tabpanel">
            <div>
              <h3>Comprendre mes devis</h3>
              <p class="guide-intro">Un devis détaille les pièces et la main-d’œuvre, regroupées par famille de prestations, avec un total clair en FCFA.</p>
              <ul class="points">
                <li><i class="fa-solid fa-layer-group"></i><div><strong>Lignes par famille</strong><span>Freinage, vidange, électricité… chaque famille se lit séparément.</span></div></li>
                <li><i class="fa-solid fa-square-check"></i><div><strong>Lignes à inclure ou exclure</strong><span>Selon le devis, vous choisissez les lignes à retenir et le total se recalcule.</span></div></li>
                <li><i class="fa-solid fa-tag"></i><div><strong>Un statut lisible</strong><span>Vous savez d’un coup d’œil si un devis attend une réponse.</span></div></li>
              </ul>
              <div class="legend">
                <span class="st st-wait">En attente</span><span class="st st-ok">Accepté</span><span class="st st-no">Refusé</span><span class="st st-info">En cours</span>
              </div>
            </div>
            <div class="sample">
              <div class="sample-label"><i class="fa-solid fa-hand-pointer"></i> Essayez : cochez et décochez</div>
              <div class="sample-card">
                <label v-for="(x, i) in demoLines" :key="i" class="demo-line" :class="{ off: !x.on }">
                  <input v-model="x.on" type="checkbox">
                  <span>{{ x.l }}</span>
                  <b>{{ fcfa(x.p) }}</b>
                </label>
                <div class="demo-total"><span>Total retenu</span><strong>{{ fcfa(demoTotal) }}</strong></div>
              </div>
            </div>
          </div>

          <div v-else key="c" class="guide-panel" role="tabpanel">
            <div>
              <h3>Mon compte et sécurité</h3>
              <p class="guide-intro">Votre espace est protégé par un identifiant client et un mot de passe que vous êtes seul à connaître.</p>
              <ul class="points">
                <li><i class="fa-solid fa-id-card"></i><div><strong>Votre identifiant client</strong><span>Il ressemble à GK-KEY-XXXXXX-ID-123 et sert à vous connecter.</span></div></li>
                <li><i class="fa-solid fa-key"></i><div><strong>Mot de passe provisoire</strong><span>S’il vous a été remis par le garage, il ne sert qu’une fois : vous choisissez ensuite le vôtre.</span></div></li>
                <li><i class="fa-solid fa-pen-to-square"></i><div><strong>Coordonnées modifiables</strong><span>Téléphone et email peuvent être mis à jour à tout moment.</span></div></li>
              </ul>
            </div>
            <div class="sample">
              <div class="sample-label"><i class="fa-solid fa-shield-halved"></i> Un bon mot de passe</div>
              <ul class="rules">
                <li><i class="fa-solid fa-circle-check"></i> 10 caractères minimum</li>
                <li><i class="fa-solid fa-circle-check"></i> Majuscules et minuscules mélangées</li>
                <li><i class="fa-solid fa-circle-check"></i> Au moins un chiffre</li>
                <li><i class="fa-solid fa-circle-check"></i> Jamais partagé, même avec un proche</li>
              </ul>
            </div>
          </div>
        </Transition>
      </div>
    </section>

    <section id="sieges" class="sec">
      <div class="sec-head">
        <h2>Choisissez votre siège</h2>
        <p>Votre dossier est créé au siège que vous sélectionnez. Un clic suffit pour commencer.</p>
      </div>
      <div class="sieges">
        <div v-for="(nom, code) in props.sieges" :key="code" class="siege">
          <i class="fa-solid fa-location-dot"></i>
          <h3>{{ nom }}</h3>
          <button class="btn btn-light" @click="openAuth('register', code)">Me rattacher ici <i class="fa-solid fa-arrow-right go"></i></button>
        </div>
      </div>
    </section>

    <section id="faq" class="sec">
      <div class="sec-head"><h2>Questions fréquentes</h2></div>
      <div class="faq">
        <div v-for="(item, i) in faq" :key="i" class="faq-item" :class="{ open: faqOpen === i }">
          <button class="faq-q" :aria-expanded="faqOpen === i" @click="faqOpen = faqOpen === i ? -1 : i">
            {{ item.q }} <i class="fa-solid fa-plus"></i>
          </button>
          <div class="faq-a"><div><p>{{ item.a }}</p></div></div>
        </div>
      </div>
    </section>

    <section class="cta-band">
      <div class="cta-in">
        <div>
          <h2>Rejoignez votre espace client</h2>
          <p>Créez votre compte en quelques instants. Vous pourrez enregistrer un véhicule maintenant ou plus tard.</p>
        </div>
        <button class="btn btn-primary btn-lg" @click="openAuth('register')">Créer mon compte <i class="fa-solid fa-arrow-right go"></i></button>
      </div>
    </section>

    <footer class="foot">
      <span>© {{ year }} Garage Kagnan — Tous droits réservés</span>
      <button @click="goTo('guide')">Guide d’utilisation</button>
      <button @click="goTo('faq')">Questions fréquentes</button>
      <button @click="openAuth('login')">Se connecter</button>
    </footer>

    <div class="modal" :class="{ show: open }" role="dialog" aria-modal="true" aria-label="Authentification" @click.self="open = false">
      <div class="modal-box">
        <button class="close" aria-label="Fermer" @click="open = false"><i class="fa-solid fa-xmark"></i></button>
        <div v-if="!mustChangePassword" class="tabs">
          <button :class="{ on: authMode === 'login' }" @click="openAuth('login')">Connexion</button>
          <button :class="{ on: authMode === 'register' }" @click="openAuth('register')">Créer un compte</button>
        </div>
        <template v-if="mustChangePassword">
          <div class="auth-icon"><i class="fa-solid fa-key"></i></div>
          <h2 class="auth-title">Choisissez votre mot de passe</h2>
          <p class="auth-hint">Le mot de passe provisoire ne peut servir qu’une fois. Définissez un mot de passe personnel pour continuer.</p>
          <form @submit.prevent="changePassword">
            <label class="pwd">Nouveau mot de passe<input v-model="passwordForm.password" :type="showPwd ? 'text' : 'password'" autocomplete="new-password" minlength="10" required>
              <button type="button" class="eye" :aria-label="showPwd ? 'Masquer' : 'Afficher'" @click="showPwd = !showPwd"><i class="fa-solid" :class="showPwd ? 'fa-eye-slash' : 'fa-eye'"></i></button>
            </label>
            <label>Confirmer le mot de passe<input v-model="passwordForm.password_confirmation" :type="showPwd ? 'text' : 'password'" autocomplete="new-password" minlength="10" required></label>
            <p v-if="newPwdState" class="field-note" :class="newPwdState"><i class="fa-solid" :class="newPwdState === 'good' ? 'fa-circle-check' : 'fa-circle-exclamation'"></i>{{ newPwdState === 'good' ? 'Les mots de passe correspondent.' : 'Les mots de passe ne correspondent pas.' }}</p>
            <button class="btn btn-primary full" :disabled="busy">{{ busy ? 'Enregistrement…' : 'Enregistrer et continuer' }}</button>
            <p v-if="passwordError" class="err"><i class="fa-solid fa-circle-exclamation"></i>{{ passwordError }}</p>
          </form>
        </template>
        <template v-else-if="authMode === 'login'">
          <div class="auth-icon"><i class="fa-solid fa-right-to-bracket"></i></div>
          <h2 class="auth-title">Connexion à votre espace</h2>
          <form @submit.prevent="login">
            <label>Identifiant client<input v-model="credentials.identifier" autocomplete="username" required placeholder="GK-KEY-XXXXXX-ID-123"></label>
            <label class="pwd">Mot de passe<input v-model="credentials.password" :type="showPwd ? 'text' : 'password'" autocomplete="current-password" required>
              <button type="button" class="eye" :aria-label="showPwd ? 'Masquer' : 'Afficher'" @click="showPwd = !showPwd"><i class="fa-solid" :class="showPwd ? 'fa-eye-slash' : 'fa-eye'"></i></button>
            </label>
            <button class="btn btn-primary full" :disabled="busy">{{ busy ? 'Connexion…' : 'Se connecter' }}</button>
            <p v-if="loginError" class="err"><i class="fa-solid fa-circle-exclamation"></i>{{ loginError }}</p>
          </form>
          <p class="login-help"><i class="fa-solid fa-circle-info"></i>Première visite ? <button type="button" @click="openAuth('register')">Créez votre compte</button>. Mot de passe provisoire reçu du garage : utilisez-le ici, puis choisissez le vôtre.</p>
        </template>
        <template v-else>
          <div class="auth-icon"><i class="fa-solid fa-user-plus"></i></div>
          <h2 class="auth-title">Créer mon compte</h2>
          <p class="auth-hint">Un dossier client sera créé au siège choisi. L’ajout d’un véhicule est facultatif, mais son VIN et ses dates d’assurance et de SICTA sont obligatoires.</p>
          <form @submit.prevent="register">
            <div class="row2">
              <label>Prénom<input v-model="registration.prenom" autocomplete="given-name" required maxlength="255"></label>
              <label>Nom<input v-model="registration.nom" autocomplete="family-name" required maxlength="255"></label>
            </div>
            <label>Téléphone<input v-model="registration.telephone" type="tel" inputmode="tel" autocomplete="tel" required maxlength="191"></label>
            <label>Siège de rattachement
              <select v-model="registration.siege" required>
                <option value="" disabled>Choisir un siège</option>
                <option v-for="(nom, code) in sieges" :key="code" :value="code">{{ nom }}</option>
              </select>
            </label>
            <label>Email (facultatif)<input v-model="registration.email" type="email" autocomplete="email" maxlength="191"></label>
            <label class="pwd">Mot de passe<input v-model="registration.password" :type="showPwd ? 'text' : 'password'" autocomplete="new-password" minlength="10" required>
              <button type="button" class="eye" :aria-label="showPwd ? 'Masquer' : 'Afficher'" @click="showPwd = !showPwd"><i class="fa-solid" :class="showPwd ? 'fa-eye-slash' : 'fa-eye'"></i></button>
            </label>
            <template v-if="registration.password">
              <div class="meter" :class="'s' + pwdScore"><i></i><i></i><i></i><i></i></div>
              <p class="field-note"><i class="fa-solid fa-shield-halved"></i>Force : {{ pwdLabels[pwdScore] }} (10 caractères minimum)</p>
            </template>
            <label>Confirmer le mot de passe<input v-model="registration.password_confirmation" :type="showPwd ? 'text' : 'password'" autocomplete="new-password" minlength="10" required></label>
            <p v-if="pwdState" class="field-note" :class="pwdState"><i class="fa-solid" :class="pwdState === 'good' ? 'fa-circle-check' : 'fa-circle-exclamation'"></i>{{ pwdState === 'good' ? 'Les mots de passe correspondent.' : 'Les mots de passe ne correspondent pas.' }}</p>
            <label class="check"><input v-model="registration.add_vehicle" type="checkbox"> Ajouter un véhicule maintenant (facultatif)</label>
            <div v-if="registration.add_vehicle" class="reveal">
              <div class="row2">
                <label>Marque<input v-model="registration.vehicle_marque" maxlength="100"></label>
                <label>Modèle<input v-model="registration.vehicle_modele" maxlength="100" required></label>
              </div>
              <label>Immatriculation<input v-model="registration.vehicle_immatriculation" maxlength="50" required placeholder="2381 GK 01"></label>
              <label>VIN / numéro de châssis<input v-model="registration.vehicle_vin" maxlength="100" required autocomplete="off"></label>
              <div class="row2">
                <label>Date d’expiration de l’assurance<input v-model="registration.vehicle_expiration_assurance" type="date" required></label>
                <label>Date d’expiration SICTA<input v-model="registration.vehicle_expiration_sicta" type="date" required></label>
              </div>
            </div>
            <button class="btn btn-primary full" :disabled="busy">{{ busy ? 'Création du compte…' : 'Créer mon compte' }}</button>
            <p v-if="registrationError" class="err"><i class="fa-solid fa-circle-exclamation"></i>{{ registrationError }}</p>
          </form>
        </template>
      </div>
    </div>
  </div>
</template>