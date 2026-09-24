# Content Catalog API

A Content Studio plugin that gives external tools (an AI agent, an import
script) authenticated JSON access to a site's blocks, pages and plugin data.
It can read everything below and write pages and plugin resources.

## Setup

1. Install the package and activate **Content Catalog API** under
   Plugins in the admin. Activating runs the migrations.
2. Open **Plugins → Content Catalog API**, turn the API on and rotate an API
   key. The key is shown once; only a hash is stored.
3. Optionally set **Turn off after** a number of hours. The API switches itself
   off when that time has passed.

The same page lists every request: time, operation, the last four characters
of the key, IP address and the `purpose` the caller gave.

## Authentication and limits

Send the key as a bearer token:

```http
GET /api/content-catalog HTTP/1.1
Authorization: Bearer <api key>
Accept: application/json
```

- While the API is off or has no key, every endpoint returns `404`.
- A missing or wrong key returns `401`.
- Each IP address gets 60 requests per minute. After that the API returns `429`.
- Responses carry `Cache-Control: no-store, private`.

Every request may include a `purpose` (query string for `GET`, body for
writes). It ends up in the request log and helps you tell callers apart.

## Endpoints

| Method | Path | What it does |
| --- | --- | --- |
| `GET` | `/api/content-catalog` | Blocks, templates, pages and plugins in one response |
| `GET` | `/api/content-catalog/blocks` | Block catalog: every block type and its fields |
| `GET` | `/api/content-catalog/templates` | Page templates of the active theme and their settings fields |
| `GET` | `/api/content-catalog/templates/{key}` | One template |
| `GET` | `/api/content-catalog/pages` | All pages with their blocks |
| `GET` | `/api/content-catalog/pages/{id}` | One page |
| `POST` | `/api/content-catalog/pages` | Create a page |
| `PUT` | `/api/content-catalog/pages/{id}` | Replace a page (full payload) |
| `PATCH` | `/api/content-catalog/pages/{id}` | Update the fields you send |
| `GET` | `/api/content-catalog/plugins` | Active plugins with their blocks and resources |
| `GET` | `/api/content-catalog/plugins/{plugin}` | One active plugin, by slug |
| `GET` | `/api/content-catalog/plugins/{plugin}/resources/{resource}` | All records of a resource |
| `GET` | `/api/content-catalog/plugins/{plugin}/resources/{resource}/{id}` | One record |
| `POST` | `/api/content-catalog/plugins/{plugin}/resources/{resource}` | Create a record |
| `PUT` | `/api/content-catalog/plugins/{plugin}/resources/{resource}/{id}` | Replace a record (full payload) |
| `PATCH` | `/api/content-catalog/plugins/{plugin}/resources/{resource}/{id}` | Update the fields you send |

Every response wraps its payload in `data`. Lists are not paginated.

## Blocks

`GET /blocks` returns each block type with its fields:

```json
{
  "data": [
    {
      "type": "cases.cases",
      "label": "Cases (plugin)",
      "group": "Plugins",
      "fields": [
        { "name": "source", "type": "select", "required": false, "default": "all",
          "options": { "all": "Alle gepubliceerde cases", "categories": "Cases uit gekozen categorieën", "selected": "Zelf gekozen cases" } },
        { "name": "limit", "type": "select", "default": "all", "options": { "all": "Alle cases", "3": "3 cases" } }
      ]
    }
  ]
}
```

Use these definitions to build the `fields` of a block when writing a page or
a `blocks` field of a resource.

## Templates

`GET /templates` lists the page templates of the active theme. `settings`
holds the fields of the template's settings, in the same shape as block fields:

```json
{
  "data": [
    {
      "key": "met-cta",
      "label": "Shazzoo met CTA",
      "description": "Als Shazzoo Standaard, met het CTA-blok onderaan.",
      "settings": [
        { "name": "show_page_title", "type": "toggle", "required": false, "default": false },
        { "name": "title", "type": "text", "label": "Kop", "required": false, "default": "Een half uur kost u niets" }
      ]
    }
  ]
}
```

Use these fields to fill `template_settings` on a page, or a resource field
of the `template_settings` type.

## Pages

A page as the API returns it:

