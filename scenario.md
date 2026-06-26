# Scenario Video Tutoriel (Fonctionnalites Actuelles)

Ce document est un script "pret a filmer" pour presenter toutes les fonctionnalites actuellement disponibles dans le projet Villa Boutanga PMS, en tenant compte des differents roles (RBAC).

## 1) Preparation (avant enregistrement)

- Base de donnees: execute les migrations + seeders.
- URL appli (local): ouvre l'app (page login).
- Tenant slug utilise par les seeds: `villa-boutanga`.
- Portail client (sans login): `GET /portal/villa-boutanga/restaurant`.

### Comptes de test (Seeder)

Mot de passe commun: `password`

- Admin: `admin@villaboutanga.cm`
- Manager hotel: `manager@villaboutanga.cm`
- Reception: `reception@villaboutanga.cm`
- Housekeeping leader: `housekeeping.leader@villaboutanga.cm`
- Housekeeping staff:
  - `housekeeping.staff1@villaboutanga.cm`
  - `housekeeping.staff2@villaboutanga.cm`
  - `housekeeping.staff3@villaboutanga.cm`
- Restaurant chief: `restaurant.chief@villaboutanga.cm`
- Restaurant staff (serveur): `restaurant.staff@villaboutanga.cm`

Note: d'autres roles existent (ex: `cashier`, `accountant`, `maintenance`) mais peuvent ne pas avoir de compte seed par defaut selon l'etat du projet.

## 2) Intro video (30-45s)

Objectif a dire:
- "On va parcourir un PMS multi-service (Hotel + Restaurant) avec un systeme de roles (RBAC)."
- "Chaque utilisateur voit un dashboard et une sidebar adaptes a son service, et les actions sensibles sont proteges."

Plan annonce:
1. Connexion + profil.
2. Hotel: chambres, clients, reservations (individuel + groupe), factures.
3. Housekeeping: priorites, assignations, statuts, incidents.
4. Discussions internes: conversations, non-lus, suppression/archivage.
5. Restaurant: menus, portail client (QR), commandes staff, facturation, garde-manger.
6. RBAC: demonstration d'un acces refuse propre (popup).

## 3) Commun a tous les roles

### 3.1 Connexion / Deconnexion

1. Aller sur la page de login.
2. Se connecter avec un compte seed.
3. Montrer le bouton profil dans la sidebar (bas) + deconnexion.

### 3.2 Page Profil (parametres)

1. Cliquer sur le profil dans la sidebar.
2. Montrer:
   - Mise a jour des infos du profil (selon ce qui est disponible).
   - Changement de mot de passe.
3. Retour au dashboard.

## 4) Parcours Manager (Hotel + supervision)

Connecte-toi en: `manager@villaboutanga.cm`

### 4.1 Dashboard personnalise

1. Montrer les cartes (arrivees, departs, occupation, housekeeping).
2. Insister: le dashboard est contextualise selon le role/service.

### 4.2 Module Chambres

Objectif: montrer gestion complete (CRUD + vues).

1. Ouvrir `Chambres`.
2. Montrer:
   - Recherche + filtres de statut.
   - Toggle vue `liste` / `carte`.
3. Creer une chambre:
   - Cliquer `Nouvelle chambre`.
   - Remplir type + numero + etage + vue.
   - Enregistrer.
   - Montrer le message de succes et la chambre visible dans la liste (sans rafraichir manuel).
4. Modifier une chambre:
   - Ouvrir le modal d'edition.
   - Changer un champ, enregistrer, verifier la mise a jour.
5. Supprimer une chambre:
   - Cliquer supprimer.
   - Confirmer.
   - Verifier message + disparition dans la liste.
6. Onglet Types de chambre:
   - Creer une categorie (type).
   - Editer le type.
   - Supprimer un type (si aucune chambre liee).
7. Statut chambre:
   - Ouvrir le detail d'une chambre.
   - Changer le statut (ex: available -> maintenance) + raison si disponible.
   - Montrer l'historique des statuts (si affiche).

### 4.3 Module Clients

1. Ouvrir `Clients`.
2. Montrer:
   - Liste + filtres (ex: fidelite/VIP si presentes).
   - Fiche client detaillee.
   - Historique des sejours + progression fidelite (si affiche).

### 4.4 Gestion Staff (Utilisateurs)

1. Ouvrir `Utilisateurs` (manager only).
2. Creer un utilisateur staff:
   - Remplir nom/email/role.
   - Enregistrer + message.
3. Modifier un utilisateur.
4. Desactiver un compte:
   - Cliquer "desactiver".
   - Verifier que l'utilisateur desactive ne peut plus se connecter et voit un message clair ("compte desactive").

### 4.5 Reservations (individuelles)

Objectif: demo du wizard et du cycle check-in/out + folio + facture.

1. Ouvrir `Reservations`.
2. Cliquer `Nouvelle reservation` (wizard).
3. Etape client:
   - Rechercher un client existant OU creer un nouveau client.
4. Etape dates:
   - Choisir check-in/check-out, nb personnes.
5. Etape chambre:
   - Choisir une chambre disponible.
6. Confirmer la reservation.
7. Ouvrir le detail:
   - Montrer le folio (hebergement + prestations).
   - Ajouter une prestation folio (ex: room service) si disponible.
   - Enregistrer un paiement si disponible.
8. Check-in:
   - Cliquer check-in.
   - Montrer changement de statut reservation + chambre.
9. Check-out:
   - Cliquer check-out.
   - Montrer calculs/etat final.
10. Facture:
   - Ouvrir la vue facture/impression (PDF ou page imprimable).

