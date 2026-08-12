# Rapport de tests - Zaki Logistics

Date: 2026-06-07

## Etat general
Le projet contient maintenant:
- `frontend/`: interface React/Vite.
- `backend-new/`: API Laravel avec Sanctum, SQLite local, seeders et routes REST.
- `LANCER_SITE.bat`: lance backend + frontend pour la soutenance.

## Comptes de test valides
- Admin: `admin@logistics.ma` / `admin123`
- Expediteur: `expediteur@test.ma` / `test123`
- Livreur: `livreur@test.ma` / `test123`
- Destinataire: `destinataire@test.ma` / `test123`
- Voyageur: `voyageur@test.ma` / `test123`

## Tests effectues

### Backend
Commandes executees:
```bat
cd backend-new
composer validate --no-check-publish
composer check-platform-reqs
php artisan migrate --force
php artisan db:seed --force
php artisan route:list
```

Resultats:
- `composer.json` valide.
- Exigences PHP OK.
- Migrations OK.
- Seeding OK.
- 33 routes API/web disponibles.

### API reelle
Serveur de test lance sur `http://127.0.0.1:8010`.

Resultats:
- `/up`: HTTP 200.
- Login OK pour les 5 comptes.
- `/api/admin/stats`: OK, `totalColis=4`, `totalUsers=5`.
- `/api/colis/track/LOG2024ABC`: OK.
- `/api/auth/me` sans token: HTTP 401, comportement correct.

### Frontend
Commandes executees:
```bat
cd frontend
npm run build
npm audit --omit=dev
```

Resultats:
- Build Vite OK.
- 0 vulnerabilite npm production.
- Avertissement non bloquant: bundle JS > 500 kB. Le site reste fonctionnel.

## Lancement soutenance
Double-cliquer sur:
```bat
LANCER_SITE.bat
```
Puis ouvrir:
```txt
http://127.0.0.1:5173
```

## Livraison USB
Pour une demo sans internet:
1. Copier tout le dossier `zaki` sur la cle USB.
2. Garder `backend-new/vendor` et `frontend/node_modules` pour eviter de telecharger les dependances.
3. Sur l'autre PC, installer/verifier:
   - PHP 8.3 ou plus recent
   - Node.js 18 ou plus recent
   - Composer
4. Double-cliquer sur `LANCER_SITE.bat`.
5. Ouvrir `http://127.0.0.1:5173`.

## Notes importantes
- Le backend local utilise SQLite pour fiabiliser la soutenance sans dependance MySQL.
- Le frontend est configure avec `VITE_API_URL=http://127.0.0.1:8000/api`.
- Le paiement est simule via les donnees et workflows existants; aucune dependance payante n'est necessaire.