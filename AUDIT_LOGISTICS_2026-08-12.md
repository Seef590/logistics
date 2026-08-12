# LOGISTICS — RAPPORT D’AUDIT ET DE REMÉDIATION

Date : 12 août 2026  
Périmètre : `backend-new` (Laravel 13), `frontend` (React 18/Vite 6), SQLite et lanceur Windows local.

## 1. Résumé exécutif

```text
Issues reviewed: 30
Confirmed: 28
Fixed: 22
Partially fixed: 2
Not reproduced / not applicable: 2
Manual review: 4
Regressions introduced: 0 known
```

Le projet est fonctionnel après correction. La suite Laravel passe avec 28 tests et 81 assertions, le build frontend passe et `npm audit` ne signale plus aucune vulnérabilité. Aucune donnée métier n’a été supprimée. Une migration additive d’unicité du téléphone a été appliquée après contrôle de l’absence de doublons.

Le risque urgent restant concerne trois paquets Composer vulnérables. La résolution ciblée a été préparée, mais Packagist n’a pas pu être contacté car l’autorisation réseau a été refusée. Le lockfile Composer n’a donc pas été falsifié ou modifié manuellement.

## 2. Matrice de vérification

| ID | Constat vérifié | Confirmation | Sévérité | Risque de correction | Fichiers principaux | Correction / état | Tests |
|---|---|---:|---|---|---|---|---|
| LOG-001 | `postcss` et `nanoid` vulnérables | Oui | Haute | Faible | `frontend/package*.json` | Overrides minimaux sécurisés | `npm audit`, build |
| LOG-002 | Guzzle, PSR-7 et CommonMark vulnérables | Oui | Haute | Moyen | `composer.lock` | **MANUAL ACTION REQUIRED** : réseau refusé | `composer audit` échoue encore |
| LOG-003 | PIN stocké/exposé en clair | Oui | Critique | Moyen | modèle/contrôleur colis | Hash bcrypt, retour en clair uniquement à la création | Tests PIN |
| LOG-004 | Changement de statut par utilisateur non assigné | Oui | Critique | Faible | `ColisController` | Autorisation stricte | Tests d’accès |
| LOG-005 | Deux transporteurs pouvaient réclamer le même colis | Oui | Critique | Moyen | `ColisController` | Transaction, verrou et assignation contrôlée | Test de concurrence logique |
| LOG-006 | Transitions de statut arbitraires | Oui | Haute | Moyen | `ColisController` | Graphe de transitions, override admin conservé | Tests saut/retour |
| LOG-007 | Transporteur non vérifié capable de réclamer | Oui | Haute | Faible | `ColisController` | Vérification/approbation obligatoire | Tests disponibles/claim |
| LOG-008 | Retry PIN/statut créait des doublons possibles | Oui | Haute | Moyen | `ColisController` | Opérations DB atomiques et idempotentes | Tests retry/historique/notification |
| LOG-009 | Suivi public exposait nom, téléphone et adresses | Oui | Haute | Moyen | backend + `Home.jsx` | Réponse publique en allowlist | Tests de confidentialité + smoke API |
| LOG-010 | Liste des colis disponibles exposait la PII avant acceptation | Oui | Haute | Moyen | backend + dashboards transporteurs | Projection minimale avant assignation | Test de confidentialité |
| LOG-011 | Annuaire public des livreurs exposait trop de champs | Oui | Haute | Faible | `MiscController` | Allowlist publique | Test dédié |
| LOG-012 | Note d’un transporteur sans relation au colis | Oui | Haute | Faible | `ColisController` | Destinataire réel + colis livré + transporteur assigné | Tests rating |
| LOG-013 | Token existant utilisable après bannissement/suspension | Oui | Critique | Faible | middleware, routes, admin | Middleware actif et révocation des tokens | Tests comptes/admin |
| LOG-014 | Téléphone non unique alors qu’il identifie le destinataire | Oui | Haute | Moyen | validation + migration | Normalisation, validation, index unique | Tests + migration réelle |
| LOG-015 | Propriété de téléphone non vérifiée par OTP | Oui | Haute | Élevé | architecture métier | **Partiel** : unicité corrigée, OTP/SMS à concevoir | Manual review |
| LOG-016 | Factory utilisateur incompatible avec le schéma | Oui | Moyenne | Faible | `UserFactory.php` | Factory alignée | Test de création |
| LOG-017 | Ticket frontend perdait `colis_id` | Oui | Haute | Faible | `TicketController` | Lecture du contrat snake_case réel | Test contrat |
| LOG-018 | Ticket attachable à un colis tiers et champs non bornés | Oui | Haute | Faible | `TicketController` | Autorisation et limites | Tests ticket |
| LOG-019 | Modération admin applicable à un non-transporteur | Oui | Haute | Faible | `AdminController` | Cible restreinte, validation, révocation | Tests admin |
| LOG-020 | Appels frontend sans timeout et erreurs Laravel masquées | Oui | Moyenne | Faible | `frontend/src/lib/api.js` | AbortController 15 s, erreurs de validation | Build |
| LOG-021 | Routes privées frontend sans contrôle de rôle | Oui | Haute | Faible | `App.jsx` | Rôle attendu par dashboard | Build |
| LOG-022 | Champs frontend/backend incompatibles | Oui | Moyenne | Faible | plusieurs pages React | Dates, livreur imbriqué, permis, voyageur rating | Build |
| LOG-023 | UI expéditeur proposait une notation toujours refusée | Oui | Moyenne | Faible | dashboard expéditeur | Action incohérente retirée ; notation destinataire conservée | Build |
| LOG-024 | Identifiants admin de démonstration présents dans le bundle production | Oui | Haute | Faible | `Auth.jsx` | Affichage limité à `import.meta.env.DEV` | Recherche dans `dist` |
| LOG-025 | Lanceur régénérait `APP_KEY` à chaque démarrage | Oui | Critique | Faible | `LANCER_SITE.bat` | Génération seulement si clé absente | Revue statique |
| LOG-026 | Lanceur reseedait et réinitialisait les comptes à chaque fois | Oui | Haute | Faible | lanceur + seeder | Seed seulement sur nouvelle DB, refus production | Revue statique |
| LOG-027 | Endpoints publics sans quota/format de tracking | Oui | Moyenne | Faible | `routes/api.php` | Rate limits et identifiant alphanumérique de 10 caractères | Routes + tests |
| LOG-028 | Token Sanctum dans `localStorage` | Oui | Moyenne | Élevé | architecture auth | **Partiel** : aucune injection HTML trouvée ; migration cookies HttpOnly non faite | Manual review |
| LOG-029 | Prix stocké en `float` et `is_paid` couplé à la livraison | Oui | Moyenne | Élevé/métier | schéma colis | **MANUAL REVIEW REQUIRED** | Non modifié |
| LOG-030 | Bundle frontend monolithique (~792 kB) | Oui | Faible | Moyen | routing/build | Optimisation différée pour éviter un refactoring risqué | Warning Vite |

