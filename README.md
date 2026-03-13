# GEOTIERS POUR [DOLIBARR ERP & CRM](https://www.dolibarr.org)

Module développé par **[ForLead](https://forlead.fr)** — contact@forlead.fr

## Présentation

**GeoTiers** est un module de cartographie interactive des **tiers** (clients, prospects, fournisseurs) pour Dolibarr ERP & CRM.

Il offre une carte Leaflet/OpenStreetMap intégrée à Dolibarr, permettant de visualiser géographiquement l'ensemble de vos tiers, de les filtrer par nature, et d'explorer leurs informations clés directement depuis la carte, sans quitter votre ERP.

<!--
![Screenshot geotiers](img/screenshot_geotiers.png?raw=true "GeoTiers"){imgmd}
-->

## Fonctionnalités

- 🗺️ **Carte interactive** basée sur Leaflet / OpenStreetMap
- 📍 **Marqueurs colorés par nature** : clients, prospects, fournisseurs, multi-type (couleurs personnalisables)
- 🔍 **Filtres dynamiques** : filtrer par tiers, afficher/masquer clients, prospects, fournisseurs
- 👤 **Popup détaillée** au clic sur un marqueur : nom, adresse, type de société, nature (C/P/F), lien Street View
- 📞 **Contacts associés** affichés dans la popup : nom, poste, téléphone fixe, mobile, email
- 💼 **Commerciaux assignés** affichés dans la popup : nom, téléphone, mobile, email
- 📏 **Filtre par rayon** : saisir un rayon en km depuis n'importe quel point pour n'afficher que les tiers à proximité
- 🔄 **Réinitialisation du rayon** en un clic
- 🔢 **Compteur de tiers affichés** mis à jour en temps réel
- 🖥️ **Mode plein écran** de la carte
- 🌍 **Lien Google Street View** intégré dans chaque popup

## Paramétrage

Depuis le menu **Configuration > Modules > GeoTiers > Paramètres** :

- Couleur des marqueurs clients
- Couleur des marqueurs fournisseurs
- Couleur des marqueurs prospects
- Couleur des marqueurs multi-type

## Traductions

Les traductions sont gérables manuellement en éditant les fichiers dans le répertoire `langs/` du module.

Langues actuellement disponibles :

- 🇫🇷 Français (`fr_FR`)
- 🇺🇸 English (en_US)

Les contributions de traduction sont les bienvenues.

## Installation

**Prérequis :** Dolibarr ERP & CRM doit être installé sur votre serveur.  
Téléchargeable sur [dolibarr.org](https://www.dolibarr.org) ou disponible en mode SaaS sur [saas.dolibarr.org](https://saas.dolibarr.org).

### Depuis le fichier ZIP (interface graphique)

1. Téléchargez le fichier `module_geotiers-x.x.x.zip` depuis le [Dolistore](https://www.dolistore.com)
2. Dans Dolibarr, allez dans **Accueil > Configuration > Modules > Déployer un module externe**
3. Uploadez le fichier ZIP
4. Activez le module depuis la liste des modules

### Étapes finales

Depuis votre navigateur :

1. Connectez-vous à Dolibarr en tant que super-administrateur
2. Allez dans **Configuration > Modules**
3. Recherchez **GeoTiers** et activez-le
4. Rendez-vous dans les **Paramètres** du module pour personnaliser les couleurs des marqueurs
5. Assurez-vous que vos tiers ont bien une adresse complète (adresse, code postal, ville) pour être géolocalisés

## Prérequis techniques

- Les tiers doivent avoir une **adresse renseignée** (adresse, code postal, ville)

## Support

Pour toute question ou demande de support :

- 🌐 Site : [forlead.fr](https://forlead.fr)
- 📧 Email : [contact@forlead.fr](mailto:contact@forlead.fr)

Les bugs peuvent être signalés via la page du module sur le Dolistore.

## Autres modules

D'autres modules externes sont disponibles sur [Dolistore.com](https://www.dolistore.com).

## Licences

### Documentation

Tous les textes et fichiers README sont sous licence [GFDL](https://www.gnu.org/licenses/fdl-1.3.en.html).

---

*Module GeoTiers — © [ForLead](https://forlead.fr) — [contact@forlead.fr](mailto:contact@forlead.fr)*