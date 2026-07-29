# Renommage du paquet Composer : halleck45/ast-metrics vers ast-metrics/ast-metrics

Contexte mesuré le 29/07/2026 : 1721 téléchargements au total, 581 par mois, 12 par jour.
Seules `v0.0.1` et `v0.0.2` sont publiées (avril 2024). Packagist enregistre encore
l'ancienne URL `Halleck45/ast-metrics-bridge-php`, qui ne fait plus que rediriger.

Deux contraintes structurent tout ce qui suit :

- **Un dépôt, un paquet sur Packagist.** Impossible de publier les deux noms depuis le
  dépôt du bridge. C'est pour ça que l'ancien nom doit déménager vers un dépôt legacy
  avant que le nouveau nom puisse être soumis. **L'ordre des phases n'est pas négociable.**
- **Personne ne suivra automatiquement.** En zone `0.0.x`, `^0.0.2` veut dire
  `>=0.0.2 <0.0.3`. Les utilisateurs actuels sont épinglés au patch près et devront
  éditer leur `composer.json` de toute façon. L'objectif réaliste est donc : rien ne
  casse, et le chemin est balisé.

Ce qui ne change pas pour l'utilisateur : le binaire reste `vendor/bin/ast-metrics`.
C'est ce que les scripts CI appellent réellement.

---

## Phase 0 : décisions

- [ ] Nom retenu : `ast-metrics/ast-metrics` (modèle `phpstan/phpstan`)
- [ ] Namespace PHP : renommer `Halleck45\AstMetrics\` en `AstMetrics\Bridge\` ou le garder.
      Recommandation : renommer. La seule classe jamais publiée est
      `Halleck45\AstMetrics\AstMetricsProxy`, interne à un outil CLI. Le découpage actuel
      (`Binary/`, `Process/`, `Compatibility/`) n'a jamais été publié, donc le coût réel
      est nul. Si tu préfères garder, saute l'étape 3.2.

---

## Phase 1 : créer le dépôt legacy

Les tags `v0.0.1` et `v0.0.2` doivent s'y trouver **avec leur code**. Quand Packagist
change d'URL, il recrawle et reconstruit sa liste de versions depuis les tags trouvés :
sans eux, ces versions disparaissent du listing et `composer require halleck45/ast-metrics:^0.0.2`
ne résout plus. Un tag étant un instantané immuable, réduire la branche principale au
métapaquet ne leur enlève rien : tout se fait en une passe, pas en deux temps.

```bash
# 1.1 Dépôt vide, sans README (le push écraserait tout de toute façon)
#     Le préfixe d'organisation est obligatoire : sans lui, gh crée le dépôt sous ton
#     compte personnel, et la suite clone un dépôt vide au lieu du bon.
gh repo create ast-metrics/ast-metrics-bridge-php-legacy --public \
  --description "Legacy Packagist entry for halleck45/ast-metrics. Renamed to ast-metrics/ast-metrics."

# 1.2 Historique et tags.
#     --bare et non --mirror : --mirror récupère aussi refs/pull/*, les références de pull
#     requests que GitHub expose en lecture seule et refuse en écriture ("deny updating a
#     hidden ref"). Les branches et les tags passent quand même, mais git sort en erreur,
#     ce qui laisse croire que le miroir a échoué.
cd /tmp && rm -rf legacy.git
git clone --bare git@github.com:ast-metrics/ast-metrics-bridge-php.git legacy.git
cd legacy.git
git push git@github.com:ast-metrics/ast-metrics-bridge-php-legacy.git --all
git push git@github.com:ast-metrics/ast-metrics-bridge-php-legacy.git --tags
cd /tmp && rm -rf legacy.git

# 1.3 Réduire la branche principale au métapaquet
cd /tmp && rm -rf legacy
git clone git@github.com:ast-metrics/ast-metrics-bridge-php-legacy.git legacy
cd legacy

# Garde-fou : sans les tags mirorés, on n'est pas dans le bon dépôt et la suite
# détruirait le contenu d'autre chose.
git tag | grep -q v0.0.2 || { echo "MAUVAIS DEPOT, on s'arrete"; exit 1; }

git rm -r --quiet .
cat > composer.json <<'EOF'
{
    "name": "halleck45/ast-metrics",
    "description": "Renamed to ast-metrics/ast-metrics. This package only forwards to it.",
    "type": "metapackage",
    "license": "MIT",
    "authors": [
        {
            "name": "Jean-François Lépine",
            "email": "lepinejeanfrancois@gmail.com"
        }
    ],
    "require": {
        "ast-metrics/ast-metrics": "*"
    }
}
EOF
cat > README.md <<'EOF'
# halleck45/ast-metrics

