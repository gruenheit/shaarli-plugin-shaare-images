<?php

declare(strict_types=1);

use Shaarli\Plugin\PluginManager;
use Shaarli\Config\ConfigManager;

const SHAARE_IMAGES_CACHE_DIR = 'cache/shaare-images';
const SHAARE_IMAGES_MAX_BYTES = 5 * 1024 * 1024; // 5 MB
const SHAARE_IMAGES_TIMEOUT = 10;
const EXT_TRANSLATION_DOMAIN = 'shaare_images';

/**
 * Registriert die eigene Übersetzungsdomain bei Shaarli (einmalig, dann persistent in config.json.php).
 */
function shaare_images_init(ConfigManager $conf): void
{
    if (!$conf->exists('translation.extensions.shaare_images')) {
        $conf->set('translation.extensions.shaare_images', 'plugins/shaare_images/languages/');
        $conf->write(true);
    }
}

/**
 * Übersetzungs-Wrapper -- Englisch als Quelltext, Übersetzung per .po-Datei je Sprache.
 */
function shaare_images_t(string $text): string
{
    return t($text, '', 1, EXT_TRANSLATION_DOMAIN);
}
// Matcht: ![alt-text|small](url) oder ![alt-text|large](url)
const SHAARE_IMAGES_MD_PATTERN = '/!\[([^\]|]*)\|(small|large)\]\(([^)\s]+)\)/';
// Matcht ein bereits gerendertes <img ... alt="alt|small" ...> (Größenmarker noch im Alt-Text)
const SHAARE_IMAGES_HTML_PATTERN = '/(<img\b[^>]*\balt=")([^"]*)\|(small|large)("[^>]*>)/';

/**
 * Konfigurierte Zielbreite für eine Größenstufe, mit Fallback-Default.
 */
function shaare_images_width(string $size, ConfigManager $conf): int
{
    $key = $size === 'small' ? 'plugins.SHAARE_IMAGES_WIDTH_SMALL' : 'plugins.SHAARE_IMAGES_WIDTH_LARGE';
    $default = $size === 'small' ? 250 : 600;
    $value = (int) $conf->get($key, $default);

    return $value > 0 ? $value : $default;
}

/**
 * Includes: eigenes CSS für den Toolbar-Button laden.
 */
function hook_shaare_images_render_includes(array $data): array
{
    $data['css_files'][] = PluginManager::$PLUGINS_PATH . '/shaare_images/shaare_images.css';

    return $data;
}

/**
 * Footer: eigenes JS nur für eingeloggte Nutzer laden (Button/Dialog werden nur beim Bearbeiten gebraucht).
 */
function hook_shaare_images_render_footer(array $data): array
{
    if (!($data['_LOGGEDIN_'] ?? false)) {
        return $data;
    }

    $data['js_files'][] = PluginManager::$PLUGINS_PATH . '/shaare_images/shaare_images.js';

    return $data;
}

/**
 * Bearbeiten-Formular: eigenen Button + Dialog-Markup einfügen.
 * Unabhängig vom markdown_toolbar-Plugin -- arbeitet direkt auf dem rohen Textfeld.
 */
function hook_shaare_images_render_editlink(array $data, ConfigManager $conf): array
{
    $insertImage = shaare_images_t('Insert image');
    $small = shaare_images_t('Small');
    $large = shaare_images_t('Large');

    $jsI18n = [
        'statusOk' => shaare_images_t('Image is {w}×{h} px — fits both sizes.'),
        'statusWarn' => shaare_images_t(
            'Image is only {w}×{h} px — would be upscaled and blurry for "{size}" ({width} px).'
        ),
        'statusError' => shaare_images_t('Not a valid image URL — image could not be loaded.'),
        'small' => $small,
        'large' => $large,
    ];

    $html = file_get_contents(PluginManager::$PLUGINS_PATH . '/shaare_images/shaare_images_editlink.html');
    $html = sprintf(
        $html,
        shaare_images_width('small', $conf),
        shaare_images_width('large', $conf),
        htmlspecialchars(json_encode($jsI18n), ENT_QUOTES),
        $insertImage,
        shaare_images_t('Image URL'),
        shaare_images_t('Alt text (short description)'),
        shaare_images_t('e.g. photo from the beach'),
        shaare_images_t('Size'),
        $small,
        $large,
        shaare_images_t('Cancel'),
        shaare_images_t('Insert')
    );
    $data['edit_link_plugin'][] = $html;

    return $data;
}

/**
 * Beim Speichern: rohen Beschreibungstext nach unserer Syntax durchsuchen,
 * externe Bild-URLs herunterladen + lokal cachen, Text auf lokalen Pfad umschreiben.
 */
