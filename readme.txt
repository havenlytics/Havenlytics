=== Havenlytics – Real Estate Listings, Property Search & Agent Workspace ===
Contributors: havenlytics
Donate link: https://havenlytics.com
Tags: real estate, listings, agency, property, agents
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 3.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Build real estate websites with property listings, AJAX search, interactive maps, visual builders, Elementor widgets, and a secure Agent Workspace.

== Description ==

**Havenlytics** is a WordPress real estate plugin for agencies, agents, developers, and property directories. Create and manage property listings with visual builders, AJAX search, interactive maps, galleries, import tools, and built-in analytics.

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
3. Import demo listings or add your own properties.
4. Customize property fields and search filters with the visual builders.
5. Open **Analytics** to review listing and inquiry performance.
6. Optionally install the Havenlytics Realty companion theme.

🎥 Watch: How to Install Havenlytics

[youtube https://www.youtube.com/watch?v=JU6UX3jCrhg]

= Who It's For =

- Real estate agencies, brokerages, and independent agents
- Property listing websites and searchable directories
- Property management and real estate development companies
- WordPress professionals building real estate sites for clients

= Why Havenlytics =

🏠 **Purpose-built for real estate** — Listings, agents, agencies, and inquiries in one system.

🔍 **AJAX search and maps** — Responsive filters with Leaflet or Google Maps, markers, and clustering.

🎨 **Visual builders** — Property forms, search filters, and listing cards without code.

📊 **Analytics** — Listing activity, property views, and inquiry performance in WordPress.

👤 **Agent Workspace** — Frontend portal for agents with favorites, identity verification, and review workflows.

👨‍💻 **Developer-friendly** — REST APIs, 50+ hooks and filters, and theme template overrides.

⚡ **Performance & security** — Caching, capability checks, nonces, rate limiting, and audited admin actions.

= Core Features =

✅ **Analytics Dashboard** — Property statistics, view tracking, inquiry analytics, charts, and CSV export.

✅ **Property Import Wizard** — Guided demo import with media, maps, documents, agents, and custom fields.

✅ **Search Builder** — Drag-and-drop advanced search forms and sidebar filters.

✅ **Property Builder** — Visual property form and card layout editor.

✅ **Interactive Maps** — Leaflet or Google Maps with markers, clustering, and location search.

✅ **Contact Agent & Inquiries** — Inquiry forms, email notifications, and admin inbox.

✅ **Agent & Agency Management** — Profiles, taxonomies, availability badges, and archive pages.

✅ **Agent Workspace** — Create, edit, preview, and submit listings without wp-admin.

✅ **Favorites & Saved Properties** — Save listings from cards, search, and single property pages; guest favorites merge on login.

✅ **Guest Login** — Optional read-only Workspace preview for visitors (disabled by default).

✅ **Identity Verification** — Email verification gate with secure tokens and admin tools.

✅ **Taxonomy Request & Approval** — Agents request missing terms; administrators approve, reject, merge, or delete.

✅ **Elementor Widgets** — Property Archive, Property Agents, and Property Agency.

✅ **Performance & Cache** — Cache dashboard and optimization tools.

✅ **REST API, hooks, template overrides** — Extend settings, builders, Workspace, and frontend templates.

✅ **Translation Ready** — Full i18n support with included `.pot` file.

Also includes: 50+ shortcodes; grid, list, and map layouts; property view counter; 160+ currencies; mortgage calculator; media galleries, videos, and documents; responsive single-property templates.

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

== External services ==

Havenlytics does not contact any external service by default. The services below are used only when you explicitly enable the corresponding feature.

**Appsero (optional usage telemetry)**
Used to help maintain and improve the plugin. Disabled until an administrator opts in through the admin notice. When enabled, data is sent to `api.appsero.com` and may include the site URL and name; administrator name and email; IP address; WordPress, PHP, MySQL, theme, plugin, and server versions; plugin usage metadata; and aggregate user counts. You can opt out at any time.
Terms: https://appsero.com/terms-conditions/ — Privacy: https://appsero.com/privacy-policy/

**OpenStreetMap tiles (default map provider)**
Used to display map tiles when a map is shown on your site. Tiles are requested from `tile.openstreetmap.org`, which receives normal web request data such as the visitor's IP address and user agent.
Tile usage policy: https://operations.osmfoundation.org/policies/tiles/ — Privacy: https://osmfoundation.org/wiki/Privacy_Policy

**Google Maps (optional map provider)**
Used only if you select Google Maps and supply an API key. The Maps JavaScript API is loaded from `maps.googleapis.com`, and Google may receive visitor request and device data.
Terms: https://maps.google.com/help/terms_maps/ — Privacy: https://policies.google.com/privacy

No other external service is contacted. Favorites, Saved Properties, Guest Favorites, Guest Login, the Agent Workspace, and all listing data are stored in your own WordPress database.

== Privacy Policy ==

Havenlytics includes the [Appsero](https://appsero.com/) SDK for optional diagnostic and usage telemetry.

Usage telemetry is disabled by default. If an administrator opts in through the WordPress admin notice, the SDK sends data to `api.appsero.com` to help maintain and improve the plugin. This may include the site URL and name; administrator name and email; IP address; WordPress, PHP, MySQL, theme, plugin, and server versions or configuration; plugin usage metadata; and aggregate user counts. Administrators can opt out at any time.

Review the [Appsero Privacy Policy](https://appsero.com/privacy-policy/) for details.

Havenlytics stores enabled feature data in your WordPress database. This may include property and agent information, user account details, inquiries, taxonomy requests, notification records, and audit events. Some security and audit records may include an IP address and user agent. Site owners are responsible for publishing an appropriate privacy notice, establishing retention practices, and responding to data requests under applicable law.

When Leaflet maps are displayed, map tiles are loaded from the configured tile provider. The default provider is OpenStreetMap (`tile.openstreetmap.org`), which receives normal web request data such as the visitor's IP address and user agent. Review the [OpenStreetMap tile usage policy](https://operations.osmfoundation.org/policies/tiles/) and [OpenStreetMap Foundation privacy policy](https://osmfoundation.org/wiki/Privacy_Policy).

If Google Maps is selected and configured, Havenlytics loads the Google Maps JavaScript API from `maps.googleapis.com`. Google may receive visitor request and device data under the [Google Maps terms](https://maps.google.com/help/terms_maps/) and [Google Privacy Policy](https://policies.google.com/privacy).

If you configure SMTP delivery or other third-party integrations, review those providers' privacy terms and disclose their use to site visitors where required.