### 4.6 Reservations Groupes

1. Ouvrir `Groupes`.
2. Creer un dossier groupe (contact principal).
3. Ajouter des chambres (reservations individuelles rattachees).
4. Prestations de groupe (selon modes disponibles).
5. Paiement global.
6. Check-in / Check-out groupe.
7. Facture groupe (detail par chambre).
8. Annuler un dossier (si option presente) et montrer confirmation.

## 5) Parcours Reception (front desk)

Connecte-toi en: `reception@villaboutanga.cm`

Objectif: montrer qu'il a acces aux reservations/clients, mais pas aux actions manager (ex: CRUD types, utilisateurs).

1. Dashboard reception (cartes et acces rapides).
2. Clients: consulter / mettre a jour une fiche client (si autorise).
3. Reservations:
   - Creer une reservation (wizard).
   - Effectuer check-in/check-out.
4. RBAC (test):
   - Essayer d'acceder a `Utilisateurs` (ou autre page manager).
   - Montrer le popup "Acces refuse" (pas une page d'erreur brute).

## 6) Parcours Housekeeping

### 6.1 Housekeeping Leader

Connecte-toi en: `housekeeping.leader@villaboutanga.cm`

1. Ouvrir `Housekeeping`.
2. Montrer:
   - Liste priorisee (si presente).
   - Creation d'equipe housekeeping (team).
   - Assignation de chambres au staff.
3. Marquer une chambre "cleaning" puis "ready".
4. Declarer un incident/probleme sur une chambre (issue/report) si disponible.

### 6.2 Housekeeping Staff

Connecte-toi en: `housekeeping.staff1@villaboutanga.cm`

1. Ouvrir `Housekeeping`.
2. Voir uniquement ses assignations (si filtre par user/team).
3. Marquer une chambre comme nettoyee / prete.
4. RBAC (test):
   - Essayer de supprimer une chambre (action manager) -> popup acces refuse.

## 7) Discussions internes (tous roles)

Connecte-toi avec n'importe quel role (ex: manager puis reception).

Objectif: interface type WhatsApp/Telegram desktop + non-lus + actions conversation.

1. Ouvrir `Discussions`.
2. Layout:
   - Sidebar gauche: liste des conversations + compteur non lus.
   - Zone messages: affichage conversation.
   - Champ saisie message fixe en bas + scroll messages.
3. Demarrer une nouvelle conversation (bouton flottant):
   - Choisir un utilisateur.
   - Envoyer un message.
4. Instantaneite:
   - Se connecter sur un autre compte (dans un autre navigateur/profil) et repondre.
   - Montrer reception sans refresh + scroll auto sur dernier message.
5. Non lus:
   - Montrer le badge (cercle) sur la conversation.
   - Montrer le point (beige) dans la sidebar sur "Discussion" quand un nouveau message arrive hors page.
6. Actions conversation (menu "3 points"):
   - Archiver.
   - Supprimer avec popup proposant:
     - "Supprimer pour moi"
     - "Supprimer pour tous"
   - Verifier que "supprimer pour moi" n'empeche pas de recevoir de nouveaux messages.

## 8) Restaurant

### 8.1 Gestion Menus (manager + restaurant chief)

Connecte-toi en: `restaurant.chief@villaboutanga.cm` (ou manager)

1. Ouvrir `Restaurant > Menus`.
2. Categories:
   - Creer une categorie (avec champ ordre si present).
   - Modifier l'ordre et montrer l'effet sur l'affichage.
3. Items (plats):
   - Creer un article (prix, disponibilite).
   - Modifier / desactiver un article.

### 8.2 Portail Client (QR)

Sans login:

1. Ouvrir `GET /portal/villa-boutanga/restaurant`.
2. Parcourir le menu.
3. Passer une commande:
   - Choisir des items + quantites.
   - Renseigner la table (obligatoire).
   - Soumettre.
4. Afficher la page de suivi commande (si presente) via l'URL de commande retournee.

### 8.3 Commandes Restaurant (staff)

Connecte-toi en: `restaurant.staff@villaboutanga.cm`

1. Ouvrir `Restaurant > Commandes`.
2. Creer une commande manuellement (cas client qui ne scanne pas):
   - Table obligatoire.
   - Ajouter des items.
   - Enregistrer.
3. Modifier une commande (si autorise).
4. Verifier RBAC:
   - Un serveur ne supprime pas une commande (doit etre bloque / popup).

### 8.4 Facturation interne Restaurant (manager + restaurant chief + cashier)

Connecte-toi en: manager ou cashier (si compte existant).

1. Ouvrir `Restaurant > Facturation`.
2. Ouvrir une commande.
3. Marquer payee / impayee.
4. Generer / afficher le recu imprimable.

### 8.5 Garde-manger (inventaire restaurant)

Connecte-toi en: `restaurant.chief@villaboutanga.cm` (ou manager)

1. Ouvrir `Restaurant > Garde-manger`.
2. Creer categories/items (si active).
3. Enregistrer un mouvement de stock (entree/sortie) sur un item.

## 9) RBAC (sequence "preuve")

Objectif: montrer une utilisation fluide et securisee.

1. Se connecter en `housekeeping.staff1`.
2. Aller sur une action manager (ex: tentative de suppression chambre / acces page Users).
3. Montrer popup "Acces refuse" avec message clair.
4. Fermer et continuer sans casser la navigation.

## 10) Outro (20-30s)

1. Recap rapide des modules.
2. Mentionner les prochaines etapes prevues (si tu veux teaser):
   - comptabilite/finance
   - notifications email
   - enrichissement inventaire hotel

