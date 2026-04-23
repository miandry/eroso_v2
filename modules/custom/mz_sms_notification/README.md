# MZ SMS Notification

Dispatches SMS through pluggable gateways. Ships with:

- **Log gateway** (`log`) — writes to the Drupal logger, no delivery. Default.
- **Orange Madagascar gateway** (`orange_madagascar`) — sample real-world implementation using the Orange Developer SMS API (OAuth2 client credentials + `outboundSMSMessageRequest`).

## How it works

```
[node `sms` is created]
        │
        ▼
hook_node_insert (mz_sms_notification)
        │ enabled && auto_dispatch_on_insert?
        ▼
SmsDispatcher::dispatchNode($node)
        │ picks active gateway from config
        ▼
OrangeMadagascarGateway::send($to, $message)
        │ cached OAuth2 token (state)
        ▼
POST /smsmessaging/v1/outbound/{senderAddress}/requests
        │
        ▼
Result written back onto sms node
  field_delivery_status   = sent | failed
  field_delivery_response = "HTTP 201 Accepted ..."
  field_delivery_at       = now (UTC)
  field_provider_message_id = resourceURL
```

Any code that saves an `sms` node (the `mz_sms_api` REST API, the `mz_eroso_v2` order notifier, a manual `Node::create(['type' => 'sms', ...])`, …) will automatically trigger delivery.

## Install

```bash
drush en mz_sms_notification -y
drush updb -y       # runs mz_sms_notification_update_10001 if needed
drush cr
```

The install hook adds four tracking fields to the `sms` bundle:
`field_delivery_status`, `field_delivery_response`, `field_delivery_at`, `field_provider_message_id`.

## Configure Orange Madagascar

1. Create an app on <https://developer.orange.com/> and enable the **SMS API** product for it.
2. Under "My apps" copy the **Client Id** and **Client Secret**.
3. Obtain an authorised MSISDN for outbound SMS (typically your Orange Madagascar number provisioned with the SMS API product, in `+261` format).
4. Navigate to **Administration › Configuration › Web services › MZ SMS Notification** (`/admin/config/services/mz-sms-notification`) and fill in:
   - Active gateway: `Orange Madagascar`
   - Client id / secret
   - Sender address: `tel:+261340000000` (use your number)
   - Default country prefix: `+261`
   - Tick **Actually deliver SMS**
5. Hit **Save**, then open **Send test SMS** and try a delivery.

### Sample cURL (what the module does under the hood)

**1. Get a token:**

```bash
curl -X POST "https://api.orange.com/oauth/v3/token" \
  -H "Authorization: Basic $(echo -n 'CLIENT_ID:CLIENT_SECRET' | base64)" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=client_credentials"
```

Response:

```json
{
  "token_type": "Bearer",
  "access_token": "abcd1234...",
  "expires_in": 3600
}
```

**2. Send an SMS:**

```bash
curl -X POST \
  "https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B261340000000/requests" \
  -H "Authorization: Bearer abcd1234..." \
  -H "Content-Type: application/json" \
  -d '{
    "outboundSMSMessageRequest": {
      "address": ["tel:+261340000000"],
      "senderAddress": "tel:+261340000000",
      "outboundSMSTextMessage": { "message": "Hello from Drupal" }
    }
  }'
```

Success response (HTTP `201`):

```json
{
  "outboundSMSMessageRequest": {
    "resourceURL": "https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B261340000000/requests/abcdef"
  }
}
```

The `resourceURL` is what gets stored in `field_provider_message_id`.

## Writing another gateway

1. Implement `Drupal\mz_sms_notification\SmsGatewayInterface`.
2. Declare it in `*.services.yml` with the tag `mz_sms_notification.gateway`.
3. Register it on the dispatcher:

```yaml
# my_module.services.yml
services:
  my_module.gateway.nexmo:
    class: Drupal\my_module\Gateway\NexmoGateway
    tags:
      - { name: mz_sms_notification.gateway, id: nexmo }
```

4. In `mz_sms_notification.services.yml` add the dispatcher call:

```yaml
    calls:
      - [addGateway, ['@my_module.gateway.nexmo']]
```

(Alternatively, resolve the dispatcher from your own module and call
`$dispatcher->addGateway($yourGateway)` at runtime.)

The gateway id you return from `getId()` is what users pick in the settings form.

## Resending a failed SMS

```
/admin/config/services/mz-sms-notification/resend/{nid}
```

Re-runs the current gateway for an existing `sms` node and rewrites the delivery fields.

## Disabling globally

Uncheck **Actually deliver SMS** on the settings form or, from code:

```bash
drush config:set mz_sms_notification.settings enabled 0 -y
```

The Log gateway remains available if you still want to see what *would* be sent.
