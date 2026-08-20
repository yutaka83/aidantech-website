<?php
/**
 * Turns lkim.gov.my page bodies into clean Joomla article HTML.
 *
 * The source is Elementor: every paragraph is buried under four or five
 * presentational <div>s carrying data-elementor-* attributes and inline styles
 * that reference Elementor's CSS custom properties. None of that survives the
 * move, so the cleaner promotes each widget's real payload and discards the
 * scaffolding, then rewrites media and internal links to their new homes.
 */
class Cleaner
{
    /** @var array<string,string> normalised source url => new site-relative path */
    private array $mediaMap;

    /** @var array<string,string> normalised source path => joomla route */
    private array $linkMap;

    /** @var string[] */
    public array $unresolvedLinks = [];

    /** Attributes worth keeping, per tag. Everything else is dropped. */
    private const KEEP = [
        '*'      => ['href', 'src', 'alt', 'title', 'colspan', 'rowspan', 'target', 'rel', 'lang', 'dir'],
        'img'    => ['src', 'alt', 'title', 'width', 'height', 'loading', 'decoding'],
        'iframe' => ['src', 'title', 'width', 'height', 'allow', 'allowfullscreen', 'loading'],
        'a'      => ['href', 'title', 'target', 'rel'],
        'th'     => ['colspan', 'rowspan', 'scope'],
        'td'     => ['colspan', 'rowspan'],
    ];

    /** Widgets that carry no content once the styling is gone. */
    private const DROP_WIDGETS = [
        'divider.default',
        'spacer.default',
        'sidebar.default',                  // in-content nav — a module in Joomla
        'icon.default',
        'theme-post-featured-image.default',  // Joomla renders the intro image
        'countdown.default',
        'facebook-page.default',
        'smartslider.default',
        'slides.default',
        'media-carousel.default',
    ];

    public function __construct(array $mediaMap = [], array $linkMap = [])
    {
        $this->mediaMap = $mediaMap;
        $this->linkMap  = $linkMap;
    }

    public function clean(string $html): string
    {
        $this->unresolvedLinks = [];

        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="lkim-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $xp   = new DOMXPath($doc);
        $root = $doc->getElementById('lkim-root') ?? $doc->documentElement;

        $this->removeNoise($xp);
        $this->convertWidgets($doc, $xp);
        $this->unwrapScaffolding($xp);
        $this->stripAttributes($xp);
        $this->rewriteUrls($xp);
        $this->dropEmpty($xp);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $this->tidy($out);
    }

    /* ── Steps ───────────────────────────────────────────────────────────── */