```json
{
  "id": 12,
  "title": "Cases",
  "slug": "cases",
  "translation_key": "5b0e…",
  "locale": "nl",
  "is_active": true,
  "template_key": "default",
  "template_settings": {},
  "seo_title": null,
  "seo_description": null,
  "seo": { "title": "Wat er bij onze klanten draait", "description": "…" },
  "header": [],
  "updated_at": "2026-09-23T09:14:56.000000Z",
  "blocks": [
    { "position": 0, "uuid": "8f7c…", "type": "section-intro", "fields": { "title": "…" } }
  ]
}
```

Empty field values (`null`, `""`, `[]`) are left out of `fields`.

### Writing a page

```http
POST /api/content-catalog/pages
Content-Type: application/json

{
  "title": "Cases per categorie",
  "slug": "cases-legacy",
  "locale": "nl",
  "is_active": false,
  "purpose": "import legacy cases page",
  "blocks": [
    { "type": "cases.cases", "fields": { "source": "categories", "categories": ["Legacy"] } }
  ]
}
```

| Field | `POST` / `PUT` | `PATCH` | Notes |
| --- | --- | --- | --- |
| `title` | required | optional | max 255 |
| `locale` | required | optional | max 12 |
| `blocks` | required | optional | replaces all blocks when sent |
| `slug` | optional | optional | `alpha_dash`, unique per locale |
| `translation_key` | optional | optional | UUID |
| `is_active` | optional | optional | boolean |
| `template_key` | optional | optional | a template key from `GET /templates` |
| `template_settings` | optional | optional | object, checked against the template's settings fields |
| `seo_title`, `seo_description`, `seo`, `header` | optional | optional | |
| `if_updated_at` | — | optional | see [Conflicts](#conflicts) |

Each block is `{ "type", "fields", "uuid"? }`. Keep the `uuid` from a read to
update a block in place; leave it out and a new one is generated. Block fields
are checked against the block catalog:

- the type must exist, and every field must be defined for it
- required fields without a default must be present
- `toggle` fields must be `true` or `false`
- `select` and `radio` values must be one of the option keys. A list is
  accepted when every value in it is an option key (multi-selects).
- `repeater` items are checked against the repeater's own fields

Fields you leave out get the block's default value.

`template_settings` is checked the same way against the settings fields of
the page's template: the `template_key` in the request, else the page's
current template, else `default`. Settings are stored as sent; the template
supplies its own defaults for keys that are missing.

## Plugins

`GET /plugins` lists active plugins. Each entry has `key`, `slug`, `name`,
`description`, `version`, `source`, the plugin's `blocks` and its `resources`.
Internal details such as the provider class and install path are not exposed.

A resource in that list:

```json
{
  "key": "cases",
  "label": "Cases",
  "endpoint": "/api/content-catalog/plugins/cases/resources/cases",
  "fields": [
    { "name": "title", "type": "text", "required": true },
    { "name": "is_published", "type": "toggle", "required": false, "default": true }
  ],
  "items_count": 9,
  "items": [ { "id": 1, "title": "…" } ]
}
```

A resource whose table does not exist yet is listed with no items, and its
endpoints return `404` until the plugin's migrations have run.

## Resources

A resource is a plugin model the API can list and write, such as employees,
contact forms or cases.

### Reading

`GET …/resources/{resource}` returns all records in the resource's order;
`GET …/resources/{resource}/{id}` returns one. A record contains `id`, every
declared field and `updated_at` when the model has timestamps:

```json
{
  "id": 3,
  "image_id": 41,
  "image": { "id": 41, "url": "https://example.test/storage/avery.jpg", "alt": "Avery Johnson" },
  "name": "Avery Johnson",
  "role": "Software Engineer",
  "skills": ["Laravel", "AI"],
  "updated_at": "2026-09-23T12:00:00.000000Z"
}
```

A `media` field returns its id and, next to it, the media item under the field
name without `_id` (`image_id` → `image`). A `blocks` field returns blocks in
the same shape as page blocks.

### Writing

Send the declared fields as a flat JSON object:

```http
POST /api/content-catalog/plugins/cases/resources/cases
Content-Type: application/json

{
  "title": "Offertes uit een klantvraag",
  "category": "AI-toepassing",
  "has_page": true,
  "slug": "offertes-uit-een-klantvraag",
  "content": [
    { "type": "text-section", "fields": { "title": "Waar het begon", "body": "…", "aside": "none" } }
  ],
  "purpose": "add case"
}
```

- `POST` returns `201` with the new record; `PUT` and `PATCH` return `200`.
- A resource declared with `"creatable": false` returns `405` on `POST`.
- `POST` and `PUT` need every required field without a default. `PATCH` only
  checks the fields you send.
- Fields you leave out on `POST` get their declared default.
- A field that is not declared is rejected. Only declared fields are ever
  written.
- `if_updated_at` works as for pages.

### Declaring resources in a plugin

A plugin exposes resources by listing them under `api_resources` in its
`plugin.json`. The plugin does not need a dependency on this package.

```json
{
  "key": "shazzoo/cases",
  "slug": "cases",
  "name": "Cases",
  "provider": "Shazzoo\\Cases\\CasesServiceProvider",
  "api_resources": [
    {
      "key": "cases",
      "label": "Cases",
      "model": "Shazzoo\\Cases\\Models\\CaseStudy",
      "order_by": "sort_order",
      "fields": [
        { "name": "title", "type": "text", "required": true },
        { "name": "slug", "type": "text", "unique": true },
        { "name": "is_published", "type": "toggle", "default": true },
        { "name": "content", "type": "blocks" }
      ]
    }
  ]
}
```

| Key | Required | Meaning |
| --- | --- | --- |
| `key` | yes | Resource key in the URL |
| `model` | yes | Eloquent model class |
| `fields` | yes | Fields the API reads and writes (see below) |
| `label` | no | Display name; defaults to the key in headline case |
| `order_by` | no | Column to sort lists by; defaults to the primary key |
| `creatable` | no | `false` for a resource that can only be edited, such as a settings row. Defaults to `true` |

Each field has a `name` (the model attribute) and a `type`. Optional keys:
`required`, `default`, `unique` (unique in the model's table), `options` and
`multiple` for selects, `max` for text, and `template_from` for template
settings.

| Type | Accepts |
| --- | --- |
| `text` | string, max 255 unless `max` is set |
| `textarea` | string |
| `number` | number |
| `toggle` | `true` / `false` |
| `select` | an option key, or a list of option keys when `multiple` is true |
| `tags` | list of strings |
| `media` | id of an item in the media library |
| `repeater` | list of objects |
| `json` | any object or list |
| `blocks` | list of blocks, validated like page blocks and stored as page content |
| `template` | a template key from `GET /templates` |
| `template_settings` | object, checked against the settings of the template named in the field `template_from` (default `template_key`). Not checked while that field is empty |

Declare only fields the model can store. The model needs casts for `tags`,
`repeater`, `json`, `blocks` and `template_settings` fields (`array`) and for
toggles (`boolean`).
Leave out fields that should stay private, such as a form's recipient address:
the API never reads or writes undeclared columns.

A declaration with a missing key, model or field type is skipped and logged as
a warning. Resources of inactive plugins are not available.

### Built-in resources

Plugins released before `api_resources` existed get a declaration from this
package:

| Plugin | Resource | Fields |
| --- | --- | --- |
| `shazzoo/contact-form` | `contact_forms` | `name`, `key` (unique), `subject_prefix`, `button_label`, `success_message`, `privacy_note`, `fields` |
| `shazzoo/employees` | `employees` | `image_id` (media), `name`, `role`, `skills` (tags) |
| `shazzoo/strategy-engine-plugin` | `settings` | `index_template_key`, `index_template_settings`, `article_template_key`, `article_template_settings`. Edit only (`creatable: false`) |

The recipient of a contact form is deliberately not exposed. Of the Strategy
Engine settings only the templates are: the overview and article pages use
the chosen template with these settings. The AI and route settings stay out
of the API. A plugin that
declares its own `api_resources` replaces its built-in declaration.

## Conflicts

Send the `updated_at` you read as `if_updated_at` on `PUT` or `PATCH`. If the
page or record changed since then, the API returns `409` and writes nothing.
Fetch it again and retry.

## Errors

| Status | When |
| --- | --- |
| `401` | Missing or wrong API key |
| `404` | API off, unknown page, template, plugin, resource or record, or a resource without a table |
| `405` | `POST` to a resource that cannot be created |
| `409` | `if_updated_at` does not match |
| `422` | Validation failed; `errors` maps each field path to its messages |
| `429` | More than 60 requests per minute from one IP address |

Validation errors use Laravel's format, with dotted paths into blocks:

```json
{
  "message": "The selected block type is invalid.",
  "errors": {
    "blocks.0.type": ["The selected block type is invalid."],
    "blocks.1.fields.categories": ["The selected value is invalid."]
  }
}
```
