<?php
/**
 * Lnwthai — Public catalog queries
 * Path: includes/Catalog.php
 * อ่านอย่างเดียว · ไม่ SELECT secret_payload ของ game_accounts (นับสต็อกด้วย COUNT เท่านั้น)
 */
declare(strict_types=1);
defined('LNW_ROOT') || exit;

final class Catalog
{
    public const SORTS = [
        'newest'     => 'p.created_at DESC, p.id DESC',
        'price_asc'  => 'p.price ASC, p.id DESC',
        'price_desc' => 'p.price DESC, p.id DESC',
    ];
    public const SORT_LABELS = [
        'newest'     => 'ใหม่ล่าสุด',
        'price_asc'  => 'ราคาต่ำ → สูง',
        'price_desc' => 'ราคาสูง → ต่ำ',
    ];
    public const STOCK_LABELS = ['' => 'ทั้งหมด', 'in' => 'พร้อมจำหน่าย', 'out' => 'สินค้าหมด'];

    private const VISIBLE = "p.status IN ('active','sold_out')";
    private const STOCK = "(SELECT COUNT(*) FROM game_accounts ga WHERE ga.product_id = p.id AND ga.status = 'available')";
    private const FROM = ' FROM products p JOIN games g ON g.id = p.game_id AND g.is_active = 1';
    private const CARD_SELECT = 'SELECT p.id, p.slug, p.sku, p.title, p.short_description, p.platform, p.server_region,
            p.level_rank, p.price, p.compare_at_price, p.status, p.is_featured, p.is_special_offer, p.created_at,
            g.name AS game_name, g.slug AS game_slug,
            (SELECT pi.public_url FROM product_images pi WHERE pi.product_id = p.id
              ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS image_url, '
        . self::STOCK . ' AS stock' . self::FROM;

    public static function games(bool $featuredOnly = false): array
    {
        return DB::all(
            'SELECT g.id, g.name, g.slug, g.short_description, g.icon_path,
                    (SELECT COUNT(*) FROM products p WHERE p.game_id = g.id AND ' . self::VISIBLE . ') AS product_count
             FROM games g WHERE g.is_active = 1' . ($featuredOnly ? ' AND g.is_featured = 1' : '') . '
             ORDER BY g.sort_order, g.name'
        );
    }

    public static function featured(int $limit): array
    {
        return self::section('p.is_featured = 1', [], $limit);
    }

    public static function offers(int $limit): array
    {
        return self::section('p.is_special_offer = 1', [], $limit);
    }

    public static function latest(int $limit): array
    {
        return DB::all(self::CARD_SELECT . ' WHERE ' . self::VISIBLE . ' ORDER BY p.created_at DESC, p.id DESC LIMIT ?', [$limit]);
    }

    public static function related(int $gameId, int $excludeId, int $limit): array
    {
        return self::section('p.game_id = ? AND p.id <> ?', [$gameId, $excludeId], $limit);
    }

    private static function section(string $extra, array $params, int $limit): array
    {
        return DB::all(
            self::CARD_SELECT . ' WHERE ' . self::VISIBLE . ' AND ' . $extra
            . " ORDER BY (p.status = 'active') DESC, p.created_at DESC, p.id DESC LIMIT ?",
            [...$params, $limit]
        );
    }

    /** ทำความสะอาดค่าจาก $_GET */
    public static function filters(array $in): array
    {
        $str = static fn(string $k, int $max): string => is_string($in[$k] ?? null) ? mb_substr(trim($in[$k]), 0, $max) : '';
        $money = static function (string $k) use ($in): ?string {
            $v = $in[$k] ?? '';
            if (!is_string($v)) {
                return null;
            }
            $v = trim(str_replace(',', '', $v));
            return preg_match('/^\d{1,9}(\.\d{1,2})?$/', $v) === 1 ? $v : null;
        };

        $q = (string) preg_replace('/\s+/u', ' ', $str('q', 100));
        $game = $str('game', 140);
        if (preg_match('/^[a-z0-9-]+$/', $game) !== 1) {
            $game = '';
        }
        $stock = $str('stock', 5);
        if (!isset(self::STOCK_LABELS[$stock])) {
            $stock = '';
        }
        $sort = $str('sort', 20);
        if (!isset(self::SORTS[$sort])) {
            $sort = 'newest';
        }
        $min = $money('min');
        $max = $money('max');
        if ($min !== null && $max !== null && (float) $min > (float) $max) {
            [$min, $max] = [$max, $min];
        }
        $page = filter_var($in['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]) ?: 1;

        return ['q' => $q, 'game' => $game, 'min' => $min, 'max' => $max, 'stock' => $stock, 'sort' => $sort, 'page' => $page];
    }

    /** พารามิเตอร์สำหรับสร้างลิงก์ (ไม่รวม page) */
    public static function params(array $f): array
    {
        return [
            'q'     => $f['q'] !== '' ? $f['q'] : null,
            'game'  => $f['game'] !== '' ? $f['game'] : null,
            'min'   => $f['min'],
            'max'   => $f['max'],
            'stock' => $f['stock'] !== '' ? $f['stock'] : null,
            'sort'  => $f['sort'] !== 'newest' ? $f['sort'] : null,
        ];
    }

    /** @return array{items: array, total: int, page: int, pages: int} */
    public static function search(array $f, int $perPage): array
    {
        $where = [self::VISIBLE];
        $params = [];

        if ($f['q'] !== '') {
            $like = '%' . strtr($f['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $where[] = "(p.title LIKE ? ESCAPE '!' OR p.title_en LIKE ? ESCAPE '!' OR g.name LIKE ? ESCAPE '!'
                         OR g.name_en LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!')";
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($f['game'] !== '') {
            $where[] = 'g.slug = ?';
            $params[] = $f['game'];
        }
        if ($f['min'] !== null) {
            $where[] = 'p.price >= ?';
            $params[] = $f['min'];
        }
        if ($f['max'] !== null) {
            $where[] = 'p.price <= ?';
            $params[] = $f['max'];
        }
        if ($f['stock'] === 'in') {
            $where[] = "p.status = 'active' AND " . self::STOCK . ' > 0';
        } elseif ($f['stock'] === 'out') {
            $where[] = "(p.status = 'sold_out' OR " . self::STOCK . ' = 0)';
        }

        $sqlWhere = ' WHERE ' . implode(' AND ', $where);
        $total = (int) DB::value('SELECT COUNT(*)' . self::FROM . $sqlWhere, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min((int) $f['page'], $pages);

        $items = DB::all(
            self::CARD_SELECT . $sqlWhere . ' ORDER BY ' . self::SORTS[$f['sort']] . ' LIMIT ? OFFSET ?',
            [...$params, $perPage, ($page - 1) * $perPage]
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public static function findBySlug(string $slug): ?array
    {
        return DB::one(
            'SELECT p.id, p.game_id, p.sku, p.slug, p.title, p.title_en, p.short_description, p.description,
                    p.platform, p.server_region, p.level_rank, p.items_summary, p.attributes,
                    p.price, p.compare_at_price, p.status, p.created_at,
                    g.name AS game_name, g.slug AS game_slug, c.name AS category_name, '
            . self::STOCK . ' AS stock' . self::FROM . '
             LEFT JOIN categories c ON c.id = p.category_id AND c.is_active = 1
             WHERE p.slug = ? AND ' . self::VISIBLE . ' LIMIT 1',
            [$slug]
        );
    }

    public static function images(int $productId): array
    {
        return DB::all(
            'SELECT public_url, alt_text FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order, id',
            [$productId]
        );
    }

    public static function isPurchasable(array $p): bool
    {
        return ($p['status'] ?? '') === 'active' && (int) ($p['stock'] ?? 0) > 0;
    }
}