This package was renamed to [`ast-metrics/ast-metrics`](https://packagist.org/packages/ast-metrics/ast-metrics).

```bash
composer remove halleck45/ast-metrics
composer require --dev ast-metrics/ast-metrics
```

The command stays the same: `php vendor/bin/ast-metrics analyze src`.

This repository only exists to keep the old Composer name resolvable. The code lives at
[ast-metrics/ast-metrics-bridge-php](https://github.com/ast-metrics/ast-metrics-bridge-php).
EOF
composer validate --no-check-all --no-check-publish
git add composer.json README.md
git commit -m "Forward halleck45/ast-metrics to ast-metrics/ast-metrics"

# 1.4 Un seul tag, une seule fois : le métapaquet transmet avec une contrainte lâche,
#     il n'aura jamais à être retagué à chaque release de l'analyseur.
git tag -a v1.0.0 -m "Renamed to ast-metrics/ast-metrics"
git push origin main --tags

# 1.5 Nettoyer la branche héritée du miroir, sinon Packagist la listera
git push origin --delete experimental_fix
```

- [ ] Vérifier l'état complet du dépôt legacy. Les quatre références doivent être là, et
      `main` doit descendre de l'historique mirroré, pas être un commit orphelin :

```bash
git ls-remote https://github.com/ast-metrics/ast-metrics-bridge-php-legacy.git
```
      Attendu : `refs/heads/main`, `refs/tags/v0.0.1`, `refs/tags/v0.0.2`, `refs/tags/v1.0.0`,
      et **pas** de `refs/heads/experimental_fix`.

- [ ] Vérifier qu'aucun dépôt parasite n'a été créé sous le compte personnel, ce qui arrive
      si le préfixe d'organisation a été oublié à l'étape 1.1 :

```bash
gh repo view Halleck45/ast-metrics-bridge-php-legacy 2>/dev/null \
  && echo "PARASITE A SUPPRIMER : gh repo delete Halleck45/ast-metrics-bridge-php-legacy --yes" \
  || echo "aucun parasite"
```

---

## Phase 2 : basculer l'ancien paquet sur Packagist (interface web)

À faire **avant** la phase 4 : tant que l'URL du dépôt du bridge est enregistrée sous
l'ancien nom, la soumission du nouveau nom sera refusée avec « repository already in the list ».

> **Fenêtre de casse inévitable, à garder courte.** Le métapaquet `v1.0.0` requiert
> `ast-metrics/ast-metrics`, qui n'existe pas encore sur Packagist à ce stade. Entre cette
> phase et la phase 4, un `composer require halleck45/ast-metrics` sans contrainte résoudra
> `v1.0.0` puis échouera sur la dépendance manquante. Les contraintes `^0.0.2`, elles, ne
> sont pas affectées. Enchaîne les phases 3 et 4 dans la même session.

> **L'URL doit être celle de l'organisation**, `ast-metrics/...`, et non `Halleck45/...`.
> Une URL vers un dépôt qui n'a pas les tags mirorés fait disparaître `v0.0.1` et `v0.0.2`
> du listing, et casse toute installation par contrainte. C'est arrivé le 29/07/2026.

- [ ] Sur https://packagist.org/packages/halleck45/ast-metrics , en tant que mainteneur,
      changer l'URL du dépôt vers `https://github.com/ast-metrics/ast-metrics-bridge-php-legacy`.
      Packagist valide que le `name` du nouveau dépôt correspond au paquet : c'est pour ça
      que le métapaquet doit déclarer `halleck45/ast-metrics`.
- [ ] Lancer un « Update » manuel, puis vérifier :

```bash
curl -sS https://packagist.org/packages/halleck45/ast-metrics.json \
  | python3 -c "
import json,sys
d=json.load(sys.stdin)['package']
print('depot:', d['repository'])
print('versions:', sorted(v for v in d['versions'] if not v.startswith('dev-')))
"
```
      Attendu : la nouvelle URL, et `v0.0.1`, `v0.0.2`, `v1.0.0` toujours présentes.
      **Si `v0.0.1` ou `v0.0.2` a disparu, arrête-toi** : le miroir des tags a échoué.

- [ ] Marquer le paquet abandonné vers `ast-metrics/ast-metrics` (bouton « Abandon » sur
      la page du paquet). À savoir : Composer affiche l'avertissement sur `update` et
      `require`, **pas** sur `install` depuis un lock. Une CI existante ne verra donc rien.

---

## Phase 3 : renommer dans le dépôt du bridge

Sur la branche de travail actuelle `feat/streaming-and-phpmetrics-compatibility`, ou une
branche dédiée.

```bash
cd /home/jflepine/Personnel/Projets/ast-metrics-bridge-php
```

### 3.1 composer.json

- [ ] `"name": "ast-metrics/ast-metrics"`
- [ ] Ajouter `"replace": { "halleck45/ast-metrics": "*" }`

Le `*` est délibéré, pas de la paresse : `self.version` ne couvrirait pas les contraintes
`^0.0.2` existantes, puisque les nouvelles versions seront en `0.41.x`. Son rôle est
d'empêcher que les deux paquets s'installent ensemble et se disputent
`vendor/bin/ast-metrics` quand l'ancien nom arrive par une dépendance transitive.

- [ ] Corriger les URLs `support` vers le dépôt courant

### 3.2 Namespace (optionnel, voir phase 0)

Deux `sed` distincts, et ce n'est pas une redondance : dans un fichier PHP le namespace
s'écrit avec un seul backslash, dans `composer.json` le JSON le double. Un seul motif pour
les deux échouerait silencieusement sur l'un des deux.

Commandes vérifiées sur une copie du dépôt le 29/07/2026 : les 66 assertions passent après
renommage.

```bash
# Fichiers PHP : un backslash littéral
sed -i 's/Halleck45\\AstMetrics/AstMetrics\\Bridge/g' \
  $(grep -rl 'Halleck45\\AstMetrics' src bin tests)

# composer.json : backslash doublé par le JSON
sed -i 's/Halleck45\\\\AstMetrics\\\\/AstMetrics\\\\Bridge\\\\/' composer.json

# Contrôle : il ne doit plus rester que le nom du paquet legacy et l'adresse e-mail
grep -rn "Halleck45" src bin tests composer.json

composer dump-autoload
make check
```

### 3.3 Documentation et vérification

- [ ] README : `composer require --dev ast-metrics/ast-metrics`, plus une note sur
      l'ancien nom
- [ ] `make check` passe
- [ ] Committer

---

## Phase 4 : publier le nouveau nom

- [ ] Soumettre `https://github.com/ast-metrics/ast-metrics-bridge-php` sur
      https://packagist.org/packages/submit
- [ ] Vérifier que le hook GitHub vers Packagist est actif sur ce dépôt (sinon les tags
      poussés par le workflow ne déclencheront rien)
- [ ] Pousser un tag pour publier une première version sous le nouveau nom, ou laisser la
      prochaine release d'ast-metrics le faire via le job `php-bridge` de `distribute.yml`

---

## Phase 5 : vérifications de bout en bout

Le seul point qui peut surprendre est l'interaction entre `replace: *` et le métapaquet
qui requiert le nouveau paquet. À tester réellement plutôt qu'à supposer.

```bash
# 5.1 Le nouveau nom
cd $(mktemp -d) && composer init --no-interaction --name=test/rename
composer require --dev ast-metrics/ast-metrics
ls -l vendor/bin/ast-metrics && php vendor/bin/ast-metrics version

# 5.2 L'ancien nom sans contrainte : doit aboutir, et ne produire qu'un seul binaire
cd $(mktemp -d) && composer init --no-interaction --name=test/legacy
composer require --dev halleck45/ast-metrics:*
composer show | grep ast-metrics
ls -l vendor/bin/ast-metrics && php vendor/bin/ast-metrics version

# 5.3 L'ancien nom épinglé : doit installer v0.0.2 et rien d'autre
cd $(mktemp -d) && composer init --no-interaction --name=test/pinned
composer require --dev "halleck45/ast-metrics:^0.0.2"
composer show halleck45/ast-metrics
```

- [ ] 5.1 installe et exécute le binaire
- [ ] 5.2 aboutit, avec un seul `vendor/bin/ast-metrics`
- [ ] 5.3 installe encore `v0.0.2` (preuve que les locks et contraintes existants tiennent)

---

## Ne pas faire

**Supprimer l'ancien paquet de Packagist.** Les 581 installations mensuelles passeraient
en erreur dure. Le métapaquet coûte un dépôt et un tag, la suppression coûte des builds
cassés chez des gens qui n'ont rien demandé.

**Renommer le dépôt GitHub du bridge.** Le job `php-bridge` de `distribute.yml` clone
`ast-metrics/ast-metrics-bridge-php` en dur. Si tu le renommes, mets ce workflow à jour
dans le même mouvement.

**Supprimer le dépôt parasite avant d'avoir vérifié que Packagist ne le référence plus.**
Le 29/07/2026 l'URL du paquet a pointé vers lui par erreur : le supprimer à ce moment-là
aurait fait pointer Packagist vers le vide. Vérifier d'abord avec la commande de contrôle
de la phase 2, supprimer ensuite.

---

## Retour en arrière

Rien n'est irréversible avant la phase 2. Après :

- L'URL Packagist se rechange dans les deux sens.
- Le drapeau abandonné se retire.
- Les tags `v0.0.1` et `v0.0.2` existent en double (ancien dépôt et legacy), donc aucune
  perte possible de ce côté.
