=== Havenlytics – Real Estate Listings, Property Search & Agent Workspace ===
Contributors: havenlytics
Donate link: https://havenlytics.com
Tags: real estate, listings, agency, property, agents
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 3.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Build real estate websites with property listings, AJAX search, interactive maps, visual builders, Elementor widgets, and a secure Agent Workspace.

== Description ==

**Havenlytics** is a WordPress real estate plugin for agencies, agents, developers, and property directories. Create and manage listings with visual builders, advanced filters, interactive maps, galleries, import tools, and built-in analytics.

Agents can manage listings from a secure frontend Workspace without entering wp-admin. Administrators retain control over registration, identity verification, listing review, taxonomy requests, and publishing workflows.

Havenlytics works with **any WordPress theme**. The free [Havenlytics Realty](https://wordpress.org/themes/havenlytics-realty/) companion theme is optional.

📘 Documentation: [https://havenlytics.com/documentation/](https://havenlytics.com/documentation/)
🚀 Live Demo: [https://demo.havenlytics.com/](https://demo.havenlytics.com/)

👤 Agent Workspace Demo: 
🔐 Agent Login: [https://demo.havenlytics.com/agent-dashboard/login](https://demo.havenlytics.com/agent-dashboard/login) 
📝 Agent Registration: [https://demo.havenlytics.com/agent-dashboard/register](https://demo.havenlytics.com/agent-dashboard/register) 
🔑 Forgot Password: [https://demo.havenlytics.com/agent-dashboard/forgot-password](https://demo.havenlytics.com/agent-dashboard/forgot-password)

🌐 Official Website: [https://havenlytics.com/](https://havenlytics.com/)
🎨 Official Theme: [https://wordpress.org/themes/havenlytics-realty/](https://wordpress.org/themes/havenlytics-realty/) (optional)
💬 Community: [https://facebook.com/groups/havenlytics/](https://facebook.com/groups/havenlytics/)
📺 YouTube: [https://www.youtube.com/@havenlytics](https://www.youtube.com/@havenlytics)
📧 Support: [https://havenlytics.com/support/](https://havenlytics.com/support/)

= Quick Start =

1. Install and activate Havenlytics.
2. Run the Property Setup Wizard.
3. Import demo listings with Quick Property Setup, or add your own properties.
4. Customize property fields and search filters with the visual builders.
5. Open **Analytics** in the WordPress admin to review listing and inquiry performance.
6. Optionally install the free Havenlytics Realty companion theme for coordinated layouts and widget areas.


= Who It's For =

- Real estate agencies, brokerages, and independent agents
- Property listing websites and searchable directories
- Property management and real estate development companies
- WordPress professionals building real estate websites for clients

= Why Havenlytics =

🏠 **Purpose-built for real estate** — Manage listings, agents, agencies, and inquiries in one connected system.

📊 **Actionable analytics** — Review listing activity, property views, and inquiry performance in WordPress.

🎨 **Visual configuration** — Build property forms, search filters, and listing cards without editing code.

🔍 **AJAX search and maps** — Help visitors narrow listings with responsive filters, Leaflet or Google Maps, markers, and clustering.

⚡ **Performance controls** — Built-in caching, optimized queries, and cache management for growing property catalogs.

👨‍💻 **Developer-friendly** — Extend Havenlytics with REST APIs, 50+ hooks and filters, and template overrides.

🔒 **Security-conscious workflows** — Capability checks, nonces, identity verification, rate limiting, and audited administrative actions.


= Core Features =

✅ **Analytics Dashboard** — Property statistics, view tracking, inquiry analytics, charts, tables, and CSV export.

✅ **Property Import Wizard** — Guided demo import with media, maps, documents, agents, and custom fields.

✅ **Search Builder** — Drag-and-drop advanced search forms and sidebar filters.

✅ **Property Builder** — Visual property form and card layout editor.

✅ **Interactive Maps** — Leaflet or Google Maps with markers, clustering, and location search.

✅ **Contact Agent & Inquiries** — Inquiry forms, email notifications, and admin inbox.

✅ **Agent & Agency Management** — Profiles, taxonomies, availability badges, and archive pages.

✅ **Agent Workspace** — Frontend portal where agents can create, edit, preview, and submit listings without wp-admin.

✅ **Identity Verification** — Email verification gate, secure tokens, rate-limited verify/resend, and admin tooling.

✅ **Taxonomy Request & Approval** — Agents can request missing property terms while administrators approve, reject, merge, or delete requests.

✅ **Documentation System** — In-plugin guides and links to full documentation at havenlytics.com.

✅ **Elementor Widgets** — Property Archive, Property Agents, and Property Agency.

✅ **Performance & Cache** — Cache dashboard and optimization tools.

✅ **REST API** — Extend settings, builders, Workspace, and integrations for client projects.

✅ **Template Overrides** — Override templates in `your-theme/havenlytics/`.

✅ **Hooks & Filters** — 50+ extension points for agencies and custom workflows.

✅ **Translation Ready** — Full i18n support with included `.pot` file.

**Also includes:** 50+ shortcodes; grid, list, and map layouts; property view counter; 160+ currencies; mortgage calculator widget; media galleries, videos, and documents; and responsive single-property templates.

= Agent Workspace (3.2.0+) =

A frontend portal where approved agents can manage listings without accessing wp-admin.

✅ **Account access** — Login, registration, password recovery, and secure logout flows.

✅ **Agent registration controls** — Choose open or administrator-approved registration with pending, approved, rejected, and suspended states.

✅ **Agent dashboard** — A focused starting point for listing activity and account tasks.

✅ **Property Builder Workspace** — Clear section navigation follows the active Property Builder configuration.

✅ **Listing management** — Create, edit, save drafts, and submit properties for review.

✅ **Property media** — Manage featured images, galleries, documents, and video fields.

✅ **Property preview** — Review a listing before submitting or publishing it.

✅ **Review workflow** — Submit listings for review with ownership and capability checks at every status change.

✅ **Builder synchronization** — Workspace forms follow the live Property Builder fields and sections.

✅ **Responsive interface** — Designed for desktop, tablet, and mobile screens.

✅ **Workspace REST API** — Cookie-authenticated `hvnly/v1` property and identity endpoints for integrations.

= Identity Verification (3.3.0) =

Secure email verification helps ensure that only eligible, verified agents can access the Workspace.

✅ **Email verification gate** — Agents must verify their email before accessing the Workspace.

✅ **Two-condition authorization** — Workspace access requires both an allowed registration status and completed identity verification, enforced server-side.

✅ **Extensible verification framework** — Developers can register additional factors through `hvnly_identity_factors` without changing the authorization layer.

✅ **Secure single-use tokens** — Selector + verifier, HMAC-at-rest, expiry, and timing-safe compare.

✅ **Branded verification emails** — Verification, confirmation, and resend messages include expiry and security guidance.

✅ **Rate-limited public endpoints** — CSRF-protected verify and resend with non-enumerating responses.

✅ **Email-change re-verification** — Changing a verified email revokes access and invalidates prior links.

✅ **Verify Your Email screen** — Includes resend cooldowns, accessible status messages, and logout.

✅ **Administrator tools** — View verification status, manually verify, resend an email, apply an emergency override, and review audit events.

= Taxonomy Request & Approval (3.3.0) =

Agents can request missing property taxonomy terms directly from the Property Builder Workspace without interrupting their listing workflow.

✅ **Consistent request action** — Available for property departments, types, statuses, features, locations, tags, and badges.

✅ **Guided request modal** — Agents can provide a requested term, description, and reason, with suggestions for matching existing terms.

✅ **Administrator review** — Review requests under **Havenlytics → Taxonomy Requests**, then approve, reject, merge, or delete them.

✅ **Notifications and email** — Agents receive lifecycle updates in the Workspace and by email when enabled.

✅ **Complete audit history** — Submission and moderation events record the actor, status transition, date, and reason.

✅ **Duplicate prevention** — Normalized matching and a unique active-request key help prevent duplicate requests.

✅ **Rate limiting** — Per-user submission limits reduce abuse while preserving normal agent workflows.

= Analytics =

Use the Analytics dashboard to understand listing activity and inquiry performance from your own WordPress data.

✅ Review property, agent, agency, inquiry, and view statistics.

✅ Explore charts and tables with practical date filters.

✅ Export report data as CSV for spreadsheet analysis.

✅ Track property views without requiring Google Analytics.

= Search Builder =

Create advanced property search forms and sidebar filters with drag-and-drop controls. Choose the fields visitors can use, configure layouts, and keep search experiences aligned with your property data.

🎥 Video tutorial: Build Property Search Forms with Search Builder

[youtube https://www.youtube.com/watch?v=d2mJra8RYM8]

= Property Builder =

Configure the property editing experience without changing templates or plugin code.

✅ Organize standard and custom fields into manageable sections.

✅ Add supported field types, repeaters, documents, galleries, maps, and property media.

✅ Configure property card layouts and keep Agent Workspace forms synchronized with the active schema.

= Email Notifications =

1. Go to **Havenlytics → Settings → Email**.
2. Set an optional sender name and email address.
3. Customize supported subjects and messages with the available merge tags.
4. Enable the notifications appropriate for your site and verify that WordPress can deliver email.

Contact Agent messages are configured under **Settings → Contact Agent**. Workspace identity, listing workflow, and taxonomy request emails use their corresponding Workspace settings and lifecycle events.

= Elementor Widgets =

Requires Elementor Free or Pro. Find the widgets in the **Havenlytics** category or search for **HVN** in the Elementor panel.

**Quick start:** Edit a page with Elementor → add a Havenlytics widget → configure its content and styles → publish.

Use shortcodes for straightforward listing pages or Elementor widgets when combining property content with other page elements.

= HVN: Property Archive =

Full property listing archive — same system as the property grid/list/search shortcodes.

**Content controls:**
* Show/hide filter sidebar and top search bar
* Default view — grid, list, or map
* Sidebar position (left or right)
* Grid columns (1–4)
* Properties per page, order by (date, title, price, random)
* Featured-only filter
* Default filters — department, min/max price, bedrooms, bathrooms

**Style controls:**
* Brand and secondary colors (CSS variables)

**Behavior:**
* AJAX filtering, pagination, and load more
* Inherits global Havenlytics search settings
* Multiple widget instances supported per page

= HVN: Property Agents =

Agent archive matching the native `/property-agents/` page — search, grid/list toggle, pagination, and agent cards with availability badges.

**Content controls:**
* Show/hide header, title, and subtitle
* Show/hide search and view controls
* Agents per page (1–48)
* Grid columns (1–4)
* Default view — grid or list
* Order by name or date added (asc/desc)

= HVN: Property Agency =

Agency archive matching the native `/property-agencies/` page — same card layout and controls as the agents widget, for agency taxonomy listings.

**Content controls:**
* Show/hide header, title, and subtitle
* Show/hide search and view controls
* Agencies per page (1–48)
* Grid columns (1–4)
* Default view — grid or list
* Order by name or date added (asc/desc)

= Sidebar Widgets =

Classic WordPress widgets for single property sidebars (**Appearance → Widgets**, **HAVENLYTICS** category):

* **Featured Properties** — highlight selected listings
* **Property Agent** — agent card for the current property
* **Mortgage Calculator** — tax, insurance, HOA, and PMI options
* **Related Properties** — similar listings on single property pages
* **Agent Listings Carousel** — other listings from the assigned agent

Add to the **Havenlytics - Single Property Sidebar** area (not Elementor widgets).

= Automatically Created Pages =

Pages are created only when a matching slug does not already exist.

**On every activation (including updates):** Property Grid (`/property-grid/`), Property Lists (`/property-lists/`), Property Search (`/property-search/`), Agents (`/property-agents/`), Agency (`/property-agencies/`).

**On fresh installations only:** Rent (`/rent/`), Sale (`/sale/`), Commercial (`/commercial/`), Let (`/let/`) — each with a department-filtered property grid shortcode.

Each page includes the matching shortcode. Replace with Elementor widgets from the **Havenlytics** category if preferred.

= Shortcodes =

Display listings, agents, and agencies in posts, pages, templates, and compatible page builders. Find copy-ready examples and 50+ variations under **Havenlytics → Settings → Shortcodes**.

🎥 Video tutorial: Display Property Content with Shortcodes

[youtube https://www.youtube.com/watch?v=DJ2IYECJ_YA]

= [hvnly_property_grid] =
Responsive property grid. See the [live demo](https://demo.havenlytics.com/property-grid/).

= [hvnly_property_lists] =
Vertical property list layout. See the [live demo](https://demo.havenlytics.com/property-lists/).

= [hvnly_property_search] =
Advanced search with filters. See the [live demo](https://demo.havenlytics.com/property-search/).

Supports department, price, beds, baths, location, status, columns, pagination, default view, and custom CSS class.

= Agent & Agency Shortcodes =

= [hvnly_property_agents] =
Display the agents archive (same layout as `/property-agents/`) with search, grid/list toggle, pagination, and availability badges.

Auto-created page slug: `property-agents`

**Common attributes:** `posts_per_page`, `columns` (1–4), `orderby` (title|date), `order` (ASC|DESC), `show_header`, `title`, `subtitle`, `show_search`, `show_view_controls`, `default_view` (grid|list), `class`

**Examples:**
* `[hvnly_property_agents columns="3"]`
* `[hvnly_property_agents default_view="list" show_search="no"]`
* `[hvnly_property_agents show_header="no" class="my-agents-archive"]`

**Legacy alias:** `[hvnly_agents]`

= [hvnly_property_agencies] =
Display the agencies archive (same layout as **HVN: Property Agency** Elementor widget) with search, grid/list toggle, and pagination.

Auto-created page slug: `property-agencies`

**Common attributes:** same as `[hvnly_property_agents]` above.

**Examples:**
* `[hvnly_property_agencies columns="2"]`
* `[hvnly_property_agencies default_view="list"]`
* `[hvnly_property_agencies show_header="no" class="my-agencies-archive"]`

**Legacy alias:** `[hvnly_agencies]`

== Installation ==

1. Install via **Plugins → Add New** or upload to `/wp-content/plugins/havenlytics/`.
2. Activate through the **Plugins** screen.
3. Complete the Property Setup Wizard and optionally import demo listings.
4. Open **Analytics** in the WordPress admin to review property and inquiry performance.
5. Display listings with shortcodes, Elementor widgets, or the auto-created pages.

📘 Full documentation: [https://havenlytics.com/documentation/](https://havenlytics.com/documentation/)

== Frequently Asked Questions ==

= Do I need the Havenlytics Realty theme? =

No. Havenlytics works with any WordPress theme. [Havenlytics Realty](https://wordpress.org/themes/havenlytics-realty/) is an optional companion theme if you want matched layouts and widget areas.

= How do I customize layouts and design? =

Use the Property Builder for field layouts, shortcodes or Elementor widgets for archives, and theme template overrides in `your-theme/havenlytics/` for full control.

= How do I add custom fields or documents? =

Use the Property Builder **Add Property Form** tab for custom fields. Add a **Property Documents** group for repeatable PDFs and brochures with icons, labels, and URLs.

= Does it support maps? =

Yes — Leaflet (OpenStreetMap) or Google Maps with markers, clustering, and per-property map groups.

= Is it mobile responsive? =

Yes. All frontend templates are responsive across phones, tablets, and desktops.

= How do I clear the cache? =

Go to **Havenlytics → Cache Dashboard** and clear search, sidebar, term caches, or everything at once.

= Does Havenlytics work with Elementor? =

Yes — three widgets under the **Havenlytics** category: Property Archive, Property Agents, and Property Agency. The mortgage calculator is a WordPress sidebar widget, not an Elementor widget.

= How do I display agents or agencies? =

Use `[hvnly_property_agents]` or `[hvnly_property_agencies]`, the matching Elementor widgets, or the auto-created `/property-agents/` and `/property-agencies/` pages.

= How does Contact Agent work? =

Enable under **Settings → Contact Agent**. Visitors submit inquiries from property and agent pages; admins get email notifications and an **Inquiries** inbox. Offline agents hide the contact form.

= Does Havenlytics send email notifications? =

Yes. Havenlytics supports import completion, inquiry, Agent Workspace, identity verification, listing workflow, and taxonomy request emails. Configure general email options under **Havenlytics → Settings → Email** and inquiry messages under **Settings → Contact Agent**. Delivery depends on your WordPress mail configuration.

= How does Agent Workspace email verification work? =

After registration or admin provisioning, agents receive a branded verification email. They must verify before accessing the Workspace. Changing a verified email requires re-verification. Administrators can manually verify, resend, or emergency-disable verification from the Users/Agents screens.

= Is Agent Workspace included in the free plugin? =

Yes. Agent Workspace (auth, property CRUD, media, workflow) shipped in 3.2.0. Identity verification and the email verification gate shipped in 3.3.0.

= How do agents request a missing property term? =

In the Agent Workspace property form, select **Request New** below a supported taxonomy field. The agent can add a term name, description, and reason. Administrators review requests under **Havenlytics → Taxonomy Requests** and can approve, reject, merge, or delete them.

= Can developers extend Havenlytics? =

Yes. Havenlytics is built for agencies and developers who need reliable extension points:

* **REST API** — Settings, builders, analytics, Workspace (`hvnly/v1`), and plugin integrations via `hvnlynab/v1`
* **Hooks & filters** — 50+ actions and filters across listings, search, import, and inquiries
* **Template overrides** — Copy templates to `your-theme/havenlytics/` without editing plugin files
* **Extensible architecture** — Modular services, migration-safe data, and filterable REST class maps
* **WordPress coding standards** — Capability checks, nonces, prepared queries, and PHPCS-friendly code

See the [developer documentation](https://havenlytics.com/docs/category/developers-doc/) for integration guides.

= What is the Analytics Dashboard? =

Havenlytics includes a built-in **Analytics** dashboard in the WordPress admin. It provides insights into properties, agents, agencies, inquiries, property views, and listing activity without requiring a third-party analytics service.

= Does Havenlytics track property views? =

Yes. Havenlytics automatically tracks individual property views on the frontend and displays those statistics in the Analytics Dashboard.

= Can I export Analytics reports? =

Yes. Analytics data can be exported as CSV for use in Microsoft Excel, Google Sheets, LibreOffice, and other spreadsheet applications.

= Does Analytics require Google Analytics? =

No. Havenlytics Analytics works independently using your own WordPress data. You may still use Google Analytics or other tools alongside Havenlytics if you prefer.

= Can I hire your development team? =

Visit [havenlytics.com](https://havenlytics.com/) for custom development services.

== Screenshots ==

1. Analytics Dashboard — property statistics, views, and inquiry reports
2. Advanced Property Search with Filters
3. Property Builder
4. Search Builder — Manage Fields
5. Property Import Setup Wizard
6. AJAX Map Search Results
7. Property Grid Layout
8. Single Property Page — video, gallery, map, and details
9. Contact Agent Inquiry Form
10. Documentation — in-plugin guides and help links
11. Elementor Widget — Property Archive
12. Elementor Widget — Property Agents
13. Elementor Widget — Property Agency
14. Settings Panel
15. Cache Management Dashboard
16. Agent Workspace Dashboard — listing activity and account overview


== Changelog ==

= 3.3.0 - 2026-07-19 =

**Agent Identity Verification and Taxonomy Requests**

* New: Agent identity verification — agents must verify their email before accessing the Agent Workspace.
* New: Two-condition authorization — Workspace access requires an allowed registration status and a satisfied identity verification, enforced server-side.
* New: Extensible verification framework — add phone, 2FA, or KYC factors via the `hvnly_identity_factors` filter without changing authorization.
* New: Secure single-use verification tokens (selector + verifier, HMAC-at-rest, expiry, timing-safe compare).
* New: Branded verification, confirmation, and resend emails with expiry and security notices.
* New: Public rate-limited, CSRF-protected verify and resend endpoints with generic (non-enumerating) responses.
* New: Email-change re-verification — changing a verified email revokes access and invalidates prior links.
* New: "Verify Your Email" SPA page with resend cooldown, accessible states, and logout.
* New: Administrator tooling — email-verified badge, manual verify, resend, and emergency disable, all audit-logged.
* New: Taxonomy Request & Approval workflow for property departments, types, statuses, features, locations, tags, and badges.
* New: Workspace request modal with term name, description, reason, and matching-term suggestions.
* New: Taxonomy Requests admin queue with search, filters, pagination, and approve, reject, merge, and delete actions.
* New: Request detail screen with moderation controls and a complete audit timeline.
* New: In-app notifications and optional email updates for taxonomy request lifecycle events.
* Improved: Taxonomy request admin screens use responsive cards, status badges, accessible controls, and a visual audit timeline.
* Security: Normalized duplicate prevention, atomic active-request keys, per-user rate limiting, capability checks, and nonces protect request submission and moderation.
* Developer: Dedicated request and append-only audit tables, migration support, REST endpoints, and filterable taxonomy policies.
* Improved: Workspace `/me` exposes email verification state for the SPA.
* Security: Verification tokens are never stored or logged in plaintext; a structured audit trail records every event.
* Developer: New public actions and filters documented in `developer-docs/`.
* Security: Plugin info REST endpoint now requires `manage_options`.
* Maintenance: Removed temporary identity and Property Card Builder debug tracers from production code.
* Maintenance: Release build now includes Workspace and Setup bundles; source maps and developer docs stay out of the ZIP.

**Agent Workspace (major)**

* New: Agent Workspace SPA — authenticated frontend portal for agents to manage listings without wp-admin.
* New: Workspace authentication — login, register, forgot password, reset password, logout, and remember me.
* New: Agent registration modes — disabled, open, and approval (pending / approved / rejected / suspended).
* New: Agent dashboard shell with responsive Workspace layout.
* New: Property form driven by live Property Builder schema (dynamic sections and fields).
* New: Property CRUD via Workspace REST (`hvnly/v1`) — create, edit, save draft, trash/restore flows.
* New: Featured image support using WordPress post thumbnails (`set_post_thumbnail` / `delete_post_thumbnail`).
* New: Gallery, documents, video, FAQ, highlights, agents, map, and taxonomy editing in Workspace.
* New: Property preview for Workspace drafts and listings.
* New: Submit for review and publish / status workflow with capability and ownership checks.
* New: Workspace REST identity endpoint (`/me`) and property schema endpoint.
* Improved: Property Builder parity — Workspace renders Builder sections as a tabbed workspace (one active section).
* Improved: Media picking via WordPress Media Library with reliable select handling.
* Improved: Property save integrity — non-destructive PATCH updates; absent fields are not wiped.
* Improved: Agents hydration uses the same assignment meta as frontend ownership checks.
* Improved: Gallery save compatibility with Builder gallery keys and legacy gallery meta.
* Fixed: Featured image, gallery, and video thumbnail persistence across save / reload / edit.
* Fixed: Empty JSON `[]` pollution on scalar Builder titles (FAQ / highlights / agents titles).
* Fixed: Frontend Contact Agent bootstrap no longer loads `WP_List_Table` / `convert_to_screen()` outside wp-admin.
* Security: Workspace property routes require login, portal access, and per-property ownership or admin override.
* Developer: Workspace module constants, asset enqueue, and `hvnly/v1` REST namespace for portal integrations.

= 3.1.9 - 2026-07-11 =

* New: Added Documentation admin page with quick links to documentation, video tutorials, support resources, and system information.
* Improved: Redesigned Import Wizard interface with a modern onboarding experience, polished progress timeline, improved setup flow, and refined responsive layout.
* Improved: Property Builder usability with responsive improvements, scroll-to-top support, and refined action button styling.
* Improved: Admin Settings responsive navigation and mobile experience.
* Improved: Analytics dashboard responsive layout and overall admin interface consistency.
* Improved: Admin menu organization, highlighting, branding, and navigation consistency across Havenlytics modules.
* Improved: Inquiry management interface with improved Marketing navigation and single inquiry workflow.
* Improved: Import Wizard reliability for Playground and slow hosting environments.
* Fixed: Import Wizard final-step timeout/error by completing the import response before heavy leftover image finishers.
* Fixed: Skip Now / Complete after a failed or interrupted import no longer starts a duplicate import session.
* Fixed: Cancel Import now aborts the in-flight request and stops the server batch before the next property.
* Fixed: Pause/Resume no longer double-schedules batches; Pause waits for the current property, then holds reliably.
* Fixed: Import progress timeline no longer resets completed stages or moves backwards during import.
* Fixed: Duplicate "Additional Information" Builder sections caused by legacy section aliases during upgrades.
* Fixed: Property Builder compatibility with dynamic custom metabox sections and fields.
* Improved: Server refuses new import sessions when the wizard is locked or an interrupted session already exists.
* Improved: Analytics accuracy for dashboard statistics, reporting, and CSV exports.
* Improved: Property view tracking reliability and scheduled analytics maintenance.
* Improved: Playground reliability for Import Wizard race conditions and long-running final batches.
* Maintenance: Builder compatibility, migration safety, analytics reliability, and Import Wizard stability hardening.

= 3.1.8 - 2026-07-10 =

* Fixed: Analytics Today's Views, This Week Views, and This Month Views now use real daily analytics buckets instead of non-resetting counters.
* Fixed: Top Viewed Properties ranking now orders by actual lifetime view totals.
* Fixed: Analytics CSV export Today and This Month columns now match dashboard calculations.
* Improved: Property view tracking ignores preview, admin, autosave, bot, and rapid refresh spam while preserving cookie-based unique visitor logic.
* Improved: Daily analytics cleanup cron is now scheduled automatically on install and existing sites.
* Maintenance: Stability, analytics accuracy, and WordPress.org release hardening.

= 3.1.7 - 2026-07-10 =

* New: Analytics Dashboard in the WordPress admin — charts, tables, date filters, and CSV export.
* New: Agent inquiry property selection to correctly associate inquiries with listings.
* Improved: Self-hosted Inter typography across all Havenlytics admin pages (WordPress.org compliant; no remote fonts).
* Improved: Analytics performance, loading experience, and UI polish.
* Improved: Inquiry emails, admin inquiry management, and upgrade reliability.
* Fixed: Inquiry submission regressions and edge cases discovered during production hardening.

= 3.1.6 - 2026-07-08 =

* New: Analytics Dashboard in the WordPress admin — property statistics, view tracking, inquiry metrics, charts, tables, date filters, and CSV export.
* New: REST API analytics endpoints for overview, charts, tables, and exports.
* Improved: Admin boot loading for Analytics aligned with Settings and Property Builder — smoother initial render and reduced layout shift.
* Improved: Faster analytics data aggregation with optimized queries and sensible caching for dashboard performance.
* Improved: Inquiry analytics integrated with the existing Contact Agent inquiry system.
* Improved: Responsive admin layout for Analytics across desktop, tablet, and mobile widths.
* Improved: Contextual empty states with helpful guidance when data is not yet available.
* Improved: WordPress.org readme, documentation presentation, and developer section.
* Improved: Minor UI polish and stability improvements across the admin experience.
* Maintenance: Performance optimizations, developer experience refinements, and internal compatibility updates.
* Fixed: Resolved WordPress Playground admin styling issue affecting Property Settings and Property Builder pages.
* Improved: Havenlytics admin CSS variables and typography load consistently across supported environments.

= 3.1.5 - 2026-07-05 =

* Improved: Import Wizard department card UI for a cleaner and more balanced appearance.
* Improved: Import Complete screen spacing, typography, and visual hierarchy.
* Improved: Responsive layout across the entire Import Wizard workflow.
* Improved: Professional inquiry form UX with inline validation, loading states, and premium success/error notices.
* Improved: Submit button loading spinner and duplicate submission protection across all Contact Agent forms.
* Improved: Live field validation with accessible error messaging for name, email, phone, and message fields.
* Improved: Success notifications now include inquiry reference IDs and expected response time.
* Improved: Accessibility enhancements including aria-invalid, aria-live regions, and focus management.
* Improved: Minor UI refinements and consistency improvements.
* Fixed: Small responsive issues within the Property Import interface.
* Maintenance: Inquiry system UX polish, frontend validation alignment with server-side rules, and production maintenance.

= 3.1.4 - 2026-06-30 =

* Improved: Responsive layout for the Agents Archive.
* Improved: Responsive layout for the Agency Single → Agency Agents section.
* Improved: Minor UI and responsive improvements.
* Improved: Maintenance release.

= 3.1.3 - 2026-06-29 =

* Improved: WordPress.org documentation and readme structure.
* Improved: Refined plugin short description for the WordPress.org directory.
* Improved: Documentation and version update.
* Improved: Minor maintenance release.

= 3.1.2 - 2026-06-28 =

* New: Property Types now support optional image upload in the admin (same workflow as Property Locations); the frontend keeps image → icon → text fallback.
* New: Demo import assigns stock images to default Property Types only (user-created types are never overwritten).
* New: Fresh installs automatically create Rent, Sale, Commercial, and Let department pages with property grid shortcodes (existing pages with matching slugs are respected).
* New: Demo import populates preset Email, Phone, and Website contact fields on every imported property.
* Improved: Demo import uses 25 unique properties with no duplicate "(Copy)" titles; import quantity is capped to the unique dataset size.
* Improved: Import map location logic respects user intent — address-only edits no longer overwrite per-property demo coordinates when geocoding fills latitude/longitude.
* Improved: Fresh-install and reactivation onboarding redirects to the Import Wizard or Settings based on property count.
* Improved: New installations default Back to Top, Print, and Save Property buttons to off on single property pages (existing saved settings are preserved).
* Improved: Default Property Card layout shows three feature items (Beds, Baths, Receptions) on fresh installs and reset-to-default — reduces awkward wrapping on listing cards.
* Improved: Single property map and video blocks use responsive heights for a more balanced layout on laptops, tablets, and mobile devices.

= 3.1.1 - 2026-06-25 =

* Fixed: Property features checkbox fields no longer lose saved data on property save.
* Fixed: Badge filter SSR now recognizes `in_badge` taxonomy IDs (legacy `badge=` slugs still work).
* Fixed: Restored Search Result settings tab; wired columns, per-page, and sidebar layout settings.
* Fixed: Default gallery carousel no longer duplicates when a builder gallery group exists.
* Fixed: Demo import documents now use bundled plugin PDFs instead of external demo URLs.
* Removed: Frontend preloader system and related settings.
* Removed: Frontend preloader templates, assets, and REST integrations.
* Improved: Reduced frontend assets and plugin complexity.
* Improved: Simplified search and Elementor integrations.
* Improved: General performance and maintainability improvements.
* Improved: Internal code cleanup and deprecated feature removal.
* Improved: Property Video metabox markup and admin field spacing.
* Improved: Cleaner video field UI in property edit screens.
* Improved: Demo property section titles for FAQs, features, and agents.
* Improved: General admin UI polish and maintenance updates.

= 3.0.9 - 2026-06-23 =

* New: Added FAQ, Repeater, Agents, and Property Features support for demo imports.
* New: Added Date and URL field support for property builders and cards.
* Improved: Property Builder, Card Builder, and Search Settings compatibility.
* Improved: Imported property compatibility for map, video, gallery, documents, and agents.
* Improved: Single property rendering and custom field compatibility.
* Improved: Gallery performance, property caching, and frontend rendering.
* Improved: Dynamic color support across frontend templates and components.
* Fixed: Property import, builder, card, and rendering compatibility issues.
* Fixed: Field compatibility in property cards and single property sections.
* Improved: Stability, performance, and developer experience.


= 3.0.8 - 2026-06-21 =

* **Fix:** Property Builder reset no longer shows a success toast when the unified reset endpoint is blocked (HTTP 409). Builder state is left unchanged on failure.
* **Fix:** Builder meta remap now preserves `property_docs` keys (`icon`, `label`, `url`, `documents`, `show_in_sidebar`) and `agents` group keys (`title`, `agents`) when group base IDs change during save, reset, or migration.

= 3.0.7 - 2026-06-18 =

* **Fix:** Property Import Wizard step indicator — Step 1 now shows the active state immediately on page load (no longer requires navigating to Step 2 first).
* **Improvement:** Import Wizard UX redesign — modern horizontal stepper, improved spacing, typography, cards, and mobile responsiveness (UI only; import logic unchanged).
* **New:** Import Wizard URL step routing — each wizard step has a shareable URL (`?step=department`, `location`, `media`, `preferences`, `review`) with browser Back/Forward and refresh-safe step state.
* **New:** Archive Container Width setting — control property archive, taxonomy, and search results page width from Properties Archive Settings (Default, presets, full width, or custom px).

= 3.0.6 - 2026-06-17 =

* **Improvement:** Property Import Wizard reliability — safer batch processing and idempotent retries during demo import.
* **Improvement:** Import resume and retry handling — interrupted imports can continue from saved server state.
* **Improvement:** Import timeout handling — lighter prep batches, image sideload caching, and automatic retry on gateway timeouts (504).
* **Fix:** Removed unnecessary `pb-card-builder` REST requests during import (console HTTP 400 errors).
* **Improvement:** General stability improvements across the import wizard admin experience.
* **Fix:** Property Import Wizard no longer sends DELETE requests to `pb-card-builder` or `pb-dnd-sections` (removed stale client-side reset that returned HTTP 400 without `confirm=true`).
* **Debug:** Optional `pb-card-builder` REST request logging when `HVNLYNAB_DEBUG` and `WP_DEBUG_LOG` are enabled.
* **Debug:** Import wizard fetch probe logs any remaining `pb-card-builder` client calls when `hvnlyImportWizard.debug` is true.
* **New:** Agent Listings Carousel sidebar widget — carousel of properties assigned to a selected agent (or auto-detect the current property agent) on single property pages.
* **New:** HAVENLYTICS widget category in Appearance → Widgets block inserter, grouping all Havenlytics legacy widgets with dedicated admin styling.
* **Improvement:** Agent Listings Carousel uses dedicated `hvnly-agent-listings-*` frontend markup and CSS, decoupled from the single-property Similar Properties carousel for independent long-term maintenance.
* **Improvement:** Agent Listings Carousel cards show compact sidebar layout with image, status, price, title, location, beds/baths/area, and View Details.
* **Fix:** Block widget editor — Havenlytics widgets failed to insert with a block error (`Cannot read properties of null (reading 'hash')`); legacy widget variations now include the required `instance` attribute.
* **Improvement:** Readme — Official Theme section for Havenlytics Realty (optional companion theme), cross-promotion links, and FAQ entries clarifying theme compatibility.
* **New:** Agency single page assigned listings use the existing Similar Luxury Properties carousel (same cards, CSS, and JS; 3 cards desktop, 2 tablet, 1 mobile).
* **New:** Single property title header shows a separate department badge when a department is assigned (alongside the status badge).
* **Improvement:** Agent and agency single pages show all assigned listings on first load (no Load More); agent department tabs still filter client-side.
* **Fix:** Profile listing pages no longer hijack archive property search, Load More, or pagination AJAX.

= 3.0.4 - 2026-06-15 =

* **Fix:** Property Builder blank page when the Property Card Layout tab was active (loading state never cleared).
* **Fix:** Property Builder tab flash on load caused by localStorage restoring the active tab after the wrong tab had already mounted.
* **Fix:** Property Builder preloader now covers the full admin width with a white background during load (admin menu hidden until the builder is ready).
* **Fix:** Property and agent single URLs returning 404 until Permalinks were saved manually — Post name structure is now applied on activation and after import.
* **Fix:** Plugin conflict checker showing duplicate admin notices and a non-working Deactivate button on conflict pages.
* **Improvement:** Settings → Shortcodes tab documents agent and agency shortcodes with copy-ready examples.

= 3.0.3 - 2026-06-15 =

* **New:** HVN: Property Agency Elementor widget — agency archive with search, grid/list views, and pagination (mirrors `[hvnly_property_agencies]` shortcode).
* **New:** `[hvnly_property_agencies]` shortcode and auto-created Agency page (`/property-agencies/`).
* **New:** Agent & agency shortcodes documented in **Settings → Shortcodes** with copy-ready examples and legacy alias fields.
* **New:** Agent availability status — Available, Busy, Away, and Offline with admin control, frontend badges, and contact form gating for offline agents.
* **New:** Demo import agent ecosystem — Pexels CDN stock photos for agent avatars and agency logos; availability status seeded from import demo data.
* **New:** Leaflet map markers — modern animated pins with staggered drop-in; numbered stack badge only when multiple properties share the exact same coordinates (distinct locations always show separate markers).
* **New:** Map troubleshooting — optional debug logging via `?hvnly_map_debug=1` or Havenlytics debug mode; clearer empty-map messages (no results vs. missing coordinates).
* **New:** Global property share popup — one modal for all listing cards; platform list driven from settings; copy-link and auto-close timer.
* **Improvement:** Property map AJAX — expanded coordinate resolution (Property Builder meta, legacy keys, active map pointers, and meta scan fallbacks).
* **Improvement:** Map assets — Leaflet/Google scripts load in the correct order before the map controller; map params localized on the map script handle.
* **Improvement:** Frontend CSS Phase 1 — property card overlay/footer styles moved to a dedicated embed stylesheet; view controls and map container styles scoped to search/archive chrome.
* **Improvement:** Share modal assets load only where property cards show share icons (archive, taxonomy, shortcode, Elementor, agent/agency listings) — not site-wide.
* **Fix:** Map view “Map Unavailable” on archives and Elementor when properties had valid coordinates but meta keys did not match legacy resolver paths.
* **Fix:** Share popup not opening — footer markup and JS enqueued reliably; removed Elementor CSS rule that hid the overlay on live pages.
* **Fix:** Share icon styling missing on agency single property listings (`.hvnly-property-grid-list-share` selectors and stylesheet enqueue on agency taxonomy pages).
* **Fix:** Property card overlay, footer badges, and view controls layout after CSS extraction (4-context selector groups for archive, shortcode, agent, and agency embeds).
* **Fix:** Property Location map tab empty when the map preview field lacked `metaKey`; MapField now renders all map-type preview fields reliably.
* **Fix:** Map view “Request timed out” on slower hosts — map AJAX timeout raised to 30s; builder map groups cached per request; Leaflet registered before admin map field scripts.
* **Fix:** Property Builder preloader white flash — static boot preloader shows immediately and hides when configuration is ready.
* **Fix:** `[hvnly_property_agencies]` shortcode rendered empty pages — registration and asset loading restored.
* **Fix:** Agent metabox parse error that prevented agency taxonomy registration and broke agent custom field metaboxes.
* **New:** Agent Management — agent profiles, property assignment, and redesigned agent widget layouts.
* **New:** HVN: Property Agents Elementor widget — agent archive with search, grid/list views, and pagination.
* **New:** `[hvnly_property_agents]` shortcode and auto-created Agents page (`/property-agents/`).
* **New:** Contact Agent — inquiry modal, email notifications, auto-reply, admin replies, and Inquiries admin with unread badge.
* **New:** Email settings tab — import success notifications and centralized sender/template options.
* **New:** Data Preservation Framework — safer upgrades, migrations, and builder/meta protection on existing sites.
* **Fix:** Linux upgrade and post-update admin redirect issues.
* **Fix:** Property builder and import wizard no longer reset data when properties already exist.
* **Fix:** Contact Agent form submission and inquiry storage reliability.
* **Improvement:** Production-ready performance, security hardening, and WordPress.org code quality compliance.

= 3.0.1 - 2026-06-9 =

* Fix: AJAX Load More repeatedly displaying duplicate property listings.
* Fix: AJAX pagination incorrectly loading page 1 due to stale pagination values.
* Fix: Elementor AJAX pagination now correctly respects requested page numbers.
* Improvement: Pagination state synchronization across archive, shortcode, and Elementor property widgets.
* Improvement: Enhanced pagination reliability for filtered and sorted property searches.

= 3.0.0 - 2026-06-8 =

**New: Elementor Page Builder Integration**

* **New:** Dedicated Elementor widgets for seamless page building
* **New:** HVN: Property Archive widget — complete archive system with filters, grid/list/map views, and AJAX
* **New:** Elementor widget style controls for brand colors and layout customization
* **New:** Automatic widget asset enqueuing for Elementor editor and frontend
* **New:** Widget instance-specific IDs for multiple widget support per page
* **New:** Full preloader system compatibility with Elementor widgets
* **New:** AJAX load more and pagination support within Elementor widgets

**Note:** The mortgage calculator is a WordPress sidebar widget (Appearance → Widgets), not an Elementor widget. Agent and agency Elementor widgets were added in 3.0.2 and 3.0.3.

* **Improvement:** Elementor widgets inherit global Havenlytics settings
* **Improvement:** Responsive design for all Elementor widgets
* **Improvement:** Archive widget supports grid/list/map views with configurable columns
* **Improvement:** Mortgage calculator (sidebar widget) supports advanced options (tax, insurance, HOA, PMI)

**Major release – production-ready architecture update**

Havenlytics 3.0.0 delivers a stable Property Builder and Import foundation, reliable frontend map search, cleaner admin UX, and production-safe architecture across the plugin.

* **New:** Version 2.3.2 migration backfills `_hvnly_field_map` for existing properties with zero data loss
* **New:** Safe first-time Property Import initialization with card builder defaults
* **New:** Unified template helpers (`hvnly_get_template`, `hvnly_get_template_part`) and shared AJAX utilities
* **New:** Property card and single renderers via centralized singleton helpers
* **Improvement:** Property Import now uses a stable 7-section default structure with consistent group field IDs
* **Improvement:** UnifiedFieldGenerator uses persistent master IDs so builder resets no longer orphan property meta
* **Improvement:** Property Builder admin UI with smoother drag-and-drop and better section handling
* **Improvement:** Video, gallery, map, and document group fields save and load reliably across import and edit screens
* **Improvement:** React Property Card Builder integrated with backend DnD API
* **Improvement:** Migration system hardened for backward compatibility and safe upgrades
* **Improvement:** Internal field architecture refactored for stability, scalability, and WordPress coding standards
* **Improvement:** Production debug output removed from frontend/admin JS; PHP logging gated behind debug mode
* **Improvement:** Cache admin menu and plugin action links now respect cache-enabled setting
* **Improvement:** Plugin activation redirects to Property Import and flushes permalinks correctly
* **Improvement:** CPT and taxonomy registration timing fixed for reliable property URLs after activation
* **Improvement:** OpenStreetMap/Leaflet search map reflow, bounds fit, and container cleanup on Map tab load
* **Fix:** Group field identifier (`group_id` / `group_base_id`) mapping across the dynamic builder system
* **Fix:** Metabox data duplication caused by cross-section field name overrides
* **Fix:** DnD builder no longer collapses unique group base IDs to shared master IDs
* **Fix:** Property Import standardized field names, demo video URL fallback, and `_hvnly_field_map` on import
* **Fix:** Video field import, thumbnail sync, gallery hydration, and single image upload in property edit
* **Fix:** Map field cross-section data leakage and invalid `(0,0)` coordinates in map AJAX responses
* **Fix:** Property Documents repeater saving and metabox debug noise in production
* **Fix:** OpenStreetMap markers stacking in the top-left corner on first Map tab view
* **Fix:** Map loading preloader stuck due to shared loading flags and Leaflet init timing
* **Fix:** Duplicate Leaflet zoom controls on property search map

= 2.2.1 (2026-06-4) =
* **Fix:** JavaScript error in property builder functionality.

= 2.2.0 - 2026-05-14 =
* **New:** Multiple map providers - Leaflet (OpenStreetMap) or Google Maps with auto fallback when quota exceeded
* **New:** Google Maps setup UI with API requirements checklist, quota info, and quick enable links
* **New:** Map marker color customization and enhanced map controls (fullscreen, zoom, scroll wheel)
* **New:** Complete Preloader System with Grid/List support and bidirectional view sync
* **New:** 10+ animation styles for property cards, filter sidebar, top search, view controls, and load more
* **New:** Professional map loading animation with pulse, ripple, and progress bar effects
* **New:** Enhanced Google Maps marker with custom pin design and home icon
* **New:** Property Builder reference keys system for reliable group field retrieval
* **New:** Property Import Wizard - Step 2 (Location) and Step 3 (Media) fields are now read-only with demo data pre-filled
* **Improvement:** Settings sync between Search Property and Preloader tabs
* **Improvement:** Professional read-only field styling with lock indicators for better UX
* **Improvement:** Property Import Wizard now stores reference keys for all group fields
* **Fix:** List view preloader now works correctly when Default Property View is set to List
* **Fix:** Google Maps custom markers no longer cut off or misaligned
* **Fix:** Consistent popup styling across both Google Maps and Leaflet
* **Fix:** Map data retrieval from Property Builder auto-generated field names
* **Fix:** Single property map now respects admin map provider setting
* **Fix:** Demo data consistency for address, map, video, and gallery fields during import

= 2.1.5 (2026-04-30) =
* **Fix:** JavaScript error in property search functionality.

= 2.1.4 (2026-04-29) =
* **Fix:** Emergency property search filter currency issue.
* **Improvement:** Replaced browser alerts with custom modal for required field validation.

= 2.1.3 (2026-04-28) =
* **New:** Custom price label per property with toggle switch (numeric/label pricing)
* **New:** Dynamic price label management in Currency Settings (Create, Edit, Delete)
* **Improvement:** Automatic migration system with backup/rollback for existing price fields
* **Improvement:** Replaced direct DB queries with WordPress options API and caching
* **Improvement:** Enhanced accessibility (ARIA labels for gallery, carousel, breadcrumbs)
* **Fix:** Migration compatibility for existing installations

= 2.1.2 (2026-04-22) =
* **Fix:** Emergency property search drag and drop builder settings taxonomy field issue.

= 2.1.1 (2026-04-20) =
* **Added:** 50+ new Property Grid and Property List shortcode variations
* **Added:** Department, price range, bedrooms, bathrooms, location, status, features, tags, and badges filters
* **Added:** Custom ordering, pagination control, results bar positioning, and CSS class options
* **Enhanced:** Shortcodes UI with organized collapsible sections

= 2.1.0 (2026-04-17) =

**New Features:**
* **New:** Drag & Drop Search Builder
* **New:** Editable price, number, and field controls
* **New:** Taxonomy Term Manager
* **New:** Top Search Fields configuration
* **New:** Dynamic Sidebar Filters
* **New:** Field Configuration Modal

**Improvements:**
* **Improved:** Search UX and flexibility
* **Improved:** Property ID field locked
* **Improved:** Empty input validation with error indicators
* **Improved:** Dark mode support for modals

**Fixes:**
* **Fixed:** Default filter fields on first install
* **Fixed:** Reset settings restores defaults
* **Fixed:** Modal positioning issues

= 2.0.6 (2026-04-15) =

**Bug Fixes:**
* **Fix:** Resolved Leaflet map not loading on single property pages due to missing JavaScript dependencies
* **Fix:** Improved coordinate detection for single property maps across multiple meta key locations
* **Fix:** Enhanced map asset enqueuing to only load when property has valid coordinates

**Improvements:**
* **Improvement:** Added multiple fallback methods for retrieving latitude/longitude values in location card template
* **Improvement:** Added debug logging support for map initialization troubleshooting

= 2.0.5 (2026-04-11) =

**New Features:**
* **New:** Appsero SDK integration for optional, consent-based telemetry and usage insights
* **New:** Privacy policy disclosure for data collection compliance
* **New:** Setup Wizard admin notice for new installations with zero properties
* **New:** One-click "Run the Setup Wizard" from WordPress admin dashboard
* **New:** Dismissible welcome notice with skip option

= 2.0.4 (2026-04-08) =

* Updated support section to use official WordPress.org forums
* Minor readme improvements

= 2.0.3 (2026-04-7) =

**Major Features:**
* **New:** Dynamic Settings System with real-time CSS variable generation
* **New:** Global Color & Typography Settings for complete frontend customization
* **New:** Currency Management System with support for 160+ world currencies
* **New:** Advanced Container Width Controls with responsive breakpoint management
* **New:** Professional Color Picker with portal positioning and reset to default functionality
* **New:** Dynamic CSS caching system integrated with existing Cache Manager

**Settings Panel Enhancements:**
* **New:** Redesigned Currency Settings tab with thousand/million/billion formatting options
* **New:** Misc Settings tab for Gutenberg editor, user reviews, favorites, and social sharing
* **New:** Search Property & Search Result dropdown tabs with layout controls
* **New:** Improved Select Dropdown component with auto-positioning (opens up/down based on viewport)
* **New:** Tab Action Buttons component for consistent save/reset experience across all tabs
* **New:** Real-time settings preview with instant CSS variable updates
* **Improvement:** All settings fields now use standardized naming for better organization
* **Improvement:** Dropdown tabs now properly save and reset with parent group data
* **Improvement:** Color Picker now uses React Portal to prevent overflow clipping
* **Fix:** Resolved duplicate toast notifications on save/reset operations
* **Fix:** Fixed dropdown menu cutoff issues in nested containers
* **Fix:** Corrected settings persistence for currency and misc dropdown tabs

**Frontend Enhancements:**
* **New:** Dynamic CSS injection system for real-time style updates
* **New:** Responsive container width system with 8 breakpoint controls (XS to 4K)
* **New:** Currency formatting with proper symbol display for 160+ currencies
* **New:** Price formatting options (comma, dot, space separators)
* **New:** Large number formatting with K, M, B suffixes
* **New:** Price on Call text options (Price on Call, Fixed Price, Guide Price, Offers Over)
* **Improvement:** Enhanced price formatting function with full currency settings integration
* **Improvement:** CSS variables now available throughout frontend for complete design control

= 2.0.2 (2026-03-31) =
* **New:** Enhanced Property Import Wizard with automatic builder reset before import
* **New:** Dynamic field detection for video, gallery, map, and document group fields
* **Fix:** Property video fields now properly populate with YouTube data during import
* **Fix:** Property documents repeater fields now correctly save with icon, label, and URL
* **Fix:** Map location fields now properly import latitude and longitude coordinates
* **Fix:** Gallery fields now correctly save titles and image IDs
* **Improvement:** Added REST API integration for builder reset functionality
* **Improvement:** Better error handling and logging during import process
* **Improvement:** Updated cache admin interface with shortcode cache clearing options

= 2.0.1 (2026-03-25) =
* Updated readme and added FAQ section

= 2.0.0 (2026-03-23) =

**Major Release:** Complete rewrite with new architecture

= 1.0.13 (2025-08-12) =
* Fix: Editor font issue in backend

= 1.0.12 (2025-08-02) =
* Fix: Pagination handling for homepage

= 1.0.11 (2025-07-28) =
* Minor code fixes
* Enhancement: Property price formatting

= 1.0.10 (2025-07-27) =
* Minor Owl Carousel JS bug fix

== Upgrade Notice ==

= 3.3.0 =
Recommended update. Adds agent email verification, secure Workspace authorization, and the Taxonomy Request & Approval workflow with notifications and audit history. Existing agents and data are preserved; required tables are created automatically. Hard-refresh the Workspace after updating.

= 3.2.0 =
Major release: Agent Workspace with authentication, Property Builder–driven CRUD, media, preview, and listing workflow. Recommended for all sites. After updating, hard-refresh the Workspace page so new assets load. No manual database steps required.

= 3.1.9 =
Recommended update. Fixes an Import Wizard timeout on the final step and several import-session edge cases (Skip Now, Cancel, Pause/Resume). Also improves Analytics accuracy following the 3.1.6–3.1.8 releases. No manual action required.


== Support ==

For installation help, troubleshooting, and usage questions, use the [official WordPress.org support forum](https://wordpress.org/support/plugin/havenlytics/).

Before posting, review the [Havenlytics documentation](https://havenlytics.com/documentation/) and include your WordPress, PHP, and Havenlytics versions when relevant. Do not post passwords, API keys, verification links, or personal customer information.

== Contributing ==

Contributions and feedback are welcome:

- Report bugs and request features via the [official support forum](https://wordpress.org/support/plugin/havenlytics/)
- Translate the plugin into your language via [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/havenlytics)
- Write documentation or share feedback via [havenlytics.com/support](https://havenlytics.com/support/)
- Join our community discussions on [Facebook](https://facebook.com/groups/havenlytics/)

== Privacy Policy ==

Havenlytics includes the [Appsero](https://appsero.com/) SDK for optional diagnostic and usage telemetry.

Usage telemetry is disabled by default. If an administrator opts in through the WordPress admin notice, the SDK sends data to `api.appsero.com` to help maintain and improve the plugin. This may include the site URL and name; administrator name and email; IP address; WordPress, PHP, MySQL, theme, plugin, and server versions or configuration; plugin usage metadata; and aggregate user counts. Administrators can opt out at any time.

Review the [Appsero Privacy Policy](https://appsero.com/privacy-policy/) for details.

Havenlytics stores enabled feature data in your WordPress database. This may include property and agent information, user account details, inquiries, taxonomy requests, notification records, and audit events. Some security and audit records may include an IP address and user agent. Site owners are responsible for publishing an appropriate privacy notice, establishing retention practices, and responding to data requests under applicable law.

When Leaflet maps are displayed, map tiles are loaded from the configured tile provider. The default provider is OpenStreetMap (`tile.openstreetmap.org`), which receives normal web request data such as the visitor's IP address and user agent. Review the [OpenStreetMap tile usage policy](https://operations.osmfoundation.org/policies/tiles/) and [OpenStreetMap Foundation privacy policy](https://osmfoundation.org/wiki/Privacy_Policy).

If Google Maps is selected and configured, Havenlytics loads the Google Maps JavaScript API from `maps.googleapis.com`. Google may receive visitor request and device data under the [Google Maps terms](https://maps.google.com/help/terms_maps/) and [Google Privacy Policy](https://policies.google.com/privacy).

If you configure SMTP delivery or other third-party integrations, review those providers' privacy terms and disclose their use to site visitors where required.