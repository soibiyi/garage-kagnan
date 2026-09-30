// Familles et sous-familles de pièces / prestations (ordre = ordre d'affichage sur la facture)
export const FAMILLES = {
    'Motorisation': ['Moteur', 'Distribution', 'Lubrification', 'Refroidissement', 'Alimentation / Injection'],
    'Transmission': ['Embrayage', 'Boîte de vitesses', 'Cardans'],
    'Freinage': ['Freins avant', 'Freins arrière', 'Hydraulique', 'Frein de stationnement'],
    'Suspension & Direction': ['Amortissement', 'Suspension', 'Direction'],
    'Électricité & Électronique': ['Démarrage', 'Charge', 'Éclairage', 'Capteurs', 'Électronique'],
    'Tôlerie & Carrosserie': ['Carrosserie', 'Portières', 'Capot & Coffre', 'Pare-chocs', 'Rétroviseurs'],
    'Climatisation': ['Climatisation', 'Chauffage'],
    'Échappement': ["Ligne d'échappement", 'Catalyseur', 'Filtration des gaz'],
    'Roues & Pneumatiques': ['Pneus', 'Jantes', 'Roulements'],
};

// Libellé du groupe pour les lignes sans famille (diagnostic, main d'œuvre...)
export const FAMILLE_PAR_DEFAUT = "Main d'œuvre & prestations";