function hook_shaare_images_save_link(array $data): array
{
    $description = $data['description'] ?? '';

    if (strpos($description, '|small]') === false && strpos($description, '|large]') === false) {
        return $data;
    }

    if (!is_dir(SHAARE_IMAGES_CACHE_DIR)) {
        mkdir(SHAARE_IMAGES_CACHE_DIR, 0755, true);
    }

    // Manitu-Webspace: cache/ ist per .htaccess grundsaetzlich gesperrt (Require all denied) --
    // eigene Freigabe noetig, analog cache/thumb/.htaccess. Auf anderen Hosts (z. B. nginx) wirkungslos.
    $htaccess = SHAARE_IMAGES_CACHE_DIR . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents(
            $htaccess,
            "<IfModule version_module>\n"
            . "  <IfVersion >= 2.4>\n"
            . "     Require all granted\n"
            . "  </IfVersion>\n"
            . "  <IfVersion < 2.4>\n"
            . "     Allow from all\n"
            . "     Deny from none\n"
            . "  </IfVersion>\n"
            . "</IfModule>\n\n"
            . "<IfModule !version_module>\n"
            . "    Require all granted\n"
            . "</IfModule>\n"
        );
    }

    $data['description'] = preg_replace_callback(
        SHAARE_IMAGES_MD_PATTERN,
        function (array $m): string {
            [$full, $alt, $size, $url] = $m;

            // Bereits lokal (z. B. erneutes Speichern einer bestehenden Notiz) -- nichts tun.
            if (strpos($url, '/' . SHAARE_IMAGES_CACHE_DIR . '/') === 0) {
                return $full;
            }

            $local = shaare_images_fetch($url);
            if ($local === null) {
                // Download fehlgeschlagen -- Original-URL behalten, Speichern nicht blockieren.
                return $full;
            }

            return '![' . $alt . '|' . $size . '](' . $local . ')';
        },
        $description
    );

    return $data;
}

/**
 * Lädt eine Bild-URL herunter und speichert sie lokal (dedupliziert per URL-Hash).
 * Gibt den root-relativen Pfad zurück (für <img src="...">), oder null bei Fehler.
 */
function shaare_images_fetch(string $url): ?string
{
    $hash = sha1($url);

    $existing = glob(SHAARE_IMAGES_CACHE_DIR . '/' . $hash . '.*');
    if (!empty($existing)) {
        return '/' . $existing[0];
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => SHAARE_IMAGES_TIMEOUT,
            'follow_location' => 1,
            'max_redirects' => 3,
            'user_agent' => 'Shaarli shaare_images plugin',
        ],
        'https' => [
            'timeout' => SHAARE_IMAGES_TIMEOUT,
        ],
    ]);

    $content = @file_get_contents($url, false, $context);
    if ($content === false || strlen($content) === 0 || strlen($content) > SHAARE_IMAGES_MAX_BYTES) {
        return null;
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'shim');
    file_put_contents($tmpFile, $content);

    $info = @getimagesize($tmpFile);
    if ($info === false) {
        unlink($tmpFile);
        return null;
    }

    $ext = image_type_to_extension($info[2], false);
    if (!$ext) {
        unlink($tmpFile);
        return null;
    }

    $target = SHAARE_IMAGES_CACHE_DIR . '/' . $hash . '.' . $ext;
    if (!rename($tmpFile, $target)) {
        unlink($tmpFile);
        return null;
    }
    chmod($target, 0644);

    return '/' . $target;
}

/**
 * Ersetzt in bereits gerendertem Beschreibungs-HTML den Größenmarker im Alt-Text
 * durch ein echtes width-Attribut (Höhe folgt per CSS automatisch dem Seitenverhältnis).
 */
function shaare_images_process_html(string $html, ConfigManager $conf): string
{
    if (strpos($html, '|small') === false && strpos($html, '|large') === false) {
        return $html;
    }

    return preg_replace_callback(
        SHAARE_IMAGES_HTML_PATTERN,
        function (array $m) use ($conf): string {
            $width = shaare_images_width($m[3], $conf);

            return $m[1] . $m[2] . '"'
                . ' width="' . $width . '" style="height:auto;max-width:100%;"'
                . $m[4];
        },
        $html
    );
}

function hook_shaare_images_render_linklist(array $data, ConfigManager $conf): array
{
    foreach ($data['links'] as &$link) {
        if (!empty($link['description'])) {
            $link['description'] = shaare_images_process_html($link['description'], $conf);
        }
    }
    unset($link);

    return $data;
}

function hook_shaare_images_render_daily(array $data, ConfigManager $conf): array
{
    foreach ($data['linksToDisplay'] as &$day) {
        foreach ($day['links'] as &$link) {
            if (!empty($link['description'])) {
                $link['description'] = shaare_images_process_html($link['description'], $conf);
            }
        }
        unset($link);
    }
    unset($day);

    return $data;
}
