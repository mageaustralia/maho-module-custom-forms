# Custom Forms - schema & headless contract

The form `schema` (one JSON document per form) is the single source of truth and
the contract between Maho and any renderer (the server-rendered Maho theme AND
the headless maho-storefront). Build both renderers against this doc.

## Form schema

```json
{
  "version": 1,
  "title": "Apply for a trade account",
  "steps": [
    {
      "key": "business",
      "title": "Your business",
      "fields": [
        {
          "key": "company_name",
          "type": "text",
          "label": "Company name",
          "required": true,
          "width": "full",
          "placeholder": "Acme Pty Ltd",
          "validate": { "minLength": 2, "maxLength": 255 }
        },
        {
          "key": "company_vat",
          "type": "text",
          "label": "ABN / VAT",
          "required": true,
          "width": "half",
          "validate": { "pattern": "^[0-9 ]{8,20}$" }
        },
        {
          "key": "business_type",
          "type": "select",
          "label": "Business type",
          "width": "half",
          "options": [
            { "value": "reseller", "label": "Reseller" },
            { "value": "installer", "label": "Installer" }
          ]
        },
        {
          "key": "trade_refs",
          "type": "textarea",
          "label": "Trade references",
          "showIf": { "field": "business_type", "eq": "reseller" }
        }
      ]
    }
  ]
}
```

A flat form omits `steps` and uses a top-level `"fields": [ ... ]`. The helper
flattens both shapes (`getSchemaFields()`).

### Field object

| Key | Meaning |
|---|---|
| `key` | **Stable machine key.** Payload is keyed by this; the B2B adapter maps it to a customer/company attribute; never reused for styling. |
| `type` | `text` `textarea` `email` `phone` `number` `select` `radio` `checkbox` `multiselect` `file` `heading` `html` `step` |
| `label` | Display label |
| `required` | bool |
| `width` | `full` `half` `third` (responsive grid; replaces the old "line + CSS id" model) |
| `placeholder` | string |
| `options` | `[{value,label}]` for select/radio/checkbox/multiselect |
| `validate` | `{ minLength, maxLength, pattern, ... }` (pattern is admin-authored, trusted) |
| `showIf` | `{ field, eq }` - field shown only when another field equals a value. Hidden fields are not validated. |

`heading` / `html` / `step` are layout-only and are never validated or stored.

## Settings (separate `settings` JSON)

```json
{
  "captcha": true,
  "successMessage": "Thanks - we'll review and email you.",
  "notify": { "recipients": ["sales@example.com"], "template": "customforms_notify" }
}
```

## API (headless)

### GET `/api/custom-forms/{code}`
Public, CDN-cacheable. Returns the form's schema + a `captcha` block + a signed
`renderToken` (carries issue time for the server-side time-trap):

```json
{
  "code": "trade_application",
  "schema": { ... },
  "captcha": { "enabled": true, "challengeUrl": "https://store/altcha/challenge", "...": "widget attrs" },
  "renderToken": "1717000000.<sig>"
}
```

The `captcha` block comes straight from `Maho_Captcha` (altcha: self-hosted,
privacy-friendly proof-of-work - no third-party site key to leak). The
storefront mounts the altcha widget from `challengeUrl` + widget attributes.

### POST `/api/custom-forms/{code}/submissions`
Authenticated optional (a logged-in customer is linked). Body:

```json
{
  "payload": { "company_name": "Acme", "company_vat": "12 345 678 901", "business_type": "reseller" },
  "captchaToken": "<altcha solution>",
  "renderToken": "1717000000.<sig>",
  "_hp": ""
}
```

Server is authoritative - it re-runs the entire `processSubmission()` pipeline
(honeypot `_hp`, time-trap via `renderToken`, per-IP rate limit, captcha verify,
schema validation), persists, and dispatches `customform_submission_created`.
Responses:

- `200 { "status": "ok", "message": "..." }`
- `422 { "status": "invalid", "errors": { "company_vat": "Please enter a valid value." }, "message": "..." }`
- `429 { "status": "rate_limited", "message": "..." }`

CORS must allow the storefront origin(s).

## Storefront renderer (maho-storefront, separate deliverable)

One Stimulus `custom-form` controller:

1. `GET /api/custom-forms/{code}` -> schema + captcha + renderToken.
2. Render fields by `type`, lay out by `width`, apply `showIf` reactively,
   page through `steps` if present.
3. Mount the altcha widget from the `captcha` block.
4. Validate client-side from the same `validate` rules (UX only).
5. POST payload + captchaToken + renderToken + `_hp` to the submissions
   endpoint; show `errors` against fields or the success `message`.

One schema -> identical render server-side and headless. Validation lives in the
schema and is enforced on both sides, authoritative on the server.

## Anti-spam (all server-side, layered)

| Layer | Mechanism |
|---|---|
| Captcha | `Maho_Captcha` (altcha) - `Mage::helper('captcha')->verify($token)` |
| Honeypot | hidden `_hp` field; non-empty -> silently accepted, nothing stored |
| Time-trap | signed `renderToken` issue time; submit faster than `min_seconds` -> rejected |
| Rate limit | per-IP count in the last hour vs `rate_limit_per_hour` |

## Downstream seam

`customform_submission_created` fires with `submission` + `form`. The B2B
registration module subscribes to it (event-driven) and maps the submission
`payload` (by field key) onto a pending customer/company. Same hook later drives
ERP/CRM webhooks.
