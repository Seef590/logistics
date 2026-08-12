# Zaki Logistics - Soutenance

## Lancement rapide
Double-cliquer sur `LANCER_SITE.bat`, puis ouvrir : http://127.0.0.1:5173

## Comptes de test
- Admin : admin@logistics.ma / admin123
- Expediteur : expediteur@test.ma / test123
- Livreur : livreur@test.ma / test123
- Destinataire : destinataire@test.ma / test123
- Voyageur : voyageur@test.ma / test123

## Commandes utiles
Backend :
```bat
cd backend-new
php artisan migrate --force
php artisan db:seed --force
php artisan serve --host=127.0.0.1 --port=8000
```

Frontend :
```bat
cd frontend
npm run dev -- --host 127.0.0.1 --port 5173
npm run build
```

## Copie USB
Pour une demo sans internet, copier le dossier complet `zaki` avec `backend-new/vendor` et `frontend/node_modules`.
Sur l'autre PC, PHP, Composer et Node.js doivent etre installes. Ensuite lancer `LANCER_SITE.bat`.