## 3. Corrections réalisées

### Sécurité et authentification

- Les comptes bannis ou temporairement suspendus sont bloqués sur toutes les routes Sanctum, y compris avec un token déjà émis.
- Les tokens sont révoqués lors d’un bannissement ou d’une suspension automatique.
- L’inscription normalise email/téléphone, impose un mot de passe de 8 à 72 caractères et valide les documents selon le rôle.
- Le téléphone est unique au niveau validation et base de données.
- Les endpoints publics sensibles ont un rate limit.
- Les identifiants de démonstration ne sont plus inclus dans le bundle de production.

### Intégrité des colis

- Le PIN est haché ; sa valeur en clair n’est disponible qu’une fois lors de la création.
- La prise en charge est transactionnelle et réservée aux transporteurs approuvés ; un seul transporteur gagne la course.
- Les transitions sont bornées et les retries n’ajoutent pas d’historique ou de notification en double.
- La validation PIN, l’état livré, l’historique et la notification sont dans une même transaction SQL.
- Les notes sont réservées au destinataire correspondant et au transporteur réellement assigné.

### Confidentialité et contrats API

- Le suivi public ne renvoie plus le nom, le téléphone, les adresses exactes, l’expéditeur ou le téléphone du livreur.
- Les annonces disponibles n’exposent plus les coordonnées avant assignation.
- Le contrat `colis_id` des tickets est restauré sans changer le format JSON frontend.
- Les erreurs Laravel sont rendues lisibles côté frontend ; les IDs de tracking sont encodés dans l’URL.

