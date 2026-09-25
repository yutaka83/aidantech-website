# portal-lkim migration toolkit

Scripts that built **portal-lkim** — a Joomla 6 rebuild of
[lkim.gov.my](https://www.lkim.gov.my) (Lembaga Kemajuan Ikan Malaysia).

The source site runs WordPress + Elementor + Max Mega Menu behind LiteSpeed and
exposes an unauthenticated REST API, so the migration harvests structured JSON
rather than scraping HTML, then unwraps Elementor's markup into clean article
bodies.

Everything here is **re-runnable and idempotent**. Imported items carry their
source id in the article metadata; generated menus and modules carry a
`lkim:<key>` note. Re-running updates in place instead of duplicating.

---

## Target

| | |
|---|---|
| Docroot | `C:\Users\hiday\Herd\portal-lkim` |
| URL | `https://portal-lkim.test` (Herd) |
| Joomla | 6.1.3 |
| PHP | 8.4 (`C:\Users\hiday\.config\herd\bin\php84\php.exe`) |
| Database | MySQL 8.4 @ `127.0.0.1:3306`, `portal_lkim`, prefix `lkim_` |
| Admin | `https://portal-lkim.test/administrator` — user `lkimadmin` |
| Languages | Bahasa Melayu (default, unprefixed) · English at `/en/` via Falang |

`php` is not on PATH by default. Prefix commands with:

```bash
export PATH="/c/Users/hiday/.config/herd/bin/php84:$PATH"
```

---

## Pipeline

Run in this order. Each step prints a summary and writes CSV reports to
`reports/`.

| # | Script | What it does |
|---|---|---|
| 1 | `harvest.php` | Pulls pages, posts, media, categories and tags from the source REST API into `data/*.jsonl`. |
| 1b | `harvest-media.php` | Re-fetches the media collection ordered by id. WordPress pages media by date, and equal timestamps make that ordering unstable; this pass is deterministic. |
| 2 | `scrape-menu.php [path] [out]` | Reads the rendered Max Mega Menu out of the HTML — the WP menus endpoint needs auth. Run twice: `/ menu.json` and `/en/ menu-en.json`. |
| 3 | `build-map.php` | Resolves every source item to a Joomla category, a language, and its menu position. Writes `data/resolved.json`. |
| 4 | `plan-media.php` | Decides where each file belongs in `images/`, filed by the category of the article that references it. Writes `data/media-plan.json`. |
| 5 | `fetch-media.php` | Downloads the planned files straight into the docroot. Resumable via `data/media-manifest.jsonl`. |
| 6 | `import.php` | Creates the category tree and imports Malay articles. `--lang=en` imports the standalone English pages, `--dry-run` reports only, `--limit=N` for a smoke test. |
| 7 | `build-menus.php` | Rebuilds `mainmenu`, `footermenu` and `hiddenmenu` from the captured navigation. |
| 8 | `build-modules.php` | Lays out navigation, homepage bands and footer columns using core modules. |
| 9 | `configure-site.php` | Default language, article display options, the Home menu item, retires stock modules. |
| 10 | `configure-falang.php` | Content languages, plugin ordering, the language switcher. |
| 11 | `pair-languages.php` + `pair-languages-reverse.php` + `merge-menu-pairs.php` | Works out which English page is which Malay page's translation. |
| 12 | `import-translations.php` | Writes the English content into Falang as translations. |
| 13 | `translate-menus.php` | Translates menu labels and module titles. |
| 14 | `feature-articles.php` | Features recent news so the homepage component area is not empty. |
| 15 | `relink.php` | Rewrites internal links from source permalinks to Joomla routes. `--dry-run` supported. |
| 16 | `build-redirects.php` | 301s every old permalink at its new route (2,268 records). |
| 17 | `build-sitemap.php` | Writes `sitemap.xml` with hreflang alternates, points `robots.txt` at it, and regenerates the Peta Laman page from the live menu tree in both languages. `--base=` for the production hostname. |
| 18 | `harden.php` | Security headers, plugin posture, global configuration. `--production` switches on caching, HSTS, forced SSL and indexing. |
| 19 | `build-content-checklist.php` | Content checklist as an .xlsx, in the JKM sheet's format. Statuses are resolved against the live portal rather than typed by hand. |
| 20 | `qa-crawl.php` · `qa-media.php` · `qa-sitemap.php` | Crawl every route; check every media reference exists; check every sitemap URL resolves. |

> **`relink.php` must run last among the content steps.** `import.php` rebuilds
> article bodies from the harvested source, so re-running an import — even just
> to regenerate a report — silently undoes the link rewriting. Menu rebuilds
> change routes and invalidate it too. If in doubt, run steps 15–17 again;
> all three are cheap and idempotent.

`template/`, `template2/` and `template3/` hold the three site templates:
`tpl_lkim` (the first pass), `tpl_lkim2` (the 2026 design, currently the
default style) and `tpl_lkim3` (the LKIM-3 design, from the
`ui-mockup.github.io/LKIM-3` mockup). `php build-package.php template3
tpl_lkim3-1.0.0.zip` rebuilds the third one, and `php cli/joomla.php
extension:install --path=<zip>` installs it.

> The CLI installer refuses a template whose folder already exists in
> `templates/` — delete `templates/<name>` and `media/templates/site/<name>`
> first, or it reports only "Unable to install extension".

`tpl_lkim3` is styled entirely from the template style, in the way Gantry and
Helix templates are, because agency servers routinely forbid writing to files
under the document root. `media/.../lkim3.css` defines every colour, font,
radius, width and spacing value as a CSS custom property, and `index.php`
writes a second `:root` block into the page from the style parameters. That
block loads after the stylesheet, so it wins on order — Colours, Typography and
Layout on the style can recolour and re-space the whole portal without touching
a file. An empty parameter is skipped, so the stylesheet's own value stands,
and the manifest defaults are kept identical to the stylesheet (there is a
check for this in the commit that introduced them) so saving a style never
changes the design by itself. Token values containing `;`, `{`, `}` or a
comment are dropped rather than written into the declaration block.

The page body between the hero and the footer is a list the style owns, on the
**Layout** tab: which bands appear, in what order, and how each is spaced,
coloured and sized. Each row renders `sections/<type>.php`, and two of those
types are the escape hatch for bands the design does not ship — `modules`
renders a named module position inside the design's own heading and spacing
(there are six free `section-a`…`section-f` positions for this), and `html`
takes markup straight from the style. So a new band does not mean editing
`index.php`. Leave the list untouched and `$defaultSections` in `index.php`
supplies the design's own order; keep the two in step when either changes. A
section whose source turns out to be empty takes its band away with it, so an
unpopulated module position does not leave a coloured empty strip.

`tpl_lkim3` also differs from its siblings in two ways worth knowing before
editing it. Each homepage band (audience gateways, services, gallery, news, agencies)
and the main navigation take their content either from the LKIM-3 design or
from a module position, chosen per band in the template style; the design is
the default so a fresh install matches the mockup. And the mega menu is drawn
by `megamenu.php` straight from a site menu rather than from the `menu`
position, so installing it does not disturb the DJ-MegaMenu module the current
default style relies on. `html/mod_menu/default.php` renders the same panel for
anyone who switches the navigation back to a core menu module.

`site-images/` holds images that generated module content points at; copy them
into the site `images/` folder before running `build-modules.php`.

`admin-overrides/` holds administrator template overrides that the scripts do
not create — currently a fix for the Preview link in Site Template Styles; see
its README. Copy them into `administrator/templates/atum/html/` on deploy.

`Cleaner.php` converts the source markup to clean HTML — it handles both page
builders the site used: Elementor on the newer pages, and WPBakery shortcodes
on the older ones. `map.php` is the single source of truth for taxonomy and
routing decisions — correct it there, not downstream. `XlsxWriter.php` is a
minimal SpreadsheetML writer, because this machine has no Python and the
project has no Composer packages.

---

## What landed

- **1,065 articles** — 906 Malay (the canonical, translatable set) + 159
  standalone English pages
- **25 categories** mirroring the source sitemap
- **4,854 media files, 3.2 GB**, filed under `images/` by category
- **101 menu items** across three menus
- **70 Falang translations** + 91 translated menu labels + 15 module titles
- **2,268 redirects** from the old permalinks
- **sitemap.xml** with 1,090 URLs and 190 hreflang alternates, plus a **Peta
  Laman** page generated from the live menus (87 links, both languages)
- **content checklist** as an .xlsx in the JKM format, statuses resolved live
- **0 problems** across a full 1,155-URL crawl, and 0 broken links across the
  1,264 URLs the two sitemaps advertise

---

## Known gaps

These are limits of the source material, not of the import. Each has a report.

| Report | Rows | What it means |
|---|---|---|
| `reports/media-failures.csv` | 10 | Files that 404 on lkim.gov.my itself. |
| `reports/unresolved-links.csv` | 176 | Links in article bodies pointing at pages that do not exist on the source either — mostly leftovers from an older Liferay portal (`/c/document_library/...`) and `/intranet`. |
| `reports/english-unpaired.csv` | 159 | English pages the source never declares as a translation of anything. Imported as standalone `en-GB` articles; pair them in Falang if a Malay counterpart is identified. |
| `reports/translation-rejected.csv` | 4 | Malay pages whose hreflang all point at the same English page — the source's own links are wrong there. |
| `reports/menu-gaps.csv` | 1 | *Pendaratan Ikan di Kompleks / Labuhan Perikanan LKIM* — a broken link on the live site too. Rendered as a heading. |
| `reports/menu-untranslated.csv` | 9 | Mostly labels identical in both languages (Agrotourism, KUNITA, Fishpro). Three are Malay-only branches. |
| `reports/skipped-spam.csv` | 2 | Injected SEO spam on the source. See the note below. |

### The source site carries injected spam

`lkim.gov.my` has SEO spam in it: a page titled *"Research Paper Writing
Services: Things to Consider"*, and a tag cloud of `anabolic-steroids`,
`npp-steroid`, `online-steroids` and similar. The tags carry no posts, and the
spam pages are excluded by `map.php`'s blocklist, so none of it was migrated.
**This is worth raising with LKIM** — it suggests the WordPress install has been
compromised at some point.

---

## Before go-live

1. `php harden.php --production` — turns on page caching, Gzip, HSTS, forced
   SSL, and removes the `noindex` robots directive.
2. Point `$live_site` in `configuration.php` at the real hostname.
3. The `.htaccess` hardening block only takes effect on Apache/LiteSpeed. Herd
   serves through nginx locally, so `X-Content-Type-Options` and the
   `images/*.php` execution block are untested here — verify them on the
   production host.
4. The Content-Security-Policy is deliberately **report-only**. Watch the
   reports with real traffic before enforcing; the portal embeds YouTube,
   Facebook and Google Maps.
5. Replace the placeholder links in the *Perkhidmatan Atas Talian* module and
   the footer address/social modules with the real system URLs.
6. Turn on multi-factor authentication for the Super User.
7. Re-run `php cli/joomla.php finder:index` after the final content pass.
8. Regenerate the sitemaps against the real hostname —
   `php build-sitemap.php --base=https://www.lkim.gov.my` — and re-run it
   whenever the menus or a batch of content change. `sitemap.xml` is a static
   file, so it does not update itself; wiring it to Joomla's task scheduler is
   the obvious follow-up if LKIM publish often.

## SPLaSK / MyGovEA

The template renders the audited elements — language switcher, text-resize
controls, high-contrast toggle, last-updated stamp, visitor-counter slot,
breadcrumbs, skip links, search, and the full footer policy set (FAQ, Pautan,
Peta Laman, Terma & Syarat, Dasar Keselamatan, Dasar Privasi, Penafian,
Aduan/Pertanyaan/Cadangan).

Each is marked with a `data-splask="..."` attribute. **The attribute name is a
placeholder** — set the real one from the current MAMPU/JDN circular in the
template style's *SPLaSK / MyGovEA → Atribut penanda SPLaSK* field, which
rewrites every marker at once.

The visitor counter is a slot, not an implementation: `lkim.js` fetches
`com_ajax` plugin `lkimcounter` and leaves an em dash if nothing answers. Build
that plugin or swap in the agency's existing counter.