    private function removeNoise(DOMXPath $xp): void
    {
        $gone = $xp->query('//script | //style | //noscript | //link | //meta');
        foreach ($gone as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function convertWidgets(DOMDocument $doc, DOMXPath $xp): void
    {
        // Innermost first, so nested widgets are handled before their parents.
        $widgets = iterator_to_array($xp->query('//*[@data-widget_type]'));
        $widgets = array_reverse($widgets);

        foreach ($widgets as $widget) {
            /** @var DOMElement $widget */
            $type = $widget->getAttribute('data-widget_type');

            if (in_array($type, self::DROP_WIDGETS, true)) {
                $widget->parentNode?->removeChild($widget);
                continue;
            }

            $replacement = match ($type) {
                'pdf_viewer.default', 'pdfjs_viewer.default' => $this->pdfLink($doc, $widget),
                'flip-box.default'                           => $this->flipBox($doc, $widget),
                'tabs.default'                               => $this->tabs($doc, $widget),
                'gallery.default'                            => $this->gallery($doc, $widget),
                default                                      => null,
            };

            if ($replacement !== null) {
                $widget->parentNode?->replaceChild($replacement, $widget);
                continue;
            }

            // Default: keep whatever the widget container holds.
            $this->unwrap($widget);
        }
    }

    /** A PDF viewer becomes a plain download link — no embedded reader. */
    private function pdfLink(DOMDocument $doc, DOMElement $widget): ?DOMNode
    {
        $url = null;

        foreach (['data-settings', 'data-pdf-url'] as $attr) {
            $raw = $widget->getAttribute($attr);
            if ($raw && preg_match('#https?://[^"\'\\\\ ]+\.pdf#i', $raw, $m)) {
                $url = $m[0];
                break;
            }
        }

        if ($url === null) {
            $html = $doc->saveHTML($widget);
            if (preg_match('#https?://[^"\'\\\\ ]+\.pdf#i', $html, $m)) {
                $url = $m[0];
            }
        }

        if ($url === null) {
            return null;
        }

        $p = $doc->createElement('p');
        $a = $doc->createElement('a', htmlspecialchars(basename(parse_url($url, PHP_URL_PATH) ?: 'Muat turun PDF'), ENT_QUOTES));
        $a->setAttribute('href', $url);
        $a->setAttribute('target', '_blank');
        $a->setAttribute('rel', 'noopener');
        $p->appendChild($a);

        return $p;
    }

    /** Flip boxes carry a title and a blurb; flatten to a heading + text. */
    private function flipBox(DOMDocument $doc, DOMElement $widget): DOMNode
    {
        $frag = $doc->createElement('div');
        $xp   = new DOMXPath($doc);

        foreach ($xp->query('.//*[contains(@class,"elementor-flip-box__layer__title")]', $widget) as $title) {
            $h = $doc->createElement('h3', htmlspecialchars(trim($title->textContent), ENT_QUOTES));
            $frag->appendChild($h);
        }

        foreach ($xp->query('.//*[contains(@class,"elementor-flip-box__layer__description")]', $widget) as $desc) {
            $p = $doc->createElement('p', htmlspecialchars(trim($desc->textContent), ENT_QUOTES));
            $frag->appendChild($p);
        }

        foreach ($xp->query('.//a[@href]', $widget) as $link) {
            $frag->appendChild($link->cloneNode(true));
        }

        return $frag;
    }

    /** Tabs flatten to a sequence of headings and panels. */
    private function tabs(DOMDocument $doc, DOMElement $widget): DOMNode
    {
        $frag = $doc->createElement('div');
        $xp   = new DOMXPath($doc);

        $titles = iterator_to_array($xp->query('.//*[contains(@class,"elementor-tab-title")]', $widget));
        $panels = iterator_to_array($xp->query('.//*[contains(@class,"elementor-tab-content")]', $widget));

        foreach ($titles as $i => $title) {
            $h = $doc->createElement('h3', htmlspecialchars(trim($title->textContent), ENT_QUOTES));
            $frag->appendChild($h);

            if (isset($panels[$i])) {
                $panel = $doc->createElement('div');
                foreach (iterator_to_array($panels[$i]->childNodes) as $child) {
                    $panel->appendChild($child->cloneNode(true));
                }
                $frag->appendChild($panel);
            }
        }

        return $frag;
    }

    /** Galleries become a plain list of images, styled by the template. */
    private function gallery(DOMDocument $doc, DOMElement $widget): DOMNode
    {
        $xp = new DOMXPath($doc);
        $ul = $doc->createElement('ul');
        $ul->setAttribute('class', 'lkim-gallery');
        $seen = [];

        foreach ($xp->query('.//img', $widget) as $img) {
            /** @var DOMElement $img */
            // Elementor lazy-loads: the real file is often in data-src / srcset.
            $src = $img->getAttribute('data-src')
                ?: $img->getAttribute('src');

            if ($src === '' || isset($seen[$src])) {
                continue;
            }

            $seen[$src] = true;

            $li  = $doc->createElement('li');
            $new = $doc->createElement('img');
            $new->setAttribute('src', $src);
            $new->setAttribute('alt', $img->getAttribute('alt'));
            $new->setAttribute('loading', 'lazy');
            $li->appendChild($new);
            $ul->appendChild($li);
        }

        return $ul->hasChildNodes() ? $ul : $doc->createTextNode('');
    }

    /** Remove Elementor's structural wrappers, keeping their children. */
    private function unwrapScaffolding(DOMXPath $xp): void
    {
        $selectors = [
            'elementor-section', 'elementor-container', 'elementor-column',
            'elementor-widget-wrap', 'elementor-widget-container', 'elementor-element',
            'elementor-inner', 'elementor-row', 'elementor',
        ];

        // Repeat until stable: unwrapping exposes the next layer down.
        for ($pass = 0; $pass < 12; $pass++) {
            $found = false;

            foreach ($selectors as $class) {
                $match = 'contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")';
                $nodes = $xp->query('//div[' . $match . '] | //section[' . $match . '] | //article[' . $match . ']');

                foreach (iterator_to_array($nodes) as $node) {
                    $this->unwrap($node);
                    $found = true;
                }
            }

            if (!$found) {
                break;
            }
        }
    }

    private function unwrap(DOMNode $node): void
    {
        $parent = $node->parentNode;
        if (!$parent) {
            return;
        }

        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }

    private function stripAttributes(DOMXPath $xp): void
    {
        foreach ($xp->query('//*') as $el) {
            /** @var DOMElement $el */
            $tag  = strtolower($el->nodeName);
            $keep = self::KEEP[$tag] ?? self::KEEP['*'];

            foreach (iterator_to_array($el->attributes ?? []) as $attr) {
                if (!in_array(strtolower($attr->name), $keep, true)) {
                    $el->removeAttribute($attr->name);
                }
            }
        }
    }

    private function rewriteUrls(DOMXPath $xp): void
    {
        foreach ($xp->query('//*[@src] | //*[@href]') as $el) {
            /** @var DOMElement $el */
            foreach (['src', 'href'] as $attr) {
                if (!$el->hasAttribute($attr)) {
                    continue;
                }

                $value = $el->getAttribute($attr);
                $new   = $this->mapUrl($value);

                if ($new !== null) {
                    $el->setAttribute($attr, $new);
                }
            }
        }
    }

    /**
     * @return string|null the replacement, or null to leave the URL untouched
     */
    private function mapUrl(string $url): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:')) {
            return null;
        }

        // Unwrap Google Docs viewer links back to the file they point at.
        if (preg_match('#google\.com/(?:gview|viewer)\?url=([^&]+)#i', $url, $m)) {
            $url = urldecode($m[1]);
        }

        if (!preg_match('#^https?://(?:www\.)?lkim\.gov\.my(/.*)?$#i', $url, $m)) {
            return $url === '' ? null : $url;   // external, leave as is
        }

        $path = $m[1] ?? '/';

        // Media
        $key = $this->normalise($path);
        if (isset($this->mediaMap[$key])) {
            return '/' . $this->mediaMap[$key];
        }

        // WordPress size derivative of a file we do have
        $original = preg_replace('/-\d{2,4}x\d{2,4}(?=\.[a-z0-9]+$)/i', '', $key);
        if ($original !== $key && isset($this->mediaMap[$original])) {
            return '/' . $this->mediaMap[$original];
        }

        // Internal page
        $slug = trim(preg_replace('#^/en/#', '/', $path), '/');
        $slug = basename($slug);

        if (isset($this->linkMap[$slug])) {
            return $this->linkMap[$slug];
        }

        $this->unresolvedLinks[] = $url;

        return $url;
    }

