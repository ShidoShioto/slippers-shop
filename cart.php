<?php
require_once 'db.php';

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

if (isset($_GET['ajax']) && $_GET['ajax'] === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die(json_encode(['error' => 'csrf']));
    }
    $pid = (int)($_POST['product_id'] ?? 0);
    if ($pid > 0) {
        $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + 1;
        echo json_encode(['success' => true, 'totalItems' => array_sum($_SESSION['cart'])]); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['ajax'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Ошибка безопасности: Неверный CSRF-токен.");
    }
    $action = $_POST['action'] ?? ''; $pid = (int)($_POST['product_id'] ?? 0);
    if ($action === 'add' && $pid > 0) { $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + 1; } 
    elseif ($action === 'minus' && $pid > 0) {
        if (isset($_SESSION['cart'][$pid])) {
            $_SESSION['cart'][$pid]--; if ($_SESSION['cart'][$pid] <= 0) unset($_SESSION['cart'][$pid]);
        }
    } elseif ($action === 'checkout' && !empty($_SESSION['cart'])) {
        $name = trim($_POST['customer_name'] ?? ''); $phone = trim($_POST['phone'] ?? ''); $address = trim($_POST['address'] ?? '');
        if ($name !== '' && $phone !== '' && $address !== '') {
            $ids = array_keys($_SESSION['cart']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)"); $stmt->execute($ids);
            $items = $stmt->fetchAll();
            $total = 0; $summaryParts = [];
            foreach ($items as $it) {
                $qty = $_SESSION['cart'][$it['id']];
                $total += $it['price'] * $qty;
                $summaryParts[] = $it['title'] . " (x$qty)";
            }
            $ins = $pdo->prepare("INSERT INTO orders (user_id, customer_name, phone, address, items_summary, total_price, status) VALUES (?, ?, ?, ?, ?, ?, 'Ждёт отправки')");
            $ins->execute([$_SESSION['user_id'] ?? null, $name, $phone, $address, implode(', ', $summaryParts), $total]);

            $_SESSION['cart'] = []; header("Location: cart.php?ordered=1"); exit;
        }
    }
    header("Location: cart.php"); exit;
}

require_once 'header.php';
$cartItems = []; $totalSum = 0;
if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)"); $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        $qty = $_SESSION['cart'][$r['id']]; $r['qty'] = $qty; $r['subtotal'] = $r['price'] * $qty;
        $totalSum += $r['subtotal']; $cartItems[] = $r;
    }
}
?>

<h2>
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-4px; margin-right:8px;"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
    Инвентарь (Корзина)
</h2>
<?php if (isset($_GET['ordered'])): ?>
    <div class="alert success" style="margin-top:16px;">🎉 Заказ оформлен! Жди, курьер уже надевает скороходные педали.</div>
<?php endif; ?>

<?php if (empty($cartItems)): ?>
    <div class="empty-state-centered" style="margin-top:40px;">
        <span style="font-size:3.5rem;">🕸️</span>
        <h3 style="margin: 10px 0;">Тут пока пусто...</h3>
        <p style="color:#64748b; margin-bottom:16px;">Добавьте парочку базированных тяг из каталога.</p>
        <a href="index.php" class="btn-primary">Пойти залутать тяги</a>
    </div>
<?php else: ?>
    <div class="cart-layout">
        <div class="cart-list">
            <?php foreach ($cartItems as $item): ?>
                <div class="cart-row">
                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="">
                    <div class="cart-row-info">
                        <h4><?= htmlspecialchars($item['title']) ?></h4>
                        <p class="price"><?= str_replace(' ', '&nbsp;', number_format($item['price'], 0, ',', ' ')) ?>&nbsp;₽</p>
                    </div>
                    <div class="cart-qty">
                        <form method="POST" action="cart.php">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="minus">
                            <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                            <button type="submit" class="qty-btn">−</button>
                        </form>
                        <span><?= $item['qty'] ?></span>
                        <form method="POST" action="cart.php">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                            <button type="submit" class="qty-btn">+</button>
                        </form>
                    </div>
                    <div class="cart-subtotal"><strong><?= str_replace(' ', '&nbsp;', number_format($item['subtotal'], 0, ',', ' ')) ?>&nbsp;₽</strong></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="cart-checkout-card">
            <h3>Оформление</h3>
            <p class="checkout-total">С тебя: <strong><?= str_replace(' ', '&nbsp;', number_format($totalSum, 0, ',', ' ')) ?>&nbsp;₽</strong></p>
            <form method="POST" action="cart.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="checkout">
                <div class="form-group"><input type="text" name="customer_name" required value="<?= htmlspecialchars($_SESSION['user_login'] ?? '') ?>" placeholder="Погоняло (Имя)" class="admin-input"></div>
                <div class="form-group"><input type="text" name="phone" required placeholder="Мобила" class="admin-input"></div>
                <div class="form-group"><input type="text" name="address" required placeholder="Куда везти (Адрес)" class="admin-input"></div>
                <button type="submit" class="btn-primary full-width">Подтвердить заказ</button>
            </form>
        </div>
    </div>
<?php endif; ?>
<?php require_once 'footer.php'; ?>