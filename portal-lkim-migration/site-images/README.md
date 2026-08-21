# Media-library images used by generated modules

`build-modules.php` writes module content that references these paths, so they
have to exist in the site's `images/` folder on any deployment. Copy this
folder's contents into `images/` before running that script.

| Folder | Used by |
|---|---|
| `ikon-perkhidmatan/` | Perkhidmatan Popular module (`quicklinks`) |
| `agensi/` | Agensi Berkaitan module (`agencies`) |

They live here rather than in the template package because editors change these
modules in the admin, so the images belong in the media manager where they can
see them.

Source: the reference design at nurzamf.github.io/lkim — see `../reference/README.md`
for provenance.
