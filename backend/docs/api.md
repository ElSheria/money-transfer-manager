# API Backend ABT-LACOLOMBE

Toutes les routes protegees utilisent un token Bearer obtenu apres verification OTP.

## Authentification

- `POST /api/auth/login`
- `POST /api/auth/verify-otp`
- `GET /api/auth/me`
- `POST /api/auth/logout`

## Dashboard

- `GET /api/dashboard`

Retourne les totaux utiles pour Angular selon le role connecte.

## Agences

- `GET /api/agencies`
- `POST /api/agencies`
- `GET /api/agencies/{agency}`
- `PATCH /api/agencies/{agency}`
- `DELETE /api/agencies/{agency}`

La suppression desactive l'agence avec `is_active = false`.

Les agences initiales sont chargees par le seeder :

```bash
php artisan db:seed --class=AgencySeeder
```

## Staff

- `GET /api/staff`
- `POST /api/staff`
- `PATCH /api/staff/{staff}`
- `DELETE /api/staff/{staff}`

Roles supportes :

- `dg`
- `supervisor`
- `manager`

Un DG peut creer des superviseurs et gerants. Un superviseur peut creer uniquement des gerants dans ses agences supervisees.

## Transferts

- `GET /api/transfers`
- `POST /api/transfers`
- `GET /api/transfers/{transfer}`
- `GET /api/transfers/{transfer}/receipt`
- `GET /api/transfers/code/{code}`
- `PATCH /api/transfers/{transfer}/withdraw`
- `PATCH /api/transfers/{transfer}/cancel`

Le code de transfert suit ce format :

```text
AGENCEDEPART-CODEALEATOIRE-AGENCEDESTINATION
```

Exemple :

```text
KINMA-NAYBGNGNWZ-LUBTEX
```

## Exports

- `GET /api/transfers/export/csv`
- `GET /api/transfers/export/excel`
- `GET /api/transfers/export/pdf`

Filtres supportes :

- `from`
- `to`
- `agency_id`
- `currency`
- `status`

## Audit

- `GET /api/audit-logs`

Reserve au DG.

## Parametres Systeme

- `GET /api/system-settings`
- `PATCH /api/system-settings/{systemSetting}`

Reserve au DG.

Le parametre `transfer_fees` controle les frais automatiques :

```json
{
  "USD": {"type": "percentage", "rate": 2, "minimum": 1},
  "CDF": {"type": "percentage", "rate": 2, "minimum": 1000}
}
```

Types supportes :

- `percentage`
- `fixed`

## Notifications

- `GET /api/transfer-notifications`
- `POST /api/transfer-notifications/send-pending`

Canaux supportes :

- `email`
- `whatsapp`

Variables `.env` pour WhatsApp Cloud API :

```text
WHATSAPP_CLOUD_API_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
```

Commande artisan equivalente :

```bash
php artisan notifications:send-pending --limit=50
```
