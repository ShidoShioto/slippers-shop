<?php
require_once 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? 'user') !== 'admin') {
    header("Location: index.php");
    exit;
}

$tab = $_GET['tab'] ?? 'orders';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Ошибка безопасности: Неверный CSRF-токен.");
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_order') {
        $orderId = (int)$_POST['order_id'];
        $newStatus = $_POST['status'];
        $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$newStatus, $orderId]);
            
    } elseif ($action === 'update_product_data') {
        $productId = (int)$_POST['product_id'];
        $price = (float)$_POST['price'];
        
        $pdo->prepare("UPDATE products SET price = ? WHERE id = ?")->execute([$price, $productId]);
        
        if (isset($_FILES['new_image']) && $_FILES['new_image']['error'] == 0) {
            $target_dir = "img/";
            if(!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $ext = pathinfo($_FILES["new_image"]["name"], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $ext;
            $target_file = $target_dir . $new_filename;
            
            if(in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'])){
               if (move_uploaded_file($_FILES["new_image"]["tmp_name"], $target_file)) {
                   $pdo->prepare("UPDATE products SET image_url = ? WHERE id = ?")->execute([$target_file, $productId]);
               }
            }
        }
            
    } elseif ($action === 'delete_product') {
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([(int)$_POST['product_id']]);
            
    } elseif ($action === 'add_product') {
        $imagePath = 'img/1.jpg'; 
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
            $target_dir = "img/";
            if(!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $ext = pathinfo($_FILES["image_file"]["name"], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $ext;
            $target_file = $target_dir . $new_filename;
            if(in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'])){
               if (move_uploaded_file($_FILES["image_file"]["tmp_name"], $target_file)) { $imagePath = $target_file; }
            }
        }
        $pdo->prepare("INSERT INTO products (title, description, price, image_url, category) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_POST['title'], $_POST['description'], (float)$_POST['price'], $imagePath, $_POST['category']]);
            
    } elseif ($action === 'delete_review') {
        $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([(int)$_POST['review_id']]);
    } elseif ($action === 'delete_user') {
        $delId = (int)$_POST['delete_user_id'];
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'")->execute([$delId]);
    }
    
    header("Location: admin.php?tab=" . urlencode($tab) . "&success=1");
    exit;
}

require_once 'header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <h1>👑 Кабинет Босса</h1>
    <?php if (isset($_GET['success'])): ?>
        <span style="background:#10b981; color:#fff; padding:6px 16px; border-radius:12px; font-weight:bold;">Изменения сохранены!</span>
    <?php endif; ?>
</div>

<div class="admin-layout">
    <div class="admin-sidebar">
        <a href="?tab=orders" class="<?= $tab==='orders' ? 'active' : '' ?>">📦 Управление Заказами</a>
        <a href="?tab=products" class="<?= $tab==='products' ? 'active' : '' ?>">👟 База Товаров</a>
        <a href="?tab=reviews" class="<?= $tab==='reviews' ? 'active' : '' ?>">💬 Отзывы</a>
        <a href="?tab=users" class="<?= $tab==='users' ? 'active' : '' ?>">👥 Пользователи</a>
    </div>

    <div class="admin-content">
        <?php if ($tab === 'orders'): 
            $orders = $pdo->query("SELECT * FROM orders ORDER BY id DESC")->fetchAll();
        ?>
            <h2>Заказы</h2>
            <div class="table-responsive">
                <table class="admin-table">
                    <tr><th>ID</th><th>Покупатель</th><th>Адрес / Телефон</th><th>Сумма</th><th>Статус</th><th>Действие</th></tr>
                    <?php foreach($orders as $o): ?>
                    <tr>
                        <td><?= $o['id'] ?></td>
                        <td><?= htmlspecialchars($o['customer_name']) ?></td>
                        <td style="font-size:0.85rem;"><?= htmlspecialchars($o['address']) ?><br><?= htmlspecialchars($o['phone']) ?></td>
                        <td><strong><?= str_replace(' ', '&nbsp;', number_format($o['total_price'], 0, ',', ' ')) ?>&nbsp;₽</strong><br><span style="font-size:0.8rem; color:#64748b;"><?= htmlspecialchars($o['items_summary']) ?></span></td>
                        <td>
                            <select name="status" form="status-form-<?= $o['id'] ?>" class="admin-input" style="padding:6px; min-width:140px;">
                                <option <?= $o['status']=='Ждёт отправки'?'selected':'' ?>>Ждёт отправки</option>
                                <option <?= $o['status']=='В пути'?'selected':'' ?>>В пути</option>
                                <option <?= $o['status']=='Доставлен'?'selected':'' ?>>Доставлен</option>
                                <option <?= $o['status']=='Отменен'?'selected':'' ?>>Отменен</option>
                            </select>
                        </td>
                        <td>
                            <form id="status-form-<?= $o['id'] ?>" method="POST" style="margin:0;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="update_order">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <button type="submit" class="btn-primary btn-sm">OK</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

        <?php elseif ($tab === 'products'): 
            $products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
        ?>
            <h2>Все товары на сайте</h2>
            <div class="table-responsive">
                <table class="admin-table">
                    <tr><th>Фото</th><th>Название / Категория</th><th>Настройки (Цена и Фото)</th><th>Действия</th></tr>
                    <?php foreach($products as $p): ?>
                    <tr>
                        <td><img src="<?= htmlspecialchars($p['image_url']) ?>" style="width:70px; height:50px; object-fit:cover; border-radius:6px;"></td>
                        <td style="max-width:200px;">
                            <strong><?= htmlspecialchars($p['title']) ?></strong><br>
                            <span style="font-size: 0.8rem; color: #64748b;"><?= htmlspecialchars($p['category']) ?></span>
                        </td>
                        <td>
                            <form method="POST" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:8px; max-width: 200px;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="update_product_data">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <div style="display:flex; gap:8px;">
                                    <input type="text" name="price" value="<?= $p['price'] ?>" class="admin-input" style="width:90px; padding:6px;" pattern="[0-9]+([\.,][0-9]+)?" title="Введите число">
                                    <button type="submit" class="btn-primary btn-sm">Сохранить</button>
                                </div>
                                <input type="file" name="new_image" accept="image/*" class="admin-input" style="padding:4px; font-size:0.75rem;">
                            </form>
                        </td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Точно удалить товар?');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn-cancel-order btn-sm">Удалить</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            
            <h3 style="margin-top:40px; margin-bottom:16px;">➕ Добавить новые тяги</h3>
            <form method="POST" class="admin-add-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="add_product">
                <div style="display:flex; gap:16px; flex-wrap:wrap;">
                    <div class="form-group" style="flex:1; min-width:200px;"><label>Название</label><input type="text" name="title" required class="admin-input"></div>
                    <div class="form-group" style="flex:1; min-width:100px;"><label>Цена (₽)</label><input type="text" name="price" required class="admin-input" pattern="[0-9]+([\.,][0-9]+)?"></div>
                </div>
                <div style="display:flex; gap:16px; flex-wrap:wrap;">
                    <div class="form-group" style="flex:1; min-width:200px;">
                        <label>Категория</label>
                        <select name="category" class="admin-input">
                            <option>Домашние тапочки</option>
                            <option>Пляжные сланцы</option>
                            <option>Ортопедические шлёпанцы</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1; min-width:200px;">
                        <label>Картинка (Файл)</label>
                        <input type="file" name="image_file" accept="image/*" class="admin-input" style="padding: 10px;">
                    </div>
                </div>
                <div class="form-group"><label>Мемное описание</label><textarea name="description" rows="3" required class="admin-input"></textarea></div>
                <button type="submit" class="btn-primary">Создать товар</button>
            </form>

        <?php elseif ($tab === 'reviews'): 
            $reviews = $pdo->query("SELECT r.*, p.title as prod_title FROM reviews r JOIN products p ON r.product_id = p.id ORDER BY r.id DESC")->fetchAll();
        ?>
            <h2>Отзывы покупателей</h2>
            <div class="table-responsive">
                <table class="admin-table">
                    <tr><th>Товар</th><th>Автор</th><th>Оценка</th><th>Текст</th><th>Удалить</th></tr>
                    <?php foreach($reviews as $r): ?>
                    <tr>
                        <td style="font-size:0.85rem; max-width:150px;"><?= htmlspecialchars($r['prod_title']) ?></td>
                        <td><strong><?= htmlspecialchars($r['user_login']) ?></strong></td>
                        <td style="color:#f59e0b;">★ <?= $r['rating'] ?></td>
                        <td style="max-width:300px; font-size:0.9rem;"><?= htmlspecialchars($r['comment']) ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Удалить этот отзыв?');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="delete_review">
                                <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn-cancel-order btn-sm">Удалить</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

        <?php elseif ($tab === 'users'): 
            $usersList = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
        ?>
            <h2>База пользователей</h2>
            <div class="table-responsive">
                <table class="admin-table">
                    <tr><th>ID</th><th>Логин</th><th>Email</th><th>Размер ноги</th><th>Роль</th><th>Регистрация</th><th>Действия</th></tr>
                    <?php foreach($usersList as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><strong><?= htmlspecialchars($u['login']) ?></strong></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['shoe_size']) ?> EU</td>
                        <td><span class="badge <?= $u['role']==='admin'?'badge-admin':'' ?>"><?= $u['role'] ?></span></td>
                        <td style="font-size:0.85rem;"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <?php if ($u['role'] !== 'admin'): ?>
                            <form method="POST" onsubmit="return confirm('Точно удалить этого гигачада?');" style="margin:0;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="delete_user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn-cancel-order btn-sm">Удалить</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>