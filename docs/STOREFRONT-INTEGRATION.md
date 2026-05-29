# Custom Forms - maho-storefront (headless) integration

How this module's forms work on the **headless** [maho-storefront](https://github.com/mageaustralia/maho-storefront)
(Cloudflare Workers + Hono + Stimulus), as opposed to the server-rendered Maho
theme. Read `SCHEMA.md` first - the form `schema` JSON is the contract; this
doc is about wiring that contract into the storefront's actual runtime.

> TL;DR - the storefront **cannot run the `customforms/form` PHP block**. It
> renders fields **client-side from the schema** and posts back through its
> own same-origin `/api/*` proxy. So this module must expose the schema +
> submissions as **API Platform v2 resources**, and the CMS embed must be a
> **plain placeholder `<div>`**, not a `{{block}}` directive.

---

## Why the current embed doesn't work headless

`Helper::getCmsSnippet()` emits:

```
{{block type="customforms/form" form_code="trade_application"}}
```

On the server theme this directive renders the `form.phtml` `<form>`. On the
storefront it does **not**:

- The storefront caches CMS page **content** from the Maho API and **strips
  unprocessed `{{...}}` directives** during sanitisation (`rewriteContentUrls`
  -> `sanitizeCmsHtml`). The directive is removed -> nothing renders.
- Even if the API returned the fully-rendered `<form>` HTML, the storefront's
  HTML sanitiser **drops `<form>`, `<input>`, `<select>`, `<button>`,
  `<textarea>`** (XSS allowlist). Only structural tags + `class` / `id` /
  `style` / `data-*` / `aria-*` survive.

**Conclusion:** the only thing that can survive into a storefront-rendered CMS
page is an inert placeholder element. The actual fields must be rendered by
client JS from the schema.

---

## The contract (3 pieces)

### 1. CMS embed = a neutral placeholder `<div>`

Change the embed snippet (and what authors paste) to a plain, renderer-neutral
placeholder that survives the storefront sanitiser **and** can be hydrated by
the server theme:

```html
<div data-maho-form="trade_application"></div>
```

- `div` + `data-*` are on the storefront sanitiser allowlist -> it survives CMS
  sync untouched.
- It carries no renderer-specific coupling (no `data-controller`, no Stimulus
  naming) - the storefront adapter adds that at runtime (below).
- The **server theme** can hydrate the same placeholder (its own small script,
  or a layout block that targets `[data-maho-form]`), so one snippet works in
  both renderers - matching SCHEMA.md's "one schema -> identical render".

Recommended module change: have `getCmsSnippet()` return this placeholder
(keep `getLayoutSnippet()` for theme layout-XML use). Keep accepting the legacy
`{{block}}` directive on the server theme for back-compat, but document the
placeholder as the portable form.

### 2. Schema + submissions = API Platform **v2** resources

The storefront talks to the backend exclusively through its `/api/*` proxy,
which **rewrites `/api/<x>` -> `/api/rest/v2/<x>`** and forwards the method,
body, `Content-Type`, `Authorization` and `X-Store-Code` **same-origin** (so
**no CORS is involved for the storefront**). Therefore expose:

| Friendly path (what the browser calls) | Proxied to (what you must implement) |
|---|---|
| `GET /api/custom-forms/{code}` | `GET /api/rest/v2/custom-forms/{code}` |
| `POST /api/custom-forms/{code}/submissions` | `POST /api/rest/v2/custom-forms/{code}/submissions` |

So the headless endpoints **must live under the API Platform v2 namespace**
(`/api/rest/v2/custom-forms/*`) - a `Mage_Core_Controller_Front_Action` like the
current `FormController` is **not** reachable through the storefront proxy.
(The legacy `FormController` can stay for the server theme; the headless path is
the v2 resource.)

`GET` returns `{ code, schema, captcha, renderToken }` (see SCHEMA.md). It's
public + CDN-cacheable - the storefront edge-caches the proxied response.

`POST` body is exactly SCHEMA.md's headless contract:

```json
{ "payload": { ... }, "captchaToken": "<altcha>", "renderToken": "...", "_hp": "" }
```

The server stays authoritative: re-run the full `processSubmission()` pipeline
(honeypot `_hp`, `renderToken` time-trap, per-IP rate limit, captcha verify,
schema validation) and return:

- `200 { status: "ok", message }`
- `422 { status: "invalid", errors: { fieldKey: msg }, message }`
- `429 { status: "rate_limited", message }`

> **CORS:** only matters for the **embed widget** (`/embed.js` on third-party
> sites) or a direct cross-origin call - for those, allow the configured
> storefront origin(s). The storefront's own pages don't need it (same-origin
> proxy).

