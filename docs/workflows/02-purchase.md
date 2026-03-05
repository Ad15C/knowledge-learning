# Workflow 02 — Purchase

## Objectif

Permettre à un utilisateur connecté d’ajouter des items au panier, puis de valider l’achat.

## Routes principales

- `GET /cart`
- `POST /cart/add/lesson/{id}`
- `POST /cart/add/cursus/{id}`
- `POST /cart/remove/{type}/{id}`
- `POST /cart/pay`
- `GET /cart/success/{orderNumber}`

## Paiement (simulation)

- `purchase->calculateTotal()`
- `purchase->markPaid()` (status=paid, paidAt=now)
- flush
- redirect `/cart/success/{orderNumber}`

## Évolution possible

Pour un paiement réel :

- passer en `pending` au moment du checkout
- passer en `paid` via webhook (Stripe/PayPal)