### Robustesse locale

- Timeout frontend de 15 secondes, sans retry automatique des mutations.
- `LANCER_SITE.bat` préserve `APP_KEY`, utilise `npm ci`, ne seed qu’une base créée par lui et propage les erreurs.
- Le seeder de démonstration refuse explicitement l’environnement production.

## 4. Dépendances

| Paquet | Avant | Après | Raison | Évaluation breaking change | Validation |
|---|---:|---:|---|---|---|
| `postcss` | 8.5.15 | 8.5.23 | Correctif sécurité transitif | Patch, faible risque | `npm audit`: 0 ; build PASS |
| `nanoid` | 3.3.12 | 3.3.17 | Correctif sécurité transitif | Patch, faible risque | `npm audit`: 0 ; build PASS |
| `guzzlehttp/guzzle` | 7.11.0 | 7.11.0 | Version sûre requise `>=7.15.2` | Compatible avec Laravel `^7.8.2` en théorie ; non téléchargé | **FAIL / MANUAL** |
| `guzzlehttp/psr7` | 2.11.0 | 2.11.0 | Version sûre requise `>=2.12.3` | Patch/minor compatible ; non téléchargé | **FAIL / MANUAL** |
| `league/commonmark` | 2.8.2 | 2.8.2 | Version sûre requise `>=2.9.0` | Minor compatible avec Laravel `^2.8.1` en théorie ; non téléchargé | **FAIL / MANUAL** |

Références officielles examinées : GitHub Security Advisories et releases officielles de Guzzle/CommonMark. La source de vérité locale finale reste `composer audit`.

## 5. Fichiers modifiés

```text
LANCER_SITE.bat
backend-new/app/Http/Controllers/AdminController.php
backend-new/app/Http/Controllers/AuthController.php
backend-new/app/Http/Controllers/ColisController.php
backend-new/app/Http/Controllers/MiscController.php
backend-new/app/Http/Controllers/TicketController.php
backend-new/app/Models/Colis.php
backend-new/app/Models/User.php
backend-new/bootstrap/app.php
backend-new/database/database.sqlite (migration additive appliquée)
backend-new/database/factories/UserFactory.php
backend-new/database/seeders/DatabaseSeeder.php
backend-new/routes/api.php
frontend/package.json
frontend/package-lock.json
frontend/src/App.jsx
frontend/src/lib/api.js
frontend/src/pages/Auth.jsx
frontend/src/pages/Home.jsx
frontend/src/pages/admin/Dashboard.jsx
frontend/src/pages/destinataire/Dashboard.jsx
frontend/src/pages/expediteur/Dashboard.jsx
frontend/src/pages/livreur/Dashboard.jsx
frontend/src/pages/voyageur/Dashboard.jsx
frontend/dist/index.html (généré)
frontend/dist/assets/index-BUGMcK_7.css (généré)
frontend/dist/assets/index-hJ5kSnHb.js (généré)
```

Fichier runtime également mis à jour par PHPUnit : `backend-new/.phpunit.result.cache`.

## 6. Fichiers créés

```text
AUDIT_LOGISTICS_2026-08-12.md
backend-new/app/Http/Middleware/EnsureAccountIsActive.php
backend-new/database/migrations/2026_08_12_000001_add_unique_phone_to_users_table.php
backend-new/tests/Feature/AccountSecurityTest.php
backend-new/tests/Feature/AdminSecurityTest.php
backend-new/tests/Feature/ColisSecurityTest.php
backend-new/tests/Feature/PublicCourierPrivacyTest.php
backend-new/tests/Feature/TicketSecurityTest.php
```

## 7. Fichiers supprimés

Aucun fichier source supprimé. Les anciens bundles hashés de `frontend/dist/assets` ont été remplacés automatiquement par Vite.