### 3. Renderer = a storefront **plugin** (Stimulus controller)

The storefront renderer ships in the storefront repo as a plugin under
`src/plugins/custom-forms/` (see the storefront's plugin system:
https://docs.mageaustralia.com.au/architecture/plugins). It is a **manifest
plugin** - controller only, no server routes (the generic `/api/*` proxy
already carries the post-back):

```
src/plugins/custom-forms/
|-- index.ts        # PluginManifest: registers the `custom-form` controller (+ CSP if altcha)
|-- controller.js   # Stimulus `custom-form` controller
`-- csp.ts          # (only if altcha widget loads from a URL) - see CSP below
```

A tiny bootstrap in the storefront finds `[data-maho-form]` placeholders and
attaches the controller (so the CMS snippet stays neutral). The controller:

1. reads `code` from `data-maho-form`,
2. `GET /api/custom-forms/{code}` (same-origin -> proxied -> v2; edge-cached),
3. renders fields from `schema` by `type`, lays out by `width`, applies
   `showIf` reactively, pages through `steps` if present,
4. mounts the altcha widget from the `captcha` block,
5. client-validates from the same `validate` rules (UX only - server is
   authoritative),
6. `POST .../submissions` with `payload + captchaToken + renderToken + _hp`;
   renders `errors` against fields or the success `message`.

Form elements are created in JS, so the sanitiser's `<form>`/`<input>` stripping
is irrelevant - nothing form-shaped ever passes through CMS HTML.

---

## CSP (altcha / any third-party widget)

If the altcha (or any) widget loads a script/connects to an origin, the
storefront's CSP must allow it. The storefront uses **plugin-owned CSP**: each
plugin declares its sources in `csp.ts` and registers them in
`src/plugins/csp.ts` (same model as the Stripe plugin). So the custom-forms
storefront plugin adds, e.g.:

```ts
// src/plugins/custom-forms/csp.ts
export const CUSTOM_FORMS_CSP = {
  scriptSrc: ['https://<altcha-origin>'],   // if the widget is a hosted script
  connectSrc: ['https://<altcha-origin>'],  // challenge endpoint
};
```

If altcha is self-hosted on the Maho origin and the challenge is fetched via the
`/api/*` proxy (same-origin), **no extra CSP is needed**. Prefer that.

---

## File uploads (`type: file`)

The `/api/*` proxy forwards the raw request body for non-GET, so a
`multipart/form-data` POST passes through. If you support file fields, keep the
v2 submissions endpoint accepting multipart and document max sizes - the Worker
forwards the body but has its own request-size ceiling.

---

## What goes where

| Concern | Owner |
|---|---|
| `schema` JSON contract, validation rules | this module (`SCHEMA.md`) - authoritative on the server |
| `GET /api/rest/v2/custom-forms/{code}` (schema + captcha + renderToken) | **this module** (new API Platform resource) |
| `POST /api/rest/v2/custom-forms/{code}/submissions` (full anti-spam pipeline) | **this module** (new API Platform resource) |
| CMS embed = `<div data-maho-form="CODE">` placeholder | **this module** (`getCmsSnippet()` change) |
| Stripping/sanitising CMS HTML, the `/api/*` proxy | storefront core (already exists) |
| `custom-form` Stimulus controller + manifest + CSP plugin | **storefront repo** (`src/plugins/custom-forms/`) |
| `customform_submission_created` event -> B2B/CRM/ERP | downstream subscribers (unchanged) |

## Build checklist

- [ ] Add API Platform v2 resources: `custom-forms/{code}` (GET) and
      `custom-forms/{code}/submissions` (POST), reusing `Helper::processSubmission()`
      and `validatePayload()` so server stays authoritative.
- [ ] `renderToken` is issued by GET and verified by POST (stateless time-trap;
      no session/form-key dependency - the storefront is cookieless for this).
- [ ] Change `getCmsSnippet()` to the `<div data-maho-form="CODE"></div>`
      placeholder; keep `{{block}}` working on the server theme.
- [ ] Confirm `captcha.challengeUrl` is reachable from the storefront - prefer
      same-origin via `/api/*` so no CSP/CORS changes are needed.
- [ ] Storefront repo: add `src/plugins/custom-forms/` (controller + manifest,
      `[data-maho-form]` bootstrap, optional `csp.ts`). PR against
      mageaustralia/maho-storefront.
- [ ] Verify end-to-end on a storefront CMS page: placeholder hydrates, schema
      renders, `showIf`/steps work, submit returns 200/422/429 correctly.
