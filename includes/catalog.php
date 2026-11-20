<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Products the public can see (in stock or reserved), with optional filters.
 * Filters: make, model, category, fuel, q (free text), offset.
 */
function findProducts(array $f = [], int $limit = 60): array
{
    $where = ["p.stock_status IN ('available','reserved')"];
    $args = [];
    if (!empty($f['make']))     { $where[] = 'p.make = ?'; $args[] = $f['make']; }
    if (!empty($f['model']))    { $where[] = 'p.model = ?'; $args[] = $f['model']; }
    if (!empty($f['category']) && isset(CATEGORIES[$f['category']])) { $where[] = 'p.category = ?'; $args[] = $f['category']; }
    if (!empty($f['fuel']) && in_array($f['fuel'], ['petrol', 'diesel'], true)) { $where[] = 'p.fuel = ?'; $args[] = $f['fuel']; }
    if (!empty($f['q'])) {
        foreach (array_slice(preg_split('/\s+/', trim((string) $f['q'])) ?: [], 0, 5) as $w) {
            $where[] = "CONCAT_WS(' ', p.title, p.make, p.model, p.engine_code, p.capacity, p.part_number, p.category) LIKE ?";
            $args[] = '%' . $w . '%';
        }
    }
    $sql = 'SELECT p.* FROM products p WHERE ' . implode(' AND ', $where)
         . ' ORDER BY p.make, p.model, p.id LIMIT ' . max(1, min(200, $limit)) . ' OFFSET ' . max(0, (int) ($f['offset'] ?? 0));
    $s = getDB()->prepare($sql);
    $s->execute($args);
    return withMedia($s->fetchAll());
}

function countProducts(array $f = []): int
{
    $where = ["stock_status IN ('available','reserved')"];
    $args = [];
    foreach (['make', 'model'] as $k) if (!empty($f[$k])) { $where[] = "{$k} = ?"; $args[] = $f[$k]; }
    if (!empty($f['category']) && isset(CATEGORIES[$f['category']])) { $where[] = 'category = ?'; $args[] = $f['category']; }
    if (!empty($f['fuel']) && in_array($f['fuel'], ['petrol', 'diesel'], true)) { $where[] = 'fuel = ?'; $args[] = $f['fuel']; }
    if (!empty($f['q'])) {
        foreach (array_slice(preg_split('/\s+/', trim((string) $f['q'])) ?: [], 0, 5) as $w) {
            $where[] = "CONCAT_WS(' ', title, make, model, engine_code, capacity, part_number, category) LIKE ?";
            $args[] = '%' . $w . '%';
        }
    }
    $s = getDB()->prepare('SELECT COUNT(*) FROM products WHERE ' . implode(' AND ', $where));
    $s->execute($args);
    return (int) $s->fetchColumn();
}

function productBySlug(string $slug): ?array
{
    $s = getDB()->prepare("SELECT * FROM products WHERE slug = ? AND stock_status <> 'hidden'");
    $s->execute([$slug]);
    $p = $s->fetch();
    return $p ? withMedia([$p])[0] : null;
}

function productById(int $id): ?array
{
    $s = getDB()->prepare('SELECT * FROM products WHERE id = ?');
    $s->execute([$id]);
    $p = $s->fetch();
    return $p ? withMedia([$p])[0] : null;
}

function withMedia(array $rows): array
{
    if (!$rows) return [];
    $ids = array_column($rows, 'id');
    $s = getDB()->prepare('SELECT * FROM product_media WHERE product_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY sort_order, id');
    $s->execute($ids);
    $by = [];
    foreach ($s->fetchAll() as $m) $by[$m['product_id']][] = $m;
    foreach ($rows as &$r) $r['media'] = $by[$r['id']] ?? [];
    return $rows;
}

/** Small version of an image if one exists (…-sm.webp), else the image itself. */
function thumbOf(string $path): string
{
    $sm = preg_replace('/\.(webp|jpe?g|png)$/i', '-sm.webp', $path);
    return is_file(BASE_DIR . '/' . $sm) ? $sm : $path;
}

/** The first picture of a product: a photo, else a video poster. */
function coverOf(array $p): ?string
{
    foreach ($p['media'] as $m) if ($m['media_kind'] === 'image') return $m['path'];
    foreach ($p['media'] as $m) if ($m['poster']) return $m['poster'];
    return null;
}

/** Safe public shape of a product (no supplier data). */
function productPublic(array $p): array
{
    $cover = coverOf($p);
    $spec = array_filter([$p['capacity'] ? $p['capacity'] . 'L' : '', $p['fuel'] ? FUELS[$p['fuel']] : '', $p['engine_code'] ?: '']);
    return [
        'id'       => (int) $p['id'],
        'slug'     => $p['slug'],
        'url'      => APP_URL . '/part.php?s=' . rawurlencode($p['slug']),
        'title'    => $p['title'],
        'make'     => $p['make'],
        'model'    => $p['model'],
        'code'     => (string) $p['engine_code'],
        'capacity' => (string) $p['capacity'],
        'fuel'     => $p['fuel'] ? FUELS[$p['fuel']] : '',
        'spec'     => implode(' · ', $spec),
        'category' => $p['category'],
        'categoryLabel' => CATEGORIES[$p['category']] ?? ucfirst($p['category']),
        'grade'    => GRADES[(string) $p['grade']] ?? 'Condition to confirm',
        'warranty' => (string) $p['warranty'],
        'description' => (string) $p['description'],
        'partNumber'  => (string) $p['part_number'],
        'price'    => money($p['price']),
        'hasPrice' => $p['price'] !== null && (float) $p['price'] > 0,
        'stock'    => STOCK[$p['stock_status']] ?? $p['stock_status'],
        'reserved' => $p['stock_status'] === 'reserved',
        'thumb'    => $cover ? APP_URL . '/' . thumbOf($cover) : APP_URL . '/assets/img/placeholder.svg',
        'images'   => array_values(array_map(fn($m) => ['src' => APP_URL . '/' . $m['path'], 'thumb' => APP_URL . '/' . thumbOf($m['path'])],
                        array_filter($p['media'], fn($m) => $m['media_kind'] === 'image'))),
        'videos'   => array_values(array_map(fn($m) => ['src' => APP_URL . '/' . $m['path'], 'poster' => $m['poster'] ? APP_URL . '/' . thumbOf($m['poster']) : ''],
                        array_filter($p['media'], fn($m) => $m['media_kind'] === 'video'))),
    ];
}

/** Makes we have in stock, with counts (for the brand chips and filter). @return array<string,int> */
function makesInStock(): array
{
    $rows = getDB()->query("SELECT make, COUNT(*) n FROM products WHERE stock_status IN ('available','reserved') GROUP BY make ORDER BY make")->fetchAll();
    return array_column($rows, 'n', 'make');
}

function categoryCounts(): array
{
    $rows = getDB()->query("SELECT category, COUNT(*) n FROM products WHERE stock_status IN ('available','reserved') GROUP BY category")->fetchAll();
    return array_column($rows, 'n', 'category');
}