## 8. Tests et validations exécutés

| Commande | Résultat | Sortie pertinente |
|---|---|---|
| Lint PHP de `app`, `routes`, `database` | PASS | `PHP_LINT_PASS` |
| `composer validate --strict` | PASS | `composer.json is valid` |
| `composer check-platform-reqs` | PASS | PHP 8.5.1 et extensions satisfaites |
| `php artisan route:list --path=api` | PASS | 28 routes API |
| `php artisan test` | PASS | 28 tests, 81 assertions |
| `php artisan migrate --force` | PASS | migration téléphone, batch 3 |
| Smoke API public/auth/colis | PASS | allowlist tracking, rôle expéditeur, 4 colis |
| `npm run build` | PASS | 2290 modules, bundle généré |
| `npm audit --omit=dev` | PASS | 0 vulnérabilité |
| `npm audit` | PASS | 0 vulnérabilité |
| Recherche credentials dans `dist` | PASS | identifiants de démo absents |
| `composer audit --format=json` | FAIL connu | 17 avis sur 3 paquets |
| Update Composer ciblé `--dry-run` | NOT RUN | accès réseau Packagist refusé |
| `vendor/bin/pint --test app routes database tests` | FAIL style | dette de formatage historique étendue ; aucun auto-fix massif |
| ESLint / TypeScript / Vitest | NOT RUN | scripts/configurations absents |
| Test navigateur interactif | NOT RUN | aucun navigateur contrôlable disponible |

Un smoke test de connexion a créé un token Sanctum temporaire identifié précisément ; ce token de test a été supprimé immédiatement. Aucun autre token ni donnée utilisateur n’a été supprimé.

## 9. Risques restants

### HIGH — MANUAL ACTION REQUIRED

1. Mettre à jour les trois dépendances Composer avec accès Packagist :

   ```text
   composer update guzzlehttp/guzzle guzzlehttp/psr7 league/commonmark --with-all-dependencies --minimal-changes
   composer audit
   php artisan test
   ```

2. Mettre en place une preuve de possession du téléphone (OTP SMS) avant de considérer le numéro comme identité du destinataire.

### MEDIUM — MANUAL REVIEW REQUIRED

- Décider si le paiement doit être distinct de l’état `delivered`, et migrer `price` vers un décimal selon la règle métier.
- Définir un canal fiable pour transmettre le PIN au destinataire (SMS/e-mail sécurisé) ; actuellement l’expéditeur doit le partager.
- Pour un déploiement web public, préférer un cookie HttpOnly/SameSite à un bearer token dans `localStorage`, avec migration auth complète.
- Ajouter ESLint, un typecheck et des tests frontend sans les inventer ni introduire une migration TypeScript massive.

### LOW

- Découper progressivement le bundle frontend par routes ; le chunk JS de production reste supérieur à 500 kB.
- Appliquer Pint dans une opération séparée afin de ne pas mélanger un reformatage massif avec les correctifs de sécurité.

## 10. Configuration et secrets

Fichiers inspectés sans afficher les valeurs :

```text
backend-new/.env: APP_KEY — conserver localement, ne jamais publier
frontend/.env: VITE_API_URL — configuration publique, pas un secret
```

Le projet n’a pas de dépôt Git. Il est donc impossible de produire un vrai `git diff`, d’attribuer automatiquement les modifications préexistantes ou de revenir atomiquement à une révision antérieure.

## 11. Évaluation de non-régression

- Les noms de routes et la conversion camelCase/snake_case sont conservés.
- Les réponses privées complètes restent disponibles aux propriétaires/transporteurs assignés ; seules les réponses publiques ou pré-assignation sont réduites.
- Les rôles, Sanctum, dashboards, tickets, notifications et tracking restent en place.
- Les transitions correspondent aux boutons existants des dashboards livreur/voyageur.
- Les changements de dépendances npm sont des correctifs patch ciblés.
- La base n’a subi qu’un index unique sur une colonne vérifiée sans doublon.
- Aucun framework, service, Docker, Git ou architecture de déploiement n’a été ajouté.
