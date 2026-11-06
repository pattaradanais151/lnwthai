<?php
/**
 * Lnwthai — Customer-side order queries (กัน IDOR: ทุก query ผูกกับ user_id)
 * Path: includes/CustomerOrders.php
 * ไม่ส่งออก admin_note, ip, verified_by, evidence_path ให้ลูกค้า
 */
declare(strict_types=1);
defined('LNW_ROOT') || exit;

final class CustomerOrders
{
    public const GROUPS = [
        'all'     => ['label' => 'ทั้งหมด',        'statuses' => []],
        'pending' => ['label' => 'รอชำระเงิน',     'statuses' => ['pending_payment']],
        'review'  => ['label' => 'รอตรวจสอบ',      'statuses' => ['awaiting_verification']],
        'done'    => ['label' => 'สำเร็จ',          'statuses' => ['paid', 'delivered', 'completed']],
        'closed'  => ['label' => 'ยกเลิก/คืนเงิน', 'statuses' => ['cancelled', 'expired', 'refunded']],
    ];

    public static function summary(int $userId): array
    {
        $row = DB::one(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = 'pending_payment'), 0) AS pending,
                    COALESCE(SUM(status = 'awaiting_verification'), 0) AS review
             FROM orders WHERE user_id = ?",
            [$userId]
        ) ?? [];
        return [
            'total'     => (int) ($row['total'] ?? 0),
            'pending'   => (int) ($row['pending'] ?? 0),
            'review'    => (int) ($row['review'] ?? 0),
            'purchases' => (int) DB::value(
                "SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id = oi.order_id
                 WHERE o.user_id = ? AND o.payment_status = 'verified' AND oi.delivery_status = 'delivered'",
                [$userId]
            ),
        ];
    }

    /** @return array{items: array, total: int, page: int, pages: int} */
    public static function paginate(int $userId, string $group, int $page, int $perPage): array
    {
        $statuses = self::GROUPS[$group]['statuses'] ?? [];
        $where = 'o.user_id = ?';
        $params = [$userId];
        if ($statuses !== []) {
            $where .= ' AND o.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
            array_push($params, ...$statuses);
        }
        $total = (int) DB::value('SELECT COUNT(*) FROM orders o WHERE ' . $where, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $items = DB::all(
            'SELECT o.order_number, o.status, o.payment_status, o.grand_total, o.created_at,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
                    (SELECT oi.product_title FROM order_items oi WHERE oi.order_id = o.id ORDER BY oi.id LIMIT 1) AS first_item
             FROM orders o WHERE ' . $where . ' ORDER BY o.created_at DESC, o.id DESC LIMIT ? OFFSET ?',
            [...$params, $perPage, ($page - 1) * $perPage]
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** ค้นด้วย order_number + user_id เสมอ — ป้องกัน IDOR */
    public static function find(int $userId, string $orderNumber): ?array
    {
        return DB::one(
            'SELECT id, order_number, status, payment_status, subtotal, discount_total, grand_total, currency,
                    customer_note, expires_at, paid_at, delivered_at, cancelled_at, refunded_at, created_at
             FROM orders WHERE order_number = ? AND user_id = ? LIMIT 1',
            [$orderNumber, $userId]
        );
    }

    /** เรียกหลัง find() ยืนยันความเป็นเจ้าของแล้วเท่านั้น */
    public static function items(int $orderId): array
    {
        return DB::all(
            "SELECT oi.product_title, oi.game_name, oi.unit_price, oi.quantity, oi.line_total,
                    oi.delivery_status, oi.delivered_at,
                    (SELECT p.slug FROM products p WHERE p.id = oi.product_id AND p.status IN ('active','sold_out')) AS product_slug
             FROM order_items oi WHERE oi.order_id = ? ORDER BY oi.id",
            [$orderId]
        );
    }

    public static function history(int $orderId): array
    {
        return DB::all(
            'SELECT to_status, to_payment_status, created_at FROM order_status_history
             WHERE order_id = ? ORDER BY created_at, id',
            [$orderId]
        );
    }

    public static function payments(int $orderId): array
    {
        return DB::all(
            'SELECT amount, method, status, created_at, reviewed_at, reject_reason
             FROM payment_submissions WHERE order_id = ? ORDER BY created_at DESC, id DESC',
            [$orderId]
        );
    }

    public static function purchases(int $userId): array
    {
        return DB::all(
            "SELECT oi.product_title, oi.game_name, oi.unit_price, oi.delivered_at, o.order_number, o.paid_at
             FROM order_items oi JOIN orders o ON o.id = oi.order_id
             WHERE o.user_id = ? AND o.payment_status = 'verified'
               AND oi.delivery_status = 'delivered' AND oi.game_account_id IS NOT NULL
             ORDER BY oi.delivered_at DESC, oi.id DESC",
            [$userId]
        );
    }
}