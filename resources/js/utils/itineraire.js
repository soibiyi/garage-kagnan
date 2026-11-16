// Outils d'itinéraire partagés : étapes « tourne-à-tourne », formats et liens de navigation.
// Une étape normalisée : { instruction, distanceMeters, durationMillis, manoeuvre }
// manoeuvre ∈ depart, arrive, straight, left, right, slight-left, slight-right,
//              sharp-left, sharp-right, uturn, roundabout, merge, ramp-left, ramp-right,
//              fork-left, fork-right, ferry

/* ------------------------------------------------------------------ */
/* Google Routes (google.maps.routes.Route)                             */
/* ------------------------------------------------------------------ */
const MANOEUVRES_GOOGLE = {
  DEPART: 'depart',
  STRAIGHT: 'straight',
  NAME_CHANGE: 'straight',
  TURN_LEFT: 'left',
  TURN_RIGHT: 'right',
  TURN_SLIGHT_LEFT: 'slight-left',
  TURN_SLIGHT_RIGHT: 'slight-right',
  TURN_SHARP_LEFT: 'sharp-left',
  TURN_SHARP_RIGHT: 'sharp-right',
  UTURN_LEFT: 'uturn',
  UTURN_RIGHT: 'uturn',
  ROUNDABOUT_LEFT: 'roundabout',
  ROUNDABOUT_RIGHT: 'roundabout',
  MERGE: 'merge',
  RAMP_LEFT: 'ramp-left',
  RAMP_RIGHT: 'ramp-right',
  FORK_LEFT: 'fork-left',
  FORK_RIGHT: 'fork-right',
  FERRY: 'ferry',
  FERRY_TRAIN: 'ferry',
}

export function etapesGoogle(route) {
  const steps = (route?.legs || []).flatMap(leg => leg?.steps || [])
  const etapes = steps
    .map(step => {
      const maneuver = String(step.maneuver || step.navigationInstruction?.maneuver || '').toUpperCase()
      return {
        instruction: step.instructions || step.navigationInstruction?.instructions || '',
        distanceMeters: step.distanceMeters ?? null,
        durationMillis: step.staticDurationMillis ?? null,
        manoeuvre: MANOEUVRES_GOOGLE[maneuver] || 'straight',
      }
    })
    .filter(etape => etape.instruction)

  if (etapes.length) etapes.push({ instruction: 'Vous êtes arrivé à destination', distanceMeters: null, durationMillis: null, manoeuvre: 'arrive' })
  return etapes
}

/* ------------------------------------------------------------------ */
/* OSRM (itinéraire de secours) : instructions générées en français     */
/* ------------------------------------------------------------------ */
const DIRECTIONS_OSRM = {
  uturn: { manoeuvre: 'uturn', texte: 'Faites demi-tour' },
  'sharp right': { manoeuvre: 'sharp-right', texte: 'Tournez franchement à droite' },
  right: { manoeuvre: 'right', texte: 'Tournez à droite' },
  'slight right': { manoeuvre: 'slight-right', texte: 'Serrez à droite' },
  straight: { manoeuvre: 'straight', texte: 'Continuez tout droit' },
  'slight left': { manoeuvre: 'slight-left', texte: 'Serrez à gauche' },
  left: { manoeuvre: 'left', texte: 'Tournez à gauche' },
  'sharp left': { manoeuvre: 'sharp-left', texte: 'Tournez franchement à gauche' },
}

const ordinal = n => (n === 1 ? '1re' : `${n}e`)

