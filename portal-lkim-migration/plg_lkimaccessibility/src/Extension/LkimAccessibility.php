<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.lkimaccessibility
 *
 * @copyright   (C) 2026 Lembaga Kemajuan Ikan Malaysia
 * @license     GNU General Public License version 2 or later
 */

namespace Aidan\Plugin\System\LkimAccessibility\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

/**
 * The accessibility panel, as a plugin rather than part of a template.
 *
 * It lived in tpl_lkim3 first, which put nine profiles and a dozen tools on an
 * already long template-style form and tied a site-wide facility to one
 * template. Here it applies to whatever template is in use, and survives a
 * template change.
 *
 * Two hooks: assets go on in onBeforeCompileHead, and the panel markup is
 * spliced in before </body> in onAfterRender, which is the only point where the
 * whole response exists as a string.
 */
final class LkimAccessibility extends CMSPlugin implements SubscriberInterface
{
    /**
     * @var  boolean
     */
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeCompileHead' => 'loadAssets',
            'onAfterRender'       => 'injectPanel',
        ];
    }

    /**
     * Whether this response should carry the panel at all.
     */
    private function applies(): bool
    {
        $app = $this->getApplication();

        if (!$app->isClient('site')) {
            return false;
        }

        // Only a complete HTML page has a </body> to splice into, and only an
        // HTML document has a head to put assets in.
        $doc = $app->getDocument();

        if ($doc->getType() !== 'html' || $app->getInput()->get('tmpl') === 'component') {
            return false;
        }

        /*
         * Not on a 5xx. A maintenance or error page is a stripped-down render
         * that may leave out the scripts block entirely, and a panel whose
         * script never loads is worse than no panel: a launcher that does
         * nothing. 4xx pages are ordinary full renders, so they keep it.
         *
         * The queued headers, not getResponse()->getStatusCode(): a template
         * that calls setHeader('Status', '503 …') only has it applied when the
         * application responds, which is long after both of these hooks.
         */
        foreach ($app->getHeaders() as $header) {
            if (strtolower((string) ($header['name'] ?? '')) === 'status'
                && (int) $header['value'] >= 500) {
                return false;
            }
        }

        return true;
    }

    public function loadAssets(): void
    {
        if (!$this->applies()) {
            return;
        }

        $wa = $this->getApplication()->getDocument()->getWebAssetManager();

        $wa->registerAndUseStyle('lkim.a11y', 'plg_system_lkimaccessibility/a11y.css');
        $wa->registerAndUseScript('lkim.a11y', 'plg_system_lkimaccessibility/a11y.js', [], ['defer' => true]);

        // The stylesheet falls back to the LKIM palette, so these are only
        // written when the site has said otherwise.
        $overrides = [];
        $width     = trim((string) $this->params->get('width', ''));
        $accent    = trim((string) $this->params->get('accent', ''));

        if ($width !== '' && preg_match('/^[0-9.]+(px|rem|em|vw|%)$/', $width)) {
            $overrides[] = '--a11y-panel-w: ' . $width . ';';
        }

        // Distance from the top or bottom edge only. The side distance is left
        // to the stylesheet, so pushing the button below a floating header does
        // not also drag it in from the side.
        $offset = trim((string) $this->params->get('launcherOffset', ''));

        if ($offset !== '' && preg_match('/^[0-9.]+(px|rem|em|vh|vw|%)$/', $offset)) {
            $overrides[] = '--a11y-launcher-y: ' . $offset . ';';
        }

        if ($accent !== '' && preg_match('/^#[0-9a-f]{3,8}$/i', $accent)) {
            $overrides[] = '--a11y-accent: ' . $accent . ';';
        }

        if ($overrides) {
            $wa->addInlineStyle(
                ':root {' . implode(' ', $overrides) . '}',
                ['name' => 'lkim.a11y.vars'],
                [],
                ['lkim.a11y']
            );
        }
    }

    public function injectPanel(): void
    {
        if (!$this->applies()) {
            return;
        }

        $app  = $this->getApplication();
        $body = $app->getBody();

        // Nothing to splice into, or something already put a panel there.
        if (stripos($body, '</body>') === false || strpos($body, 'id="a11y-panel"') !== false) {
            return;
        }

        $panel = $this->renderPanel();

        if ($panel === '') {
            return;
        }

        // Replace the last </body> only, so a code sample in the page content
        // containing that string is left alone.
        $at   = strripos($body, '</body>');
        $body = substr($body, 0, $at) . $panel . substr($body, $at);

        $app->setBody($body);
    }

    /**
     * A list parameter may arrive as an array or as a comma-separated string
     * depending on how the style was saved; an unsaved plugin gives neither, so
     * fall back to the full set rather than an empty panel.
     */
    private function listParam(string $name, array $fallback): array
    {
        $value = $this->params->get($name);

        if ($value === null || $value === '') {
            return $fallback;
        }

        $items = \is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map('trim', $items)));
    }

    private function renderPanel(): string
    {
        $profiles = $this->listParam('profiles', ['blind', 'lowvision', 'colorblind', 'dyslexia', 'adhd', 'epilepsy', 'motor', 'deaf', 'elderly']);
        $tools    = $this->listParam('tools', ['font', 'align', 'links', 'headings', 'cursor', 'motion', 'images', 'read', 'guide', 'mask', 'media', 'targets']);

        $side      = $this->params->get('position', 'left') === 'right' ? 'right' : 'left';
        $edge      = $this->params->get('launcherY', 'top') === 'bottom' ? 'bottom' : 'top';
        $launcher  = (bool) $this->params->get('launcher', 1);
        $statement = trim((string) $this->params->get('statement', ''));

        if ($statement !== '' && !preg_match('#^(https?:)?//|^mailto:#i', $statement)) {
            $statement = Uri::root(true) . '/' . ltrim($statement, '/');
        }

        ob_start();
        require \dirname(__DIR__, 2) . '/tmpl/panel.php';

        return (string) ob_get_clean();
    }
}
