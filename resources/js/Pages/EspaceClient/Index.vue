<script setup>
import { ref, onMounted } from 'vue'
import '../../espace-client/espace-client.css'
import { loadSession, signup, login, ROUTES } from '../../espace-client/store.js'

const open = ref(false)
const tab = ref('login')
const year = new Date().getFullYear()
const lf = ref({ id: '', password: '' })
const sf = ref({ nom: '', tel: '', email: '', vehicule: '', immat: '', password: '', cgu: false })
const lerr = ref('')
const serr = ref('')

function openAuth(t) { tab.value = t; open.value = true }

function doLogin() {
  const e = login(lf.value.id, lf.value.password)
  if (e) { lerr.value = e; return }
  window.location.href = ROUTES.dashboard
}
function doSignup() {
  const e = signup(sf.value)
  if (e) { serr.value = e; return }
  window.location.href = ROUTES.dashboard
}

onMounted(() => {
  document.title = 'Espace Client — Garage Kagnan'
  if (loadSession()) { window.location.href = ROUTES.dashboard; return }
  if (location.hash === '#inscription') openAuth('signup')
  if (location.hash === '#connexion') openAuth('login')
})
</script>

<template>
  <div class="ec landing">
    <header class="topbar">
      <a href="/" class="brand"><span class="logo">GK</span> Garage Kagnan</a>
      <nav>
        <a href="/">Retour au site</a>
        <button class="btn btn-ghost" @click="openAuth('login')">Se connecter</button>
        <button class="btn btn-primary" @click="openAuth('signup')">Créer mon compte</button>
      </nav>
    </header>

    <section class="hero">
      <div class="hero-text">
        <span class="tag">Espace Client Garage Kagnan</span>
        <h1>Votre véhicule entre de bonnes mains, <span class="accent">sans vous déplacer.</span></h1>
        <p>Dépannage, enlèvement à domicile, rendez-vous atelier, devis en ligne et suivi en temps réel. Tout ce dont votre véhicule a besoin, réuni dans un seul espace.</p>
        <div class="hero-cta">
          <button class="btn btn-primary btn-lg" @click="openAuth('signup')">Créer mon compte gratuitement</button>
          <button class="btn btn-outline btn-lg" @click="openAuth('login')">J'ai déjà un compte</button>
        </div>
        <div class="stats">
          <div><strong>24h/24</strong><span>Dépannage</span></div>
          <div><strong>0 FCFA</strong><span>Inscription</span></div>
          <div><strong>Temps réel</strong><span>Suivi atelier</span></div>
        </div>
      </div>
      <div class="hero-card">
        <div class="mock-head">Suivi véhicule — Toyota Hilux</div>
        <div class="mock-step done">Réception sur parc</div>
        <div class="mock-step done">Diagnostic atelier</div>
        <div class="mock-step active">Réparation en cours</div>
        <div class="mock-step">Contrôle qualité</div>
        <div class="mock-step">Prêt à être restitué</div>
        <div class="mock-notif">Notification : votre véhicule est passé en réparation.</div>
      </div>
    </section>

    <section class="features">
      <h2>Les avantages réservés aux membres</h2>
      <p class="sub">Un compte, et votre garage vous accompagne partout.</p>
      <div class="grid">
        <div class="feat"><div class="ico">01</div><h3>Dépanneuse à la demande</h3><p>Commandez une dépanneuse en quelques secondes. Prix calculé selon la distance, affiché avant validation.</p></div>
        <div class="feat"><div class="ico">02</div><h3>Convoyage domicile-garage</h3><p>Un chauffeur récupère votre véhicule et vous le ramène une fois réparé. Vous ne vous déplacez pas.</p></div>
        <div class="feat"><div class="ico">03</div><h3>Rendez-vous atelier</h3><p>Choisissez la date, l'heure et la prestation. Confirmation immédiate.</p></div>
        <div class="feat"><div class="ico">04</div><h3>Devis en ligne</h3><p>Recevez vos devis détaillés et acceptez-les en un clic, sans paperasse.</p></div>
        <div class="feat"><div class="ico">05</div><h3>Suivi en temps réel</h3><p>Une notification à chaque étape : réception, diagnostic, réparation, restitution.</p></div>
        <div class="feat"><div class="ico">06</div><h3>Carnet d'entretien digital</h3><p>Kilométrage, assurance, historique des réparations : nous vous alertons avant les échéances.</p></div>
      </div>
    </section>

    <section class="cta-band">
      <h2>Rejoignez l'Espace Client Garage Kagnan</h2>
      <p>Inscription gratuite en moins d'une minute.</p>
      <button class="btn btn-primary btn-lg" @click="openAuth('signup')">Je crée mon compte</button>
    </section>

    <footer class="foot">© {{ year }} Garage Kagnan — Tous droits réservés</footer>

    <div class="modal" :class="{ show: open }" @click.self="open = false">
      <div class="modal-box">
        <button class="close" @click="open = false">×</button>
        <div class="tabs">
          <button :class="{ on: tab === 'login' }" @click="tab = 'login'">Connexion</button>
          <button :class="{ on: tab === 'signup' }" @click="tab = 'signup'">Inscription</button>
        </div>

        <form v-show="tab === 'login'" @submit.prevent="doLogin">
          <label>Email ou téléphone<input v-model="lf.id" required></label>
          <label>Mot de passe<input v-model="lf.password" type="password" required></label>
          <button class="btn btn-primary full">Se connecter</button>
          <p class="err">{{ lerr }}</p>
        </form>

        <form v-show="tab === 'signup'" @submit.prevent="doSignup">
          <div class="row2">
            <label>Nom complet<input v-model="sf.nom" required></label>
            <label>Téléphone<input v-model="sf.tel" required placeholder="07 00 00 00 00"></label>
          </div>
          <label>Email<input v-model="sf.email" type="email" required></label>
          <div class="row2">
            <label>Marque / modèle<input v-model="sf.vehicule" required placeholder="Toyota Hilux"></label>
            <label>Immatriculation<input v-model="sf.immat" required placeholder="4589-HX-01"></label>
          </div>
          <label>Mot de passe<input v-model="sf.password" type="password" minlength="6" required></label>
          <label class="check"><input v-model="sf.cgu" type="checkbox" required> J'accepte les conditions d'utilisation</label>
          <button class="btn btn-primary full">Créer mon compte</button>
          <p class="err">{{ serr }}</p>
        </form>
      </div>
    </div>
  </div>
</template>
