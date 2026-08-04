=== Havenlytics – Real Estate Listings, Property Search & Agent Workspace ===
Contributors: havenlytics
Donate link: https://havenlytics.com
Tags: real estate, listings, agency, property, agents
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 3.7.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Real estate plugin with AJAX search, Migration Engine, CSV import/export, Gutenberg, Elementor, Agent Workspace, and Analytics.

== Description ==

**Havenlytics** is a WordPress real estate plugin for agencies, agents, developers, and property directories. Create and manage property listings with the Havenlytics Migration Engine, CSV Import & Export, visual builders, AJAX search, interactive maps, galleries, and built-in analytics.

Agents manage listings from a secure frontend **Agent Workspace** without entering wp-admin. Administrators retain control over registration, identity verification, listing review, taxonomy requests, and publishing workflows.

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
3. Choose one of the following:
   * Import demo content
   * Import property listings from CSV
   * Migrate an existing Havenlytics website
   * Create your own property listings
4. Customize property fields, search filters, and listing layouts using the visual builders.
5. Open **Analytics** to monitor property listings, inquiries, and website performance.
6. (Optional) Install the free **Havenlytics Realty** companion theme for a complete demo-ready experience.
7. Visit **Havenlytics → Documentation** or **https://havenlytics.com/documentation/** for detailed guides, tutorials, and videos.

🎥 **Video Tutorial: How to Install Havenlytics**

