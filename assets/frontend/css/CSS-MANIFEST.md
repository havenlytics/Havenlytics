# Havenlytics Frontend CSS Manifest

Internal ownership reference for frontend styles.  
**Phase 0 + Phase 1** (architecture only — selectors unchanged).

---

## Load order (global)

```
1. Foundation     → hvnly-frontend-default, hvnly-frontend-components, hvnly-frontend-global-grid
2. Components     → cards, property-card-embed, widgets, …
3. Views          → property-single, agents-archive, ajax-filter (search UI), archive, map
4. Integrations   → elementor/css/*
5. Responsive     → hvnly-frontend-property-responsive (always last static bundle)
6. Dynamic        → DynamicStyleGenerator (inline, priority 999)
```

---

## Ownership matrix

| Handle | File | Layer | Loads when |
|--------|------|-------|------------|
| `hvnly-frontend-default` | `hvnly-frontend-default.css` | Foundation | Always |
| `hvnly-frontend-components` | `hvnly-frontend-components.css` | Foundation | Always |
| `hvnly-frontend-global-grid` | `hvnly-frontend-global-grid.css` | Layout | Always |
| `hvnly-frontend-cards` | `hvnly-frontend-cards.css` | Component | Agent/agency archives, widgets |
| **`hvnly-frontend-property-card-embed`** | **`property-cards/hvnly-frontend-property-card-embed.css`** | **Component** | **Auto via `hvnly-frontend-property-ajax-filter` dependency** |
| `hvnly-frontend-widgets` | `hvnly-frontend-widgets.css` | Component | Property single, widgets |
| `hvnly-frontend-property-ajax-filter` | `property-search/hvnly-frontend-property-ajax-filter-search.css` | Search UI | Archive, shortcodes, agent/agency singles, Elementor property widgets, pages with search form |
| `hvnly-frontend-property-archive` | `property-search/hvnly-frontend-property-archive.css` | View | Property archive / taxonomy / search |
| `hvnly-frontend-property-map` | `property-search/hvnly-frontend-property-map.css` | View | Map-enabled listing pages |
| `hvnly-frontend-property-single` | `hvnly-frontend-property-single.css` | View | Property CPT single (+ agent single today — Phase 2 split) |
| `hvnly-frontend-property-agents-archive` | `hvnly-frontend-property-agents-archive.css` | View | Agent/agency archives, agency single layout |
| `hvnly-frontend-property-responsive` | `hvnly-frontend-property-responsive.css` | Responsive | Always (last) |
| `hvnly-shortcodes` | `shortcodes/hvnly-shortcodes.css` | View | Pages with Havenlytics shortcodes |

---

## Property card embed contexts

Property grid/list **card** styles in `hvnly-frontend-property-card-embed.css` target these parent scopes:

| Context | Parent selector |
|---------|-----------------|
| Native property archive | `.hvnly-property--grid--listings` |
| Shortcode / grid page | `.hvnly-property-display-shortcode .hvnly-property-archive-container` |
| Agent single listings | `.hvnly-agent-single__listings .hvnly-agent-single__properties` |
| Agency single listings | `.hvnly-agency-single__properties .hvnly-agency-single__property-item` |

**BEM blocks owned by embed file:**  
`.hvnly-property-grid-list-item`, `.hvnly-property--grid-list--*`, `.hvnly-property-grid-list-*`, thumbnail badges/status dropdowns, card carousel, embed view controls.

---

## What belongs in `hvnly-frontend-property-ajax-filter-search.css`

After Phase 1, this file owns **search & archive chrome only**:

- Search form, filter toggle, multi-select dropdowns
- Filter sidebar, range sliders, checkboxes
- Grid/list **layout shell** (`.hvnly-property-grid-view`, `.hvnly-grid-view`, `.hvnly-list-view` container — not card internals)
- Map view placeholder, pagination, load-more, AJAX loading states
- Archive responsive rules for sidebar/search (not card badge rules)

**Rule:** Do not add property card overlay/thumbnail/badge rules to ajax-filter. Add them to `hvnly-frontend-property-card-embed.css`.

---

## Related files (not in embed)

| File | Owns |
|------|------|
| `hvnly-frontend-property-responsive.css` | Global responsive overrides for grid-list (cross-context) |
| `hvnly-frontend-cards.css` | `.hvnly-agent-card`, `.hvnly-agency-card` only |

---

## Phase 2+ (planned, not implemented)

- Split `hvnly-frontend-property-single.css` → property / agent single
- Split agency block from `hvnly-frontend-property-agents-archive.css`
- `Hvnly_Style_Registry` central enqueue
- Optional `.hvnly-property-card-embed` host class (template change — Phase 3)

---

## Maintenance checklist

When changing property card UI:

1. Edit `property-cards/hvnly-frontend-property-card-embed.css`
2. Keep all four embed parent contexts in sync
3. Do not duplicate rules into ajax-filter
4. Hard refresh + test: archive, shortcode, agent listings, agency listings, Elementor grid

When changing search/filter UI:

1. Edit `property-search/hvnly-frontend-property-ajax-filter-search.css` only
