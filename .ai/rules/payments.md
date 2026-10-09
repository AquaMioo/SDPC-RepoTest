---
paths:
  - 'app/Services/Payments/**'
---

# Payments

## PayMongo over plain HTTP, simulated checkout until a key is set
App\Contracts\PaymentGateway is bound in AppServiceProvider: PayMongoGateway (Http client, Basic auth with PAYMONGO_SECRET_KEY, POST/GET /v1/checkout_sessions, payment_method_types ['gcash']) when the key is set, else SimulatedPaymentGateway, whose "hosted page" is SimulatedCheckoutController (payments.simulated.*, 404 once a key exists; clears with a pay_sim_ id, nothing charged). No gateway package (.ai/rules/config.md). Clearance arrives two ways, both through SettleAddendumPayment: the success_url return (AddendumPaymentController::paid asks the gateway; authorises 'view', not 'pay', because the webhook may already have completed the addendum) and POST webhooks/paymongo (CSRF-exempt; PayMongoSignature checks HMAC-SHA256 of "t.rawbody" with PAYMONGO_WEBHOOK_SECRET against te/li; 404 without a secret). The webhook must be registered in the PayMongo dashboard for checkout_session.payment.paid. Billing's dormant transactions table is NOT used; addendum_payments is the ledger and agreements.addenda.records prints it.