[youtube https://www.youtube.com/watch?v=JU6UX3jCrhg]

🎥 **Video Tutorial: Easily Move Your Website to a New Domain (Migration Engine)**

[youtube https://www.youtube.com/watch?v=KQW61krcAi4]

= Who It's For =

- Real estate agencies, brokerages, and independent agents
- Property listing websites and searchable directories
- Property management and real estate development companies
- WordPress professionals building real estate sites for clients

= Why Havenlytics =

🏠 **Purpose-built for real estate** — Listings, agents, agencies, inquiries, and workflows in one complete system.

📦 **Migration Engine (HPTP)** — Securely migrate complete Havenlytics websites with properties, agents, agencies, media, taxonomies, builders, and settings preserved.

📄 **CSV Import & Export** — Import or export property listings using CSV with intelligent column mapping, validation, reusable mapping profiles, and support for popular real estate platforms.

🔍 **AJAX Search & Interactive Maps** — Responsive search filters with Leaflet or Google Maps, clustering, and location search.

🎨 **Visual Builders** — Build property forms, search filters, and listing cards without writing code.

📊 **Analytics Dashboard** — Track listings, inquiries, property views, and performance directly inside WordPress.

👤 **Agent Workspace** — Secure frontend dashboard for agents with listings, favorites, identity verification, and workflow management.

🧱 **Gutenberg & Elementor** — Native Gutenberg blocks, Elementor widgets, shortcodes, and template overrides using one shared rendering engine.

🌍 **Multilingual Support** — Fully translation-ready with WordPress language packs. Includes complete Russian localization and supports any WordPress language.

👨‍💻 **Developer Friendly** — REST APIs, 50+ hooks & filters, template overrides, and modular architecture.

⚡ **Performance & Security** — Optimized caching, capability checks, nonces, rate limiting, and production-ready architecture.

= Core Features =

✅ **Migration Engine (HPTP)** — Secure Havenlytics-to-Havenlytics website migration with migration packages, progress tracking, resume support, duplicate handling, and builder preservation.

✅ **CSV Import & Export** — Import and export property listings using CSV with intelligent field mapping, reusable mapping profiles, validation, progress tracking, and platform presets.

✅ **Property Import Wizard (Demo Content)** — Quickly install demo properties, media, maps, documents, agents, and sample content for new websites.

✅ **Gutenberg Blocks** — Native WordPress blocks for Property Archive, Search, Inquiry, Dashboard, Authentication, Saved Properties, Agents, Agency, and more.

✅ **Analytics Dashboard** — Property statistics, inquiry analytics, charts, property views, and CSV reporting.

✅ **Search Builder** — Drag-and-drop advanced search forms and sidebar filters.

✅ **Property Builder** — Visual property form and listing card builder.

✅ **Interactive Maps** — Leaflet or Google Maps with markers, clustering, and location search.

✅ **Contact Agent & Inquiries** — Inquiry forms, email notifications, and admin inbox.

✅ **Agent & Agency Management** — Profiles, taxonomies, badges, archive pages, and frontend management.

✅ **Agent Workspace** — Frontend property management without wp-admin.

✅ **Favorites & Saved Properties** — Persistent saved listings with guest-to-account merging.

✅ **Identity Verification** — Secure email verification and protected Workspace access.

✅ **Taxonomy Requests** — Agent-submitted taxonomy requests with administrator approval workflow.

✅ **Elementor Widgets** — Property Archive, Agents, Agency, and dynamic frontend layouts.

✅ **Performance & Cache** — Cache dashboard and optimization tools.

✅ **REST API & Template Overrides** — Developer APIs, hooks, filters, and template customization.

✅ **Multilingual Support** — Translation-ready with WordPress language packs, complete Russian localization, and support for any WordPress language.

Also includes 50+ shortcodes, grid/list/map layouts, mortgage calculator, property documents, media galleries, videos, responsive templates, 160+ currencies, and extensive developer hooks.

= Agent Workspace =

A frontend portal where approved agents (and administrators) manage listings without wp-admin. Includes login and registration, Property Builder–driven forms, media, preview, submit-for-review workflow, favorites, Saved Properties, and cookie-authenticated `hvnly/v1` REST endpoints. Choose open or administrator-approved registration.

= Identity Verification & Taxonomy Requests =

Agents must verify their email before Workspace access. Tokens are single-use and HMAC-protected; changing a verified email requires re-verification. Administrators can manually verify, resend, or emergency-disable verification.

Agents can request missing property taxonomy terms from the Workspace. Administrators review requests under **Havenlytics → Taxonomy Requests** with approve, reject, merge, delete, notifications, and audit history.

= Analytics, Builders & Email =

Use Analytics for property, agent, agency, inquiry, and view statistics with date filters and CSV export — no Google Analytics required.

Search Builder and Property Builder configure search forms, sidebar filters, and property fields with drag-and-drop controls. Configure email under **Havenlytics → Settings → Email**; Contact Agent messages under **Settings → Contact Agent**.

🎥 Video tutorial: Build Property Search Forms with Search Builder

[youtube https://www.youtube.com/watch?v=d2mJra8RYM8]

= Elementor, Widgets & Pages =

Requires Elementor Free or Pro. Widgets in the **Havenlytics** category: **HVN: Property Archive**, **HVN: Property Agents**, and **HVN: Property Agency** — AJAX archives with search, grid/list/map views, and pagination.

Sidebar widgets (**Appearance → Widgets**): Featured Properties, Property Agent, Mortgage Calculator, Related Properties, and Agent Listings Carousel.

On activation, Havenlytics creates Property Grid, Lists, Search, Agents, and Agency pages when those slugs are free. Fresh installs also get Rent, Sale, Commercial, and Let department pages.

= Shortcodes =

Display listings with `[hvnly_property_grid]`, `[hvnly_property_lists]`, `[hvnly_property_search]`, `[hvnly_property_agents]`, and `[hvnly_property_agencies]`. Copy-ready examples and 50+ variations are under **Havenlytics → Settings → Shortcodes**.

🎥 Video tutorial: Display Property Content with Shortcodes

[youtube https://www.youtube.com/watch?v=DJ2IYECJ_YA]

Live demos: [property grid](https://demo.havenlytics.com/property-grid/), [property lists](https://demo.havenlytics.com/property-lists/), [property search](https://demo.havenlytics.com/property-search/).

= Multilingual Support =

Havenlytics is fully **internationalized** and follows WordPress localization standards.

Features include:

* Complete **Russian** translation included
* Translation-ready for any WordPress-supported language
* Compatible with WordPress language packs
* Supports translate.wordpress.org language packs
* RTL language compatible
* Uses WordPress i18n APIs
* Developer-friendly localization for custom extensions

= Included Translations =

* 🇷🇺 Russian (ru_RU)


📘 Full documentation: [https://havenlytics.com/documentation/](https://havenlytics.com/documentation/)


== Frequently Asked Questions ==

= Which languages does Havenlytics support? =

Havenlytics is fully translation-ready and works with WordPress language packs.

The plugin includes complete Russian localization and supports any WordPress language through translate.wordpress.org or custom language files.

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

= How do Favorites work? =

Every property card, search result, and single property page has a favorite button. Signed-in users have their saved properties stored on their account and available on any device, listed under **Saved Properties** in the Agent Workspace. Administrators use the same Workspace as agents.

= Can visitors save properties without an account? =

Yes. Visitors who are not signed in can still save properties — their selections are kept in their own browser. The first time they sign in, those saves merge into their account automatically, so nothing is lost. Signing in is encouraged but never required to browse or save.

= What is Guest Login? =

Guest Login is an optional, read-only preview of the Agent Workspace for visitors evaluating your agency. Guests can browse the dashboard, properties, saved properties, and profile, but cannot create, edit, delete, upload, or change settings. No WordPress account is created and the session expires on its own. It is disabled by default — enable it under **Havenlytics → Settings → Agent Workspace**.

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

= Does Havenlytics support the WordPress Block Editor (Gutenberg)? =

Yes. Havenlytics includes native Gutenberg blocks for building real estate pages with the WordPress Block Editor. Blocks include property archives, property search, property inquiry forms, authentication, agent and agency listings, dashboards, saved properties, and other real estate components depending on your installed version.

All blocks are server-rendered, include live editor previews, and reuse the same rendering engine, templates, queries, and caching used by the plugin's Elementor widgets and shortcodes.

= Do I need Elementor to use Havenlytics? =

No. Elementor is completely optional.

You can build your real estate website using native Gutenberg blocks, Havenlytics shortcodes, or Elementor widgets. All three use the same backend rendering system, ensuring consistent frontend output regardless of which builder you choose.

= Are the Gutenberg blocks compatible with any WordPress theme? =

Yes. Havenlytics Gutenberg blocks work with any properly coded WordPress theme. They are dynamic (server-rendered), responsive, and automatically reuse the plugin's existing templates, query engine, caching, and REST APIs.

No additional configuration or migration is required.

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

= 3.7.3 - 2026-08-04 =

**New**

* Added secure frontend Agent Workspace for property management without wp-admin.
* Added email identity verification for Agent Workspace accounts.
* Added Taxonomy Request & Approval workflow for property departments, types, features, locations, tags, statuses, and badges.
* Added a new Documentation Center with onboarding guides and video tutorials.
* Added Migration Engine and installation video tutorials to improve onboarding.

**Improved**

* Standardized responsive container width defaults for a more consistent frontend layout.
* Simplified the frontend container width system with a single runtime source.
* Redesigned the Dynamic Property Metabox with a responsive two-column layout for improved editing.
* Modernized the Property Gallery media manager with a cleaner and more intuitive interface.
* Improved the Property Video editor with a modern layout and better media management.
* Refined the Property Builder editing experience and responsive layout.
* Enhanced Agent Workspace interface, property management workflow, favorites reliability, and overall usability.
* Updated plugin branding, documentation, and onboarding resources.
* Improved overall performance, compatibility, accessibility, and code quality.

**Fixed**

* Fixed favorites synchronization issues between the Agent Workspace, guest sessions, and saved properties.
* Fixed various UI, styling, spacing, responsive layout, and compatibility issues.
* Fixed minor bugs and improved overall stability.

= 3.7.2 - 2026-07-29 =

**Improved**

* **Onboarding Wizard — premium first-run experience.** Complete presentation redesign of the "Get Started" setup wizard (Properties → Get Started). Every step, control, and state was refined for a professional first impression. The onboarding flow, step order, settings, REST endpoints, import engine, nonces, and capability checks are unchanged.
* **Custom icon set** — every emoji in the wizard was replaced with an inline SVG icon set that renders identically on Windows, macOS, and Linux.
* **Typography & design tokens** — the wizard now uses the self-hosted Inter typeface already bundled with Havenlytics, plus a consistent spacing, type, radius, and elevation scale in place of ad-hoc values.
* **Progress navigation** — a sticky top bar with a "Step N of 5" counter and completion rail; completed steps in the stepper are now clickable for going back.
* **Welcome screen** — new hero with estimated completion time, a "what you'll set up" card grid, and a factual strip of the capabilities already included in your install (Agent Workspace, Migration Engine, CSV Import & Export, Property Builder, Search Builder, Gutenberg Blocks, Elementor Widgets, Analytics, Identity Verification, multilingual support).
* **Feature spotlights & contextual help** — the Location, Currency, Workspace, and Content steps explain which Havenlytics capability they feed and link straight to the bundled Documentation screen; inline tips were added where a choice needs context.
* **Loading state** — the demo import now shows a determinate progress ring, a live "X of Y properties created" counter, a check-marked stage list, and rotating guidance instead of a plain spinner.
* **Error state** — a failed import now reports how many properties were created and kept, with a clearer "Resume import" action.
* **Completion screen** — the finish step opens with a summary of everything you configured, followed by four recommended next actions including Documentation.
* **Layout & copywriting** — per-step panel widths, sticky preview columns on the Location and Currency steps, a redesigned footer, and rewritten headings, hints, and button labels throughout.

**Fixed**

* Added a **Back** button to the demo-content step, which previously had no way to return to the Workspace step.
* Settings that fail to save now surface a dismissible warning instead of failing silently.
* Collapsed option panels (Google Maps API key, decimal separator, agent registration mode) are no longer reachable by keyboard while hidden.
* Checkbox and radio option cards are now explicitly associated with their inputs, giving each an accessible name.

**Localization**

* All new onboarding strings use the existing `havenlytics` text domain and translation pipeline.
* The Russian language pack was regenerated. The setup wizard is fully translated (204/204 strings).

= 3.7.1 - 2026-07-28 =

**Fixed**

* Shortened the plugin short description in `readme.txt` to comply with the WordPress.org parser's 150-character limit.
* Improved WordPress.org readme compatibility and repository metadata.
* Maintenance release with no functional changes.

= 3.7.0 - 2026-07-28 =

**New**

* **Complete Russian Language Pack** — Added full Russian localization across the plugin, including frontend templates, Gutenberg blocks, Elementor widgets, Agent Workspace, Analytics, Property Builder, Search Builder, Settings, Migration Engine, CSV Import & Export, email templates, and WordPress admin interfaces.

**Improved**

* **Single Property Page** — Complete frontend redesign with a premium hero header, improved gallery, cleaner property overview, redesigned Contact Agent sidebar, and refined visual hierarchy while maintaining full backward compatibility.
* **Hero Header** — Added a premium featured-image hero with a gradient overlay, breadcrumb navigation, badges, property title, pricing, and Save action. Property details now appear in a dedicated overview card below the header.
* **Mobile Experience** — Introduced a mobile-first property header with an optimized featured image, improved typography, responsive information cards, and better spacing on smaller screens.
* **Property Overview** — Redesigned property specifications into responsive, easy-to-read layouts for desktop, tablet, and mobile devices.
* **Contact Agent Sidebar** — Redesigned with a cleaner layout, improved spacing, quick contact actions, WhatsApp support, inquiry form enhancements, and social links.
* **React Admin Localization** — Improved translation loading across Analytics, Property Builder, Search Builder, Settings, Migration Engine, CSV Import & Export, and other React-powered admin interfaces.
* **Internationalization (i18n)** — Enhanced multilingual support, translation coverage, and localization consistency throughout the plugin.
* **Accessibility & Responsive Design** — Improved keyboard navigation, reduced-motion support, responsive layouts (320px–1440px), refined spacing, and replaced legacy Font Awesome header icons with the built-in SVG icon system.

**Fixed**

* Fixed property header title wrapping on narrow screen sizes.
* Removed remaining inline styles from the Location and Property Documents templates.
* Fixed legacy spacing inconsistencies in the Contact Agent sidebar.
* Improved responsive behavior across single property layouts.

**Compatibility**

* Presentation-only update. No changes to Property Builder, Dynamic Metabox, custom fields, database structure, migrations, REST API, CSV Import & Export, Migration Engine, template loading, hooks, filters, or existing integrations.
* Existing theme template overrides remain fully compatible.
* The redesigned frontend loads as a separate stylesheet on single property pages and can be disabled by dequeuing its stylesheet handle.
* The Similar Properties section continues to work without changes.
* No database migrations or manual upgrade steps are required.

= 3.6.0 - 2026-07-26 =

**New**

* **Migration Engine (HPTP)** — Securely migrate complete Havenlytics websites using Havenlytics Property Transfer Packages (.zip), preserving properties, agents, agencies, taxonomies, builder layouts, media, documents, videos, maps, settings, and supported relationships.
* Replaced the previous Import / Export tab with the new Migration Engine for Havenlytics-to-Havenlytics website transfers.
* **CSV Import & Export** — Universal CSV import and export system for property listings with spreadsheet support.
* Interactive CSV Import Wizard with upload, intelligent column mapping, validation, progress tracking, completion summaries, and reusable mapping profiles.
* CSV source presets for Generic CSV, Havenlytics, Property Hive, Easy Property Listings (EPL), Estatik, Houzez, RealHomes, Essential Real Estate, Directorist, GeoDirectory, HivePress, Classified Listing, aDirectory, WP Residence, MyHome, and custom CRM or spreadsheet exports.

**Improved**

* Improved migration reliability for agents, agencies, media, galleries, documents, videos, maps, builder data, taxonomies, and related content.
* Added batched migration with progress tracking, pause/resume, cancellation, duplicate handling, package validation, and builder keep/replace options.
* Added automatic CSV field detection with configurable property field mapping.
* Improved migration and CSV import workflows with clearer validation, loading indicators, progress feedback, and completion reporting.
* Strengthened upload validation, temporary workspace cleanup, structured AJAX error handling, and migration security.
* Automatically creates and reuses taxonomy terms during CSV import where supported.

**Compatibility**

* Existing Havenlytics Migration packages remain fully compatible.
* No breaking changes to existing listings, Gutenberg blocks, Elementor widgets, shortcodes, templates, REST APIs, or Agent Workspace.
* Migration Engine and CSV Import / Export require the `manage_options` capability.

= 3.5.0 - 2026-07-22 =

**New**

* **Gutenberg Blocks** — a complete Havenlytics block suite under the "Havenlytics" category in the block inserter. Includes:
  * HVN: Property Archive
  * HVN: Property Search
  * HVN: Property Inquiry Form
  * HVN: Featured Properties
  * HVN: Property Carousel
  * HVN: Property Map
  * HVN: Agents
  * HVN: Agency
  * HVN: Authentication
  * HVN: Dashboard
  * HVN: Saved Properties
* Every block is dynamic (server-rendered), provides a live editor preview and inspector controls, and reuses the existing Havenlytics rendering, query, template, and caching systems so frontend output stays aligned with the equivalent widgets and shortcodes.

**Improved**

* Block assets load only when a block is present on the page.
* Shared property and agent archive query helpers keep listings consistent across templates, widgets, and blocks.
* Editor previews, REST-backed property/agent pickers, and block registration stability improvements for the Gutenberg workflow.
* Compatibility metadata aligned with Block API version 3 (requires WordPress 6.3+).

**Compatibility**

* Elementor widgets, shortcodes, templates, REST APIs, and existing frontend functionality remain fully compatible. No migration is required.

= 3.4.0 - 2026-07-21 =

* New: Agent Favorites — save properties from archive cards, search results, and single property pages. Favorites persist across page loads and across devices for signed-in users.
* New: Guest Favorites — visitors can save properties before signing in, with favorites automatically merging into their account after their first login.
* New: Saved Properties page in the Agent Workspace with pagination, sorting, one-click removal, responsive layouts, and empty-state support.
* New: Saved Properties count badge in the Agent Workspace sidebar with instant updates for both signed-in users and guest sessions.
* New: Guest Login — optional read-only preview of the Agent Workspace for visitors. Browsing is allowed while property management features remain disabled.
* New: Administrators can now use the Agent Workspace with full property management while retaining all WordPress capabilities and wp-admin access.
* New: Favorites REST API endpoints under `hvnly/v1` with nonce validation and secure session-based ownership.
* New: Role-aware Workspace experience — administrators retain the WordPress admin toolbar while agents continue using a distraction-free Workspace.

* Improved: The Saved Properties page now displays listing status, assigned agent with avatar and profile link, and the saved date.
* Improved: Favorite heart icons remain synchronized across archive pages, search results, single property pages, the Agent Workspace, Saved Properties, and guest sessions.
* Improved: Saved favorite state is rendered server-side for accurate first-page rendering.
* Improved: Favorite buttons now use a delegated event handler for better reliability after AJAX updates.
* Improved: Saved Properties queries are optimized to avoid N+1 database queries and scale efficiently for large sites.
* Improved: The "Allow users to save properties" setting now fully controls favorite functionality on single property pages.
* Improved: Taxonomy Requests no longer send administrator emails for every submission. A pending-count badge in the WordPress admin replaces those notifications while Inquiry and Contact Agent emails remain unchanged.
* Improved: Properties → Settings now uses a full-width layout on laptop screens (768–1399px), while large desktops (1400px+) retain the two-column layout.

* Security: Guest sessions never create WordPress user accounts, expire automatically, and include rate limiting.
* Security: Only published properties can be added to favorites, and favorite requests are fully isolated to the current user or guest session.

* Maintenance: Added the `hvnly_favorites` database table for persistent favorites. Existing site data remains fully preserved with no breaking database changes.

= 3.3.1 - 2026-07-19 =

* Improved: Updated plugin branding and standardized the official plugin name.
* New: Added a plugin installation video tutorial to the documentation and README for easier onboarding.
* Improved: Documentation and README enhancements.
* Maintenance: Version update for the WordPress.org release.

= 3.3.0 - 2026-07-19 =

* New: Agent Workspace — frontend portal for agents to manage properties without wp-admin.
* New: Agent Identity Verification with secure email verification and protected Workspace access.
* New: Taxonomy Request & Approval workflow with moderation, audit logs, and notifications.
* New: Property Builder–powered Workspace forms, media, preview, and submit-for-review workflow.
* Improved: Security, REST API protection, and developer extensibility (`hvnly/v1`).
* Security: Plugin information REST endpoint now requires `manage_options`.
* Maintenance: Production release packaging improvements.

= 3.2.0 - 2026-07-15 =

* New: Agent Workspace with authentication, property CRUD, media, preview, and listing workflow.
* See CHANGELOG.md for the full 3.2.0 details.

= 3.1.9 - 2026-07-11 =

* New: Documentation admin page.
* Improved: Import Wizard, Property Builder, Analytics, and admin UI.
* Fixed: Import Wizard final-step timeout and several import-session edge cases.
* See CHANGELOG.md for older releases (3.1.8 and earlier).

For earlier versions, see the full history in CHANGELOG.md in the plugin package.
== Upgrade Notice ==

= 3.7.3 =
Improves cache system reliability, statistics accuracy, and the Cache Management dashboard UI. No breaking changes to builders, search, REST, AJAX, Elementor, Gutenberg, or the database.

= 3.7.2 =
Premium redesign of the onboarding wizard, plus a Back button, save-failure feedback, and keyboard fixes. Presentation only — no changes to the setup flow, settings, REST endpoints, import engine, database, or capability checks. Russian language pack regenerated.

= 3.7.1 =
Maintenance release that improves WordPress.org readme compatibility by shortening the plugin short description. No functional changes.

= 3.7.0 =
Single Property presentation redesign (hero, gallery, overview, agent sidebar) plus mobile and accessibility polish. No Builder, field, REST, database, hook, or migration changes. Hard-refresh a property page after updating.

= 3.6.0 =
Adds the Migration Engine (HPTP) and universal CSV Import / Export for listings. Existing migration packages stay compatible; no breaking changes to listings, blocks, Elementor, shortcodes, templates, REST or the Agent Workspace. Requires the manage_options capability.

= 3.5.0 =
Adds native Gutenberg blocks (archive, search, inquiry, auth, dashboard, and more) that reuse existing rendering. Elementor, shortcodes, templates, and REST stay compatible. No migration required. Install the packaged release so the block bundle is present.

= 3.4.0 =
Recommended update. Adds Agent Favorites, Saved Properties, guest favorites that merge on login, and optional Guest Login. Admins can use the Agent Workspace (no wp-admin redirect). Favorites table created automatically. Hard-refresh the Workspace after updating.

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

Havenlytics includes the Appsero SDK for optional usage telemetry.
Telemetry is **disabled by default** and is only enabled if an administrator opts in.
Privacy Policy: https://appsero.com/privacy-policy/
All Havenlytics data is stored in your own WordPress database. Contact Agent inquiries are also stored locally. Maps may use OpenStreetMap, Carto, or Google Maps (when configured). Imported CSV files may download remote media you provide.

Uninstall removes plugin options, custom tables, cron events, and Appsero telemetry data. Property posts and Media Library files are retained unless deleted manually.

== Credits ==

This plugin bundles:

- Font Awesome Free (CC BY 4.0, SIL OFL 1.1, MIT)
- Leaflet, Leaflet.markercluster, and leaflet.fullscreen (BSD-2-Clause/MIT)
- Inter font (SIL OFL 1.1)
- Appsero Client SDK (MIT)