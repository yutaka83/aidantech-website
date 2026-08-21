# Administrator template overrides

Copy into `administrator/templates/atum/html/` on any deployment of this
portal. They are not created by the pipeline scripts, so they have to travel
with the repo.

## com_templates/styles/default.php

Fixes the **Preview** link in *System → Site Template Styles*.

On Joomla 6.1.3 that link opens the **administrator**, showing the admin
template's module positions, instead of opening the site and showing the site
template's positions.

The view builds the link with `Route::link('site', 'index.php?tp=1&templateStyle=N')`.
Inside the administrator, `Route::link` resolves the site router through the DI
container, which has to construct an entire `SiteApplication`. When that
resolution fails it falls back to the administrator router, whose `build()`
prefixes the path with `basename(JPATH_ADMINISTRATOR)` — producing
`/administrator/index.php?tp=1&templateStyle=N`.

The override builds the URL from `Uri::root()` instead, which
`AdministratorApplication`'s constructor has already reset to the site root.
Administrator styles still use `Route::link('administrator', ...)`, which is
correct for them.

Remove this override if a later Joomla release fixes the core behaviour.
