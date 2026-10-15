<?php
declare(strict_types=1);
const CMS_ROOT = __DIR__ . '/..';
const CMS_DATA = __DIR__ . '/data';
function cms_json(string $name): array {
    $path = CMS_DATA . '/' . $name . '.json';
    if (!is_file($path)) throw new RuntimeException('CMS content has not been initialized.');
    return json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}
function cms_write(string $name, array $value): void {
    $path = CMS_DATA . '/' . $name . '.json';
    $tmp = tempnam(CMS_DATA, 'save-');
    try {
        if (file_put_contents($tmp, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX) === false) throw new RuntimeException('Unable to write content.');
        if (!rename($tmp, $path)) throw new RuntimeException('Unable to save content.');
    } finally { if (is_file($tmp)) unlink($tmp); }
}
function cms_doc(string $html): DOMDocument {
    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NODEFDTD);
    foreach (iterator_to_array($doc->childNodes) as $node) if ($node->nodeType === XML_PI_NODE) $doc->removeChild($node);
    $doc->encoding = 'UTF-8'; libxml_clear_errors(); return $doc;
}
function cms_fragment(string $html): DOMDocument { return cms_doc('<html><body><div id="cms-root">' . $html . '</div></body></html>'); }
function cms_inner(DOMNode $node): string { $html = ''; foreach ($node->childNodes as $child) $html .= $node->ownerDocument->saveHTML($child); return $html; }
function cms_replace(DOMNode $node, string $html): void {
    $temp = cms_fragment($html);
    $root = $temp->getElementById('cms-root');
    foreach (iterator_to_array($root->childNodes) as $child) $node->parentNode->insertBefore($node->ownerDocument->importNode($child, true), $node);
    $node->parentNode->removeChild($node);
}
function cms_url(string $value): bool {
    return !preg_match('/[\x00-\x20\\\\]/', $value) && !str_starts_with($value, '//') && !preg_match('/^(?!https?:|mailto:|tel:)[a-z][a-z0-9+.-]*:/i', $value);
}
function cms_meaningful(DOMNode $node): bool {
    $value = trim($node->textContent);
    return $value !== '' && !in_array($value, ['/', '.', '▹', '·'], true);
}
function cms_set_text(DOMNode $node, string $value): void {
    $xp = new DOMXPath($node->ownerDocument);
    $texts = [];
    foreach ($xp->query('.//text()[not(ancestor::svg)]', $node) as $text) if (cms_meaningful($text)) $texts[] = $text;
    if (!$texts) { $node->appendChild($node->ownerDocument->createTextNode($value)); return; }
    $texts[0]->nodeValue = $value;
    foreach (array_slice($texts, 1) as $text) $text->nodeValue = '';
}
function cms_render_model(array $model, array $values, array $collections = []): string {
    $doc = cms_fragment($model['html']); $xp = new DOMXPath($doc);
    // Resolve all references before edits alter the DOM.
    $references = [];
    foreach ($model['fields'] as $key => $field) $references[$key] = $xp->query($field['path'])->item(0);
    foreach ($model['fields'] as $key => $field) {
        $node = $references[$key]; if (!$node) continue;
        $value = (string)($values[$key] ?? $field['default']);
        if ($field['type'] === 'lines') {
            foreach (iterator_to_array($node->childNodes) as $child) $node->removeChild($child);
            $lines = preg_split('/\R/u', $value);
            if (($field['repeat'] ?? 1) > 1) $lines = array_merge(...array_fill(0, $field['repeat'], $lines));
            foreach ($lines as $line) {
                if (trim($line) === '') continue;
                $itemDoc = cms_fragment($field['item']);
                $item = $itemDoc->getElementById('cms-root')->firstChild;
                cms_set_text($item, trim($line));
                $node->appendChild($doc->importNode($item, true));
            }
        } elseif ($field['type'] === 'code') {
            if ($value !== $field['default']) {
                foreach (iterator_to_array($node->childNodes) as $child) $node->removeChild($child);
                $node->appendChild($doc->createTextNode($value));
            }
        } else { $node->nodeValue = $value; }
    }
    foreach (iterator_to_array($xp->query('//*[@data-cms-collection]')) as $node) {
        $id = $node->getAttribute('data-cms-collection');
        $html = '';
        foreach ($collections[$id] ?? [] as $item) if (($item['visible'] ?? true) === true) $html .= cms_render_model(cms_json('templates')['items'][$item['template']], $item['values']);
        cms_replace($node, $html);
    }
    foreach (iterator_to_array($xp->query('//*[@data-cms-block]')) as $node) {
        $id=$node->getAttribute('data-cms-block');$templates=cms_json('templates');$content=cms_json('content');
        $html=($content['visibility'][$id]??true)?cms_render_model($templates['blocks'][$id],$content['blocks'][$id],$collections):'';
        cms_replace($node,$html);
    }
    return cms_inner($doc->getElementById('cms-root'));
}
function cms_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('portfolio_admin');
        session_set_cookie_params(['httponly'=>true, 'samesite'=>'Strict', 'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'path'=>rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/']);
        session_start();
    }
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
}
function cms_authenticated(): bool { return !empty($_SESSION['admin']) && time() - ($_SESSION['last_active'] ?? 0) < 7200; }
function cms_require_auth(): void {
    cms_session();
    if (!cms_authenticated()) { http_response_code(401); throw new RuntimeException('Please sign in again.'); }
    $_SESSION['last_active'] = time();
}
function cms_csrf(): void {
    if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '')) { http_response_code(403); throw new RuntimeException('Refresh the page and try again.'); }
}