function instructionOsrm(step) {
  const { type, modifier, exit } = step.maneuver || {}
  const rue = step.name ? ` sur ${step.name}` : ''
  const vers = step.name ? ` vers ${step.name}` : ''
  const direction = DIRECTIONS_OSRM[modifier] || DIRECTIONS_OSRM.straight
  const cote = modifier?.includes('left') ? 'left' : 'right'

  switch (type) {
    case 'depart':
      return { manoeuvre: 'depart', instruction: `Partez${rue}` }
    case 'arrive':
      return { manoeuvre: 'arrive', instruction: 'Vous êtes arrivé à destination' }
    case 'roundabout':
    case 'rotary':
      return {
        manoeuvre: 'roundabout',
        instruction: exit ? `Au rond-point, prenez la ${ordinal(exit)} sortie${vers}` : `Prenez le rond-point${vers}`,
      }
    case 'exit roundabout':
    case 'exit rotary':
      return { manoeuvre: 'roundabout', instruction: `Sortez du rond-point${vers}` }
    case 'merge':
      return { manoeuvre: 'merge', instruction: `Insérez-vous${rue}` }
    case 'on ramp':
      return { manoeuvre: `ramp-${cote}`, instruction: `Prenez la bretelle${vers}` }
    case 'off ramp':
      return { manoeuvre: `ramp-${cote}`, instruction: `Prenez la sortie${vers}` }
    case 'fork':
      return { manoeuvre: `fork-${cote}`, instruction: `À l'embranchement, restez à ${cote === 'left' ? 'gauche' : 'droite'}${vers}` }
    case 'end of road':
      return { manoeuvre: direction.manoeuvre, instruction: `Au bout de la route, ${direction.texte.charAt(0).toLowerCase()}${direction.texte.slice(1)}${rue}` }
    case 'continue':
    case 'new name':
      return { manoeuvre: direction.manoeuvre, instruction: modifier && modifier !== 'straight' ? `${direction.texte}${rue}` : `Continuez${rue}` }
    default:
      return { manoeuvre: direction.manoeuvre, instruction: `${direction.texte}${rue}` }
  }
}

export function etapesOsrm(route) {
  const steps = (route?.legs || []).flatMap(leg => leg?.steps || [])
  return steps
    // Les « notifications » OSRM et les étapes nulles n'apportent rien au conducteur
    .filter(step => step.maneuver?.type !== 'notification' && (step.distance > 0 || ['depart', 'arrive'].includes(step.maneuver?.type)))
    .map(step => ({
      ...instructionOsrm(step),
      distanceMeters: step.maneuver?.type === 'arrive' ? null : step.distance,
      durationMillis: step.maneuver?.type === 'arrive' ? null : step.duration * 1000,
    }))
}

/* ------------------------------------------------------------------ */
/* Affichage                                                            */
/* ------------------------------------------------------------------ */
const ROTATIONS = {
  straight: 0, 'slight-right': 45, right: 90, 'sharp-right': 135,
  'slight-left': -45, left: -90, 'sharp-left': -135,
  'ramp-right': 45, 'ramp-left': -45, 'fork-right': 30, 'fork-left': -30, merge: 0,
}

/** Classe Font Awesome + rotation (degrés) pour illustrer une manœuvre. */
export function iconeManoeuvre(manoeuvre) {
  switch (manoeuvre) {
    case 'depart': return { classe: 'fa-solid fa-location-arrow', rotation: 0 }
    case 'arrive': return { classe: 'fa-solid fa-flag-checkered', rotation: 0 }
    case 'uturn': return { classe: 'fa-solid fa-arrow-rotate-left', rotation: 0 }
    case 'roundabout': return { classe: 'fa-solid fa-rotate-right', rotation: 0 }
    case 'ferry': return { classe: 'fa-solid fa-ferry', rotation: 0 }
    default: return { classe: 'fa-solid fa-arrow-up', rotation: ROTATIONS[manoeuvre] ?? 0 }
  }
}

export function formatDistance(metres) {
  if (!Number.isFinite(metres)) return ''
  if (metres < 1000) return `${Math.max(10, Math.round(metres / 10) * 10)} m`
  return `${(metres / 1000).toFixed(metres < 10000 ? 1 : 0).replace('.', ',')} km`
}

export function formatDuree(millis) {
  if (!Number.isFinite(millis)) return ''
  const minutes = Math.max(1, Math.round(millis / 60000))
  if (minutes < 60) return `${minutes} min`
  const heures = Math.floor(minutes / 60)
  const reste = minutes % 60
  return reste ? `${heures} h ${String(reste).padStart(2, '0')}` : `${heures} h`
}

export function heureArrivee(millis, depuis = Date.now()) {
  if (!Number.isFinite(millis)) return ''
  return new Date(depuis + millis).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

/* ------------------------------------------------------------------ */
/* Ouvrir la navigation dans une application GPS                        */
/* ------------------------------------------------------------------ */
const coord = point => `${Number(point.lat)},${Number(point.lng)}`

export function lienGoogleMaps(destination, origine = null) {
  if (!destination) return null
  const params = new URLSearchParams({ api: '1', destination: coord(destination), travelmode: 'driving', dir_action: 'navigate' })
  if (origine) params.set('origin', coord(origine))
  return `https://www.google.com/maps/dir/?${params}`
}

export function lienWaze(destination) {
  if (!destination) return null
  return `https://waze.com/ul?ll=${encodeURIComponent(coord(destination))}&navigate=yes`
}