    private function normalise(string $path): string
    {
        return strtolower(rawurldecode(preg_split('/[?&#]/', $path)[0]));
    }

    /** Drop wrappers that ended up with nothing in them. */
    private function dropEmpty(DOMXPath $xp): void
    {
        for ($pass = 0; $pass < 6; $pass++) {
            $removed = 0;
            $nodes = $xp->query('//div | //span | //p | //section | //li | //ul');

            foreach (iterator_to_array($nodes) as $node) {
                /** @var DOMElement $node */
                if ($node->getElementsByTagName('img')->length > 0
                    || $node->getElementsByTagName('iframe')->length > 0
                    || $node->getElementsByTagName('a')->length > 0) {
                    continue;
                }

                if (trim(preg_replace('/\x{00a0}/u', ' ', $node->textContent)) === '') {
                    $node->parentNode?->removeChild($node);
                    $removed++;
                }
            }

            if ($removed === 0) {
                break;
            }
        }
    }

    /** Final text-level tidy: collapse whitespace, drop leftover bare divs. */
    private function tidy(string $html): string
    {
        // Bare <div> with no attributes adds nothing once Elementor is gone.
        $html = preg_replace('#</?div>#', '', $html);

        // Elementor heading widgets sometimes nest a heading inside a heading.
        for ($pass = 0; $pass < 3; $pass++) {
            $collapsed = preg_replace('#<(h[1-6])>\s*<h[1-6]>(.*?)</h[1-6]>\s*</\1>#s', '<$1>$2</$1>', $html);
            if ($collapsed === $html) {
                break;
            }
            $html = $collapsed;
        }

        $html = preg_replace('#<span>(.*?)</span>#s', '$1', $html);
        $html = preg_replace('/(\R\s*){3,}/', "\n\n", $html);
        $html = preg_replace('/[ \t]+\R/', "\n", $html);

        return trim($html);
    }
}
