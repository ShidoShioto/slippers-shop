<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Ошибка безопасности: Неверный CSRF-токен.");
    }
    
    if (isset($_POST['update_shoe_size'])) {
        $newSize = trim($_POST['shoe_size'] ?? '40');
        $upd = $pdo->prepare("UPDATE users SET shoe_size = ? WHERE id = ?");
        $upd->execute([$newSize, $_SESSION['user_id']]);
        header("Location: profile.php?size_updated=1"); exit;
    }

    if (isset($_POST['cancel_order_id'])) {
        $cancelId = (int)$_POST['cancel_order_id'];
        $upd = $pdo->prepare("UPDATE orders SET status = 'Отменен' WHERE id = ? AND user_id = ? AND status = 'Ждёт отправки'");
        $upd->execute([$cancelId, $_SESSION['user_id']]);
        header("Location: profile.php"); exit;
    }
}

$stmt = $pdo->prepare("SELECT id, login, email, shoe_size, created_at FROM users WHERE id = :id");
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

$ordStmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = :uid ORDER BY id DESC");
$ordStmt->execute(['uid' => $_SESSION['user_id']]);
$orders = $ordStmt->fetchAll();

$availableSizes = ['37', '38', '39', '40', '41', '42', '43', '44-45'];

require_once 'header.php';
?>

<div class="profile-dashboard">
    <div class="profile-sidebar">
        <div class="profile-card profile-card-modern">
            <div class="profile-avatar">
                <?= mb_strtoupper(mb_substr($user['login'], 0, 1)) ?>
            </div>
            <h2><?= htmlspecialchars($user['login']) ?></h2>
            <div class="profile-info-list">
                <div class="info-row"><span>ID:</span> <strong><?= (int)$user['id'] ?></strong></div>
                <div class="info-row"><span>Email:</span> <strong><?= htmlspecialchars($user['email']) ?></strong></div>
                <div class="info-row"><span>В системе с:</span> <strong><?= date('d.m.Y', strtotime($user['created_at'])) ?></strong></div>
            </div>

            <div class="profile-size-box" style="text-align: left; margin-bottom: 20px; border-top: 1px solid #f1f5f9; padding-top: 16px;">
                <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:8px; color:#475569;">Размер ваших тяг:</label>
                <form method="POST" style="display:flex; gap:8px;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <select name="shoe_size" style="flex:1; padding:8px 10px; border-radius:8px; border:1px solid #cbd5e1; background:#f8fafc; font-weight:600;">
                        <?php foreach($availableSizes as $s): ?>
                            <option value="<?= $s ?>" <?= ($user['shoe_size'] === $s) ? 'selected' : '' ?>><?= $s ?> EU</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="update_shoe_size" class="btn-primary" style="padding: 8px 12px; font-size: 0.85rem;">Сохранить</button>
                </form>
                <?php if(isset($_GET['size_updated'])): ?>
                    <div style="color:#10b981; font-size:0.8rem; margin-top:6px;">✅ Размер сохранен!</div>
                <?php endif; ?>
            </div>

            <div class="profile-actions-column">
                <a href="index.php" class="btn-primary" style="text-align:center;">За новыми тягами</a>
                <a href="logout.php" class="btn-secondary btn-danger" style="text-align:center;">Выйти из аккаунта</a>
            </div>
        </div>
    </div>

    <div class="profile-main">
        <div class="orders-card orders-modern">
            <div class="orders-header">
                <h2>Мои заказы</h2>
                <span class="badge-count"><?= count($orders) ?></span>
            </div>
            
            <?php if (empty($orders)): ?>
                <div class="empty-orders">
                    <span style="font-size:3rem; display:block; margin-bottom:10px;">📦</span>
                    <p>Тут пока пусто, пора бы сделать заказ!</p>
                </div>
            <?php else: ?>
                <div class="orders-list">
                    <?php foreach ($orders as $o): ?>
                        <div class="order-item-modern">
                            <div class="order-head">
                                <div class="order-meta">
                                    <span class="order-id">Заказ #<?= $o['id'] ?></span>
                                    <span class="order-date">🗓 <?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></span>
                                </div>
                                <?php if($o['status'] === 'Отменен'): ?>
                                    <span class="badge status-cancelled">❌ Отменен</span>
                                <?php else: ?>
                                    <span class="badge status-processing">⏳ <?= htmlspecialchars($o['status']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="order-body">
                                <div class="order-items-box">
                                    <span style="font-size:1.2rem; margin-right:8px;">🛍️</span> 
                                    <?= htmlspecialchars($o['items_summary']) ?>
                                </div>
                                <div class="order-footer-info">
                                    <span class="order-address">📍 Доставка: <?= htmlspecialchars($o['address']) ?></span>
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <strong class="order-price"><?= str_replace(' ', '&nbsp;', number_format($o['total_price'], 0, ',', ' ')) ?>&nbsp;₽</strong>
                                        <?php if($o['status'] === 'Ждёт отправки'): ?>
                                            <form method="POST" onsubmit="return confirm('Точно отменяем заказ?');" style="margin:0;">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="cancel_order_id" value="<?= $o['id'] ?>">
                                                <button type="submit" class="btn-cancel-order">Отменить</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>