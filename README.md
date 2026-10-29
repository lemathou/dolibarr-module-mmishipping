# MMIShipping — Expédition et réception MMI : affectation DF, autoliquidation, édition du port, analyse des coûts transporteur et hooks PDF.

> Module Dolibarr **MMIShipping** (version 1.1), branche `20.0`.

## Fonctionnalités

### Fonctionnalités principales

- Affectation du destinataire DF et réception/envoi entrepôt (`MMISHIPPING_DF`, `MMISHIPPING_DF_ENTREPOT`).
- Autoliquidation de TVA sur les frais de port (`MMI_SHIPPING_PORT_EDIT`, droits dédiés).
- Édition du port sur les commandes fournisseur (`port_edit`).
- Analyse des coûts transporteur : import CSV, analyse, trigger `CarrierCostTriggers` (`carrier_cost_analyse.php`).
- Correction d'autoliquidation (`fix_autoliquidation.php`).
- Hooks PDF expédition/réception : tri par référence et adresse de réception (RM3582).

### Écrans et menus

- **Accueil** — `/custom/mmishipping/mmishippingindex.php` (menu mmishipping, droit `1`)
- **Expéditions/Réceptions avancées** — `/mmishipping/mmishippingindex.php` (menu mmishipping, droit `1`)

### Pages

- `admin/about.php`
- `admin/setup.php`
- `carrier_cost_analyse.php`
- `fix_autoliquidation.php`
- `mmishippingindex.php`

### Permissions

- `mmishipping.df.affect` — Affectation Destinataire DF
- `mmishipping.df.autoliquidation` — Autoliquidation Réception/Expédition DF
- `mmishipping.commande.port_edit` — Modifier les frais de port des commandes clients (RM2381)

### Configuration

| Constante | Type | Rôle |
|---|---|---|
| `MMI_SHIPPING` | separator | Expéditions |
| `MMISHIPPING_DF` | yesno | Gestion auto réceptions/expéditions contremarques |
| `MMISHIPPING_DF_ENTREPOT` | int | Entrepôt "virtuel" pour la livraison directe (réception/expédition) |
| `SHIPPING_PDF_HIDE_WEIGHT_AND_VOLUME` | yesno | Masquer la colonne "poids et volume" dans les expéditions |
| `SHIPPING_PDF_HIDE_VOLUME` | yesno | Masquer les volumes (conserver les poids) dans les expéditions |
| `MMI_SHIPPING_PREPA_MULTI` | yesno | Bon de prépa pour plusieurs expéditions |
| `MMI_EXPE_ORDER_LINES_BY_REF` | yesno |  |
| `MMI_RECEPTION` | separator | Réceptions |
| `MMI_RECEPTION_SENDER_ADDRESS` | yesno | Affichage de l'adresse du dépît ou du contact selon le type de réception |
| `STOCK_DISPLAY_WARN_IN_SHIPMENT_ENHANCED` | yesno | Afficher les warnings dans les expé de façon plus lisible |
| `STOCK_DISPLAY_WARN_IN_SHIPMENT_ONLY_NEEDED` | yesno | Masquer les warnings dans les expé si déjà expédié |
| `MMI_SHIPPING_CARRIER` | separator | Gestion transporteurs |
| `MMI_SHIPPING_CARRIER_AUTOCALC` | yesno | Répercution automatique des frais de port des expéditions sur les commandes |
| `MMI_SHIPPING_CARRIER_SHIPPING_PRODUCT_ID` | int | ID Produit à analyser/créer/mettre à jour pour les expéditions |
| `MMI_SHIPPING_PORT` | separator | Frais de port |
| `MMI_SHIPPING_PORT_EDIT` | yesno | Bouton "Frais de port" sur la fiche commande client (modification sans repasser en brouillon) |

### Hooks

- Contextes : `ordersuppliercard`, `supplierorderlist`, `receptiondao`, `shipmentlist`, `ordercard`, `expeditioncard`, `expeditionlist`, `receptioncard`, `receptionlist`
- Callbacks : `formConfirm`, `addMoreMassActions`, `doMassActions`, `doPreMassActions`, `getLabel`, `printFieldPreListTitle`, `printFieldListSearchParam`, `printFieldListWhere`, `beforePDFCreation`, `pdf_build_address`

### Triggers

- `core/triggers/interface_99_modMMIShipping_CarrierCostTriggers.class.php`

### Classes et composants

- `class/actions_mmishipping.class.php`
- `class/mmishipping.class.php`
- `lib/mmishipping.lib.php`

## Prérequis

- Dolibarr >= 11.0 (branche `20.0` : Dolibarr 20.0).
- Modules MMI : `mmicommon`. **`mmicommon` doit être activé en premier.**
- Modules cœur : `product`, `fournisseur`, `expedition`, `reception`.

## Installation

1. Copier le module dans `htdocs/custom/mmishipping` :

   ```sh
   cd htdocs/custom
   git clone <dépôt> mmishipping
   ```

2. Dans Dolibarr : **Configuration → Modules**, activer d'abord `mmicommon`, puis ce module.
3. Vérifier les permissions dans la fiche des utilisateurs concernés.

## Licence

GPLv3 ou ultérieure — voir `COPYING`. Documentation sous GFDL.

## Changelog

Voir [CHANGELOG.md](CHANGELOG.md) (évolutions datées).
