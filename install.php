<?php
require_once 'db.php';

// 1. Создание таблиц
$pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `login` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `shoe_size` VARCHAR(20) DEFAULT '40',
    `role` VARCHAR(20) DEFAULT 'user',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

try { $pdo->exec("ALTER TABLE users ADD COLUMN shoe_size VARCHAR(20) DEFAULT '40'"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'user'"); } catch (Exception $e) {}

$checkAdmin = $pdo->query("SELECT id FROM users WHERE login = 'Босс'")->fetch();
if (!$checkAdmin) {
    $hash = password_hash('boss123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (login, email, password_hash, role) VALUES ('Босс', 'boss@kefteme.ru', '$hash', 'admin')");
}

$pdo->exec("CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `customer_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(40) NOT NULL,
    `address` VARCHAR(255) NOT NULL,
    `items_summary` TEXT NOT NULL,
    `total_price` DECIMAL(10, 2) NOT NULL,
    `status` VARCHAR(50) DEFAULT 'Ждёт отправки',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `user_login` VARCHAR(50) NOT NULL,
    `rating` INT NOT NULL,
    `comment` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

try { $pdo->exec("ALTER TABLE orders ADD COLUMN status VARCHAR(50) DEFAULT 'Ждёт отправки'"); } catch (Exception $e) {}

// 2. Восстанавливаем товары
$prodCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
if ($prodCount == 0) {
    $seedProducts = [
        ['Бархатные тяги «Кефтеме»', 'Что за тяги такие бархатные, фуф... Надеваешь и сразу хочется сказать «Кефтеме». Ультра-флекс для домашнего чилла.', 890.00, 'img/1.jpg', 'Домашние тапочки'],
        ['Сланцы «Гигачад Базированные»', 'Одобрено ассоциацией альфа-самцов. Идеально подходят для того, чтобы ходить по лужам с каменным лицом. Абсолютная база.', 650.00, 'img/2.jpg', 'Пляжные сланцы'],
        ['Ортопедические подкрадули «Ауф»', 'Анатомическая стелька для бесшумного ночного рейда к холодильнику. +100 к скрытности, +50 к ловкости.', 2450.00, 'img/3.jpg', 'Ортопедические шлёпанцы'],
        ['Меховые тапки «Большой Шлёпа»', 'Мягкие, как пузико русского кота Шлёпы. Вызывают привыкание и желание лежать.', 1600.00, 'img/4.jpg', 'Домашние тапочки'],
        ['Скороходные педали «Газ в палас»', 'Спортивная аэродинамика для максимальной скорости при побеге от проблем. Не скользят, не тонут.', 1100.00, 'img/5.jpg', 'Пляжные сланцы'],
        ['Шлёпанцы «Дед инсайд» (1000-7)', 'Подошва с эффектом памяти запоминает всю боль этого мира. Максимальный комфорт для гуль-мода.', 2900.00, 'img/6.jpg', 'Ортопедические шлёпанцы'],
    ];
    $ins = $pdo->prepare("INSERT INTO products (title, description, price, image_url, category) VALUES (?, ?, ?, ?, ?)");
    foreach ($seedProducts as $p) { $ins->execute($p); }
}

// Функция для безопасного отображения цен (без разрывов строк)
function formatPrice($price) {
    return str_replace(' ', '&nbsp;', number_format($price, 0, ',', ' ')) . '&nbsp;₽';
}

$files = [];

$files['header.php'] = <<<'EOD'
<?php
require_once 'db.php';

// ГЕНЕРАЦИЯ CSRF-ТОКЕНА ДЛЯ ЗАЩИТЫ ОТ АТАК
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
$cartCount = array_sum($_SESSION['cart']);
$userRole = $_SESSION['user_role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>У шлёпок</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body>
<header class="navbar">
    <div class="container nav-container">
        <!-- Логотип слева -->
        <a href="index.php" class="logo">
            <img src="img/logo.png" alt="Логотип" class="site-logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
            <span class="fallback-logo" style="display:none;">🗿</span>
            У шлёпок
        </a>
        
        <!-- Ссылки (ПК - справа, Мобилка - снизу) -->
        <nav class="nav-links">
            <a href="index.php">Каталог</a>
            <?php if ($userRole === 'admin'): ?>
                <a href="admin.php" style="color: #f59e0b; font-weight: 800;">👑 Админка</a>
            <?php endif; ?>
            <a href="cart.php" class="cart-link" id="cartLink">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                <span class="cart-text">Корзина</span>
                <span class="cart-badge" id="cartBadge" style="<?= $cartCount == 0 ? 'display:none;' : '' ?>"><?= $cartCount ?></span>
            </a>
        </nav>

        <!-- Профиль (ПК - справа, Мобилка - справа от логотипа) -->
        <div class="auth-links">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="profile.php" class="user-pill">
                    <div class="micro-avatar"><?= mb_strtoupper(mb_substr($_SESSION['user_login'], 0, 1)) ?></div>
                    <span class="user-pill-name"><?= htmlspecialchars($_SESSION['user_login']) ?></span>
                </a>
            <?php else: ?>
                <a href="login.php" class="auth-link-simple">Вход</a>
                <a href="register.php" class="btn-primary btn-sm btn-register">Регистрация</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<main class="container main-content">
<div id="toast" class="toast">Тяги добавлены в инвентарь! 🚀</div>
EOD;

$files['footer.php'] = <<<'EOD'
</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <h4 style="display:flex; align-items:center; gap:8px;">
                <img src="img/logo.png" alt="" class="site-logo-img" style="height:24px!important; max-width:24px!important;" onerror="this.style.display='none'">
                У шлёпок
            </h4>
            <p>Самые базированные подкрадули и бархатные тяги на всём Диком Интернете.</p>
        </div>
        <div>
            <h4>Навигация</h4>
            <p><a href="index.php">Витрина тяг</a></p>
            <p><a href="cart.php">Корзина</a></p>
            <p><a href="profile.php">Штаб-квартира (Профиль)</a></p>
        </div>
        <div>
            <h4>Связь с реальностью</h4>
            <p>📞 8 (800) 555-35-35</p>
        </div>
    </div>
</footer>
<script>
function addToCart(productId) {
    fetch('cart.php?ajax=add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=add&product_id=' + productId + '&csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>'
    }).then(response => response.json()).then(data => {
        if(data.success) {
            let badge = document.getElementById('cartBadge');
            badge.innerText = data.totalItems;
            badge.style.display = 'inline-block';
            let cartLink = document.getElementById('cartLink');
            cartLink.classList.add('bounce');
            setTimeout(() => cartLink.classList.remove('bounce'), 500);
            showToast();
        }
    });
}
function showToast() {
    const toast = document.getElementById('toast');
    toast.classList.add('show');
    setTimeout(() => { toast.classList.remove('show'); }, 3000);
}
</script>
</body>
</html>
EOD;

$files['admin.php'] = <<<'EOD'
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
EOD;

$files['index.php'] = <<<'EOD'
<?php
require_once 'header.php';

// Функция для безопасного отображения цен без разрывов строк
function formatPrice($price) {
    return str_replace(' ', '&nbsp;', number_format($price, 0, ',', ' ')) . '&nbsp;₽';
}

$category = trim($_GET['category'] ?? '');
$search   = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM products WHERE 1=1";
$params = [];

if ($category !== '') { $sql .= " AND category = :cat"; $params['cat'] = $category; }
if ($search !== '') { $sql .= " AND (title LIKE :q OR description LIKE :q2)"; $params['q'] = "%$search%"; $params['q2'] = "%$search%"; }
$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$catStmt = $pdo->query("SELECT DISTINCT category FROM products");
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<section class="hero">
    <div class="hero-content">
        <h1>Время обновить тяги</h1>
        <p>Кефтеме-качество и гигачад-стиль. Доставка быстрее, чем ты скажешь «Ауф».</p>
    </div>
</section>

<form method="GET" action="index.php" class="toolbar-bar">
    <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>"><?php endif; ?>
    <div class="search-wrap"><input type="text" name="q" placeholder="Поиск тех самых тяг..." value="<?= htmlspecialchars($search) ?>"></div>
    <div class="sort-wrap"><button type="submit" class="btn-primary search-btn">Найти</button></div>
</form>

<div class="filters">
    <a href="index.php" class="filter-chip <?= $category === '' ? 'active' : '' ?>">Все подкрадули</a>
    <?php foreach ($categories as $cat): ?>
        <a href="index.php?category=<?= urlencode($cat) ?>" class="filter-chip <?= $category === $cat ? 'active' : '' ?>"><?= htmlspecialchars($cat) ?></a>
    <?php endforeach; ?>
</div>

<div class="product-grid">
    <?php foreach ($products as $item): 
        $rStmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(id) as count_rev FROM reviews WHERE product_id = ?");
        $rStmt->execute([$item['id']]);
        $revData = $rStmt->fetch();
        $avgRating = $revData['count_rev'] > 0 ? round($revData['avg_rating'], 1) : 0;
    ?>
        <article class="product-card">
            <div class="card-img-link">
                <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
            </div>
            <div class="card-body">
                <div class="card-meta">
                    <span class="badge"><?= htmlspecialchars($item['category']) ?></span>
                    <?php if($avgRating > 0): ?>
                        <span class="rating">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="1" style="vertical-align:-2px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg> 
                            <?= $avgRating ?> (<?= $revData['count_rev'] ?>)
                        </span>
                    <?php endif; ?>
                </div>
                <h3>
                    <a href="product.php?id=<?= (int)$item['id'] ?>" class="stretched-link">
                        <?= htmlspecialchars($item['title']) ?>
                    </a>
                </h3>
                <p class="desc"><?= htmlspecialchars(mb_strimwidth($item['description'], 0, 85, '...')) ?></p>
                <div class="card-footer">
                    <span class="price"><?= formatPrice($item['price']) ?></span>
                    <button type="button" class="btn-primary" onclick="addToCart(<?= $item['id'] ?>)">В корзину</button>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<?php require_once 'footer.php'; ?>
EOD;

$files['product.php'] = <<<'EOD'
<?php
require_once 'header.php';

function formatPrice($price) {
    return str_replace(' ', '&nbsp;', number_format($price, 0, ',', ' ')) . '&nbsp;₽';
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    echo "<div class='alert error'>Тяги не найдены.</div>"; require_once 'footer.php'; exit;
}

$userSize = '40';
if (isset($_SESSION['user_id'])) {
    $szStmt = $pdo->prepare("SELECT shoe_size FROM users WHERE id = ?");
    $szStmt->execute([$_SESSION['user_id']]);
    $szRow = $szStmt->fetch();
    if ($szRow && !empty($szRow['shoe_size'])) { $userSize = $szRow['shoe_size']; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Ошибка безопасности: Неверный CSRF-токен.");
    }
    if (isset($_SESSION['user_id'])) {
        $rating = (int)$_POST['rating'];
        $comment = trim($_POST['comment']);
        if ($rating >= 1 && $rating <= 5 && $comment !== '') {
            $ins = $pdo->prepare("INSERT INTO reviews (product_id, user_id, user_login, rating, comment) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$id, $_SESSION['user_id'], $_SESSION['user_login'], $rating, $comment]);
            header("Location: product.php?id=$id"); exit;
        }
    }
}

$rStmt = $pdo->prepare("SELECT * FROM reviews WHERE product_id = :id ORDER BY id DESC");
$rStmt->execute(['id' => $id]);
$reviews = $rStmt->fetchAll();
$avgRating = count($reviews) > 0 ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0;
$availableSizes = ['37', '38', '39', '40', '41', '42', '43', '44-45'];
?>

<div class="product-detail">
    <div class="detail-image">
        <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['title']) ?>">
    </div>
    <div class="detail-info">
        <span class="badge"><?= htmlspecialchars($product['category']) ?></span>
        <h1><?= htmlspecialchars($product['title']) ?></h1>
        <div class="detail-rating">
            <span class="star-rating">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="1" style="vertical-align:-2px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg> 
                <?= count($reviews) > 0 ? round($avgRating, 1) : 'Нет оценок' ?>
            </span> 
            <span class="review-count">(Отзывов: <?= count($reviews) ?>)</span>
        </div>
        <p class="detail-price"><?= formatPrice($product['price']) ?></p>
        <p class="detail-desc"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
        
        <div class="size-selector">
            <label>Выбери размер тяг (EU):</label>
            <div class="size-chips">
                <?php foreach($availableSizes as $sz): ?>
                    <span class="size-chip <?= ($userSize === $sz) ? 'active' : '' ?>" onclick="selectSize(this)"><?= $sz ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="detail-actions">
            <button type="button" class="btn-primary btn-lg" onclick="addToCart(<?= $product['id'] ?>)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px; margin-right:6px;"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                Залутать в корзину
            </button>
            <a href="index.php" class="btn-secondary btn-lg">Назад</a>
        </div>
    </div>
</div>

<script>
function selectSize(el) {
    document.querySelectorAll('.size-chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
}
</script>

<div class="reviews-section">
    <h2 style="margin-bottom: 20px;">Что говорят пацаны (Отзывы)</h2>
    <div class="reviews-layout">
        <div class="reviews-list-col">
            <?php if (empty($reviews)): ?>
                <div class="empty-reviews">
                    <span style="font-size: 3rem;">🦗</span>
                    <p>Тут пока тихо... Оставь отзыв первым!</p>
                </div>
            <?php else: ?>
                <?php foreach ($reviews as $rev): 
                    $filledStars = $rev['rating'];
                    $emptyStars = 5 - $filledStars;
                ?>
                    <div class="review-card">
                        <div class="review-avatar"><?= mb_strtoupper(mb_substr($rev['user_login'], 0, 1)) ?></div>
                        <div class="review-content">
                            <div class="review-header">
                                <strong><?= htmlspecialchars($rev['user_login']) ?></strong>
                                <span class="review-date"><?= date('d.m.Y', strtotime($rev['created_at'])) ?></span>
                            </div>
                            <div class="review-stars">
                                <?php for($i=0; $i<$filledStars; $i++): ?>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="1"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                <?php endfor; ?>
                                <?php for($i=0; $i<$emptyStars; $i++): ?>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                <?php endfor; ?>
                            </div>
                            <p class="review-text"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="review-form-col">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="review-form-modern">
                    <h3>Поделись базой</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="form-group">
                            <label>Как оценишь тяги?</label>
                            <div class="star-rating-widget">
                                <input type="radio" id="star5" name="rating" value="5" required><label for="star5" title="5 - Ауф, пушка!">★</label>
                                <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="4 - Четко">★</label>
                                <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="3 - Ну такое">★</label>
                                <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="2 - Кринж">★</label>
                                <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="1 - Полная шляпа">★</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <textarea name="comment" required placeholder="Раскидай по фактам..." rows="3"></textarea>
                        </div>
                        <button type="submit" name="submit_review" class="btn-primary full-width">Отправить отзыв</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="review-form-modern" style="text-align:center;">
                    <span style="font-size: 3rem;">🕵️</span>
                    <h3>Войди в систему</h3>
                    <p style="color:#64748b; margin-bottom:15px;">Только зарегистрированные гигачады могут оставлять отзывы.</p>
                    <a href="login.php" class="btn-primary">Авторизоваться</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
EOD;

$files['cart.php'] = <<<'EOD'
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
EOD;

$files['profile.php'] = <<<'EOD'
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
EOD;

$files['login.php'] = <<<'EOD'
<?php
require_once 'db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Ошибка безопасности: Неверный CSRF-токен.");
    }
    $loginOrEmail = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($loginOrEmail === '' || $password === '') {
        $error = 'Введите логин/Email и пароль.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE login = :login OR email = :email LIMIT 1");
        $stmt->execute(['login' => $loginOrEmail, 'email' => $loginOrEmail]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_login'] = $user['login'];
            $_SESSION['user_role'] = $user['role'] ?? 'user';
            header("Location: profile.php"); exit;
        } else { $error = 'Неверный логин или пароль.'; }
    }
}
require_once 'header.php';
?>
<div class="auth-card">
    <h2>Вход в аккаунт</h2>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="form-group"><label>Логин или Email</label><input type="text" name="login" required value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" class="admin-input"></div>
        <div class="form-group"><label>Пароль</label><input type="password" name="password" required class="admin-input"></div>
        <button type="submit" class="btn-primary full-width">Войти</button>
    </form>
    <p class="auth-switch" style="text-align:center; margin-top:14px;">Нет аккаунта? <a href="register.php" style="color:#0284c7; font-weight:bold; text-decoration:none;">Создать аккаунт</a></p>
</div>
<?php require_once 'footer.php'; ?>
EOD;

$files['register.php'] = <<<'EOD'
<?php
require_once 'db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Ошибка безопасности: Неверный CSRF-токен.");
    }
    $login = trim($_POST['login'] ?? ''); $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; $password_confirm = $_POST['password_confirm'] ?? '';
    if ($login === '' || $email === '' || $password === '') { $error = 'Заполните все обязательные поля.'; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = 'Некорректный формат Email.'; }
    elseif (mb_strlen($password) < 6) { $error = 'Пароль минимум 6 символов.'; }
    elseif ($password !== $password_confirm) { $error = 'Пароли не совпадают.'; }
    else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE login = :login OR email = :email");
        $stmt->execute(['login' => $login, 'email' => $email]);
        if ($stmt->fetch()) { $error = 'Пользователь с таким логином/Email уже существует.'; }
        else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (login, email, password_hash, role) VALUES (?, ?, ?, 'user')")->execute([$login, $email, $hash]);
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_login'] = $login;
            $_SESSION['user_role'] = 'user';
            
            header("Location: profile.php"); exit;
        }
    }
}
require_once 'header.php';
?>
<div class="auth-card">
    <h2>Регистрация</h2>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST" action="register.php">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="form-group"><label>Логин</label><input type="text" name="login" required minlength="3" value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" class="admin-input"></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" class="admin-input"></div>
        <div class="form-group"><label>Пароль (от 6 символов)</label><input type="password" name="password" required minlength="6" class="admin-input"></div>
        <div class="form-group"><label>Повторите пароль</label><input type="password" name="password_confirm" required minlength="6" class="admin-input"></div>
        <button type="submit" class="btn-primary full-width">Зарегистрироваться</button>
    </form>
    <p class="auth-switch" style="text-align:center; margin-top:14px;">Уже есть аккаунт? <a href="login.php" style="color:#0284c7; font-weight:bold; text-decoration:none;">Войти</a></p>
</div>
<?php require_once 'footer.php'; ?>
EOD;

$files['style.css'] = <<<'EOD'
* { box-sizing: border-box; margin: 0; padding: 0; }
body { 
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; 
    background-color: #e5eff8;
    background-image: url('img/bg.jpg');
    background-repeat: repeat-y;
    background-position: center top;
    background-size: 1920px auto;
    color: #1e293b; 
    line-height: 1.5; 
    display: flex; flex-direction: column; min-height: 100vh; 
}

.main-content { 
    background-color: #e5eff8;
    border-radius: 0;
    box-shadow: none;
    padding: 24px 0 56px 0;
    flex: 1; 
    position: relative; 
}

.container { 
    max-width: 1080px; 
    margin: 0 auto; 
    width: 100%; 
    padding: 0 16px; 
}

/* =========================================
   ШАПКА (ПК - классический вид)
   ========================================= */
.navbar { background: rgba(255,255,255,0.96); backdrop-filter: blur(12px); box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); position: sticky; top: 0; z-index: 100; border-bottom: 1px solid rgba(255,255,255,0.5); }

.nav-container { 
    display: flex; 
    align-items: center; 
    padding: 8px 16px; 
}

.logo { margin-right: auto; font-size: 1.6rem; font-weight: 900; color: #0f172a; text-decoration: none; letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
.site-logo-img { max-height: 38px !important; width: auto !important; max-width: 200px !important; object-fit: contain; border-radius: 6px; display: block; }
.fallback-logo { font-size: 2rem; transform: translateY(-2px); }

/* Ссылки перед профилем на ПК */
.nav-links { display: flex; align-items: center; gap: 16px; margin-right: 16px; }
.nav-links a { text-decoration: none; color: #334155; font-weight: 600; font-size: 0.95rem; transition: color 0.2s; }
.nav-links a:hover { color: #0284c7; }

/* Кнопки авторизации/профиля справа на ПК */
.auth-links { display: flex; align-items: center; gap: 12px; }
.auth-link-simple { text-decoration: none; color: #334155; font-weight: 600; font-size: 0.95rem; transition: color 0.2s; }
.auth-link-simple:hover { color: #0284c7; }

.cart-link { background: #e0f2fe; color: #0369a1 !important; padding: 8px 16px; border-radius: 12px; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #bae6fd; transition: transform 0.2s; }
.cart-link:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(2, 132, 199, 0.15); }
.cart-badge { background: #0284c7; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; font-weight: 700; }

.user-pill { background: #fff; padding: 6px 14px 6px 6px; border-radius: 30px; display: flex; align-items: center; gap: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.02); text-decoration: none; color: #0f172a; font-weight: 600; transition: border-color 0.2s; }
.user-pill:hover { border-color: #cbd5e1; }
.micro-avatar { width: 28px; height: 28px; background: #0284c7; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; }

@keyframes bounceCart { 0% {transform: scale(1);} 50% {transform: scale(1.2);} 100% {transform: scale(1);} }
.bounce { animation: bounceCart 0.4s ease; }

/* Кнопки */
.btn-primary { background: #0284c7; color: #fff !important; padding: 10px 20px; border-radius: 12px; border: none; cursor: pointer; text-decoration: none; font-weight: 600; display: inline-block; transition: all 0.2s; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25); text-align: center; }
.btn-primary:hover { background: #0369a1; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(2, 132, 199, 0.35); }
.btn-secondary { background: #f1f5f9; color: #334155 !important; padding: 10px 16px; border-radius: 12px; text-decoration: none; font-weight: 600; border: 1px solid #e2e8f0; transition: all 0.2s; text-align: center; }
.btn-secondary:hover { background: #e2e8f0; transform: translateY(-2px); }
.btn-danger { color: #ef4444 !important; border-color: #fca5a5; }
.btn-danger:hover { background: #fef2f2; border-color: #f87171; }
.btn-lg { padding: 14px 24px; font-size: 1.05rem; }
.full-width { width: 100%; padding: 14px; font-size: 1rem; }
.btn-sm { padding: 6px 12px; font-size: 0.85rem; border-radius: 8px; }

.btn-cancel-order { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; padding: 6px 12px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: 0.2s; }
.btn-cancel-order:hover { background: #f87171; color: #fff; }

/* Тост */
.toast { visibility: hidden; min-width: 280px; background-color: #10b981; color: #fff; text-align: center; border-radius: 12px; padding: 16px; position: fixed; z-index: 1000; left: 50%; bottom: 30px; transform: translateX(-50%); font-weight: 600; font-size: 1.1rem; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3); opacity: 0; transition: opacity 0.3s, bottom 0.3s; }
.toast.show { visibility: visible; opacity: 1; bottom: 50px; }

/* Баннер, поиск и кнопка Найти */
.hero { background: linear-gradient(135deg, #0f172a 0%, #3b82f6 100%); color: #fff; padding: 40px; border-radius: 24px; margin-bottom: 24px; text-align: center; box-shadow: 0 10px 30px rgba(59, 130, 246, 0.2); border: 1px solid rgba(255,255,255,0.1); }
.hero h1 { font-size: 2.2rem; margin-bottom: 12px; font-weight: 800; letter-spacing: -0.5px; }
.hero p { opacity: 0.9; font-size: 1.1rem; }

.toolbar-bar { display: flex; gap: 12px; margin-bottom: 20px; }
.search-wrap { flex: 1; }
.search-wrap input { width: 100%; height: 50px; padding: 0 20px; border-radius: 14px; border: 1px solid #cbd5e1; font-size: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.02); background:#fff; outline: none; }
.search-wrap input:focus { border-color: #0284c7; }
.sort-wrap { display: flex; align-items: center; }
.search-btn { height: 50px; padding: 0 28px; display: inline-flex; align-items: center; justify-content: center; border-radius: 14px; }

.filters { display: flex; gap: 10px; overflow-x: auto; padding: 6px 6px 12px 6px; margin: 0 -6px 20px -6px; }
.filter-chip { background: #fff; border: 1px solid #cbd5e1; padding: 10px 20px; border-radius: 30px; text-decoration: none; color: #475569; white-space: nowrap; font-weight: 600; font-size: 0.9rem; transition: 0.2s; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
.filter-chip.active, .filter-chip:hover { background: #0284c7; color: #fff; border-color: #0284c7; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(2, 132, 199, 0.2); }

/* Сетка каталога */
.product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; }
.product-card { position: relative; background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04); display: flex; flex-direction: column; border: 1px solid #e2e8f0; transition: transform 0.2s, box-shadow 0.2s; }
.product-card:hover { transform: translateY(-6px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08); }
.card-img-link img { width: 100%; height: 220px; object-fit: cover; display: block; border-bottom: 1px solid #f1f5f9; }
.card-body { padding: 20px; display: flex; flex-direction: column; flex-grow: 1; }
.card-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.badge { background: #f1f5f9; color: #475569; font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 8px; position: relative; z-index: 2; }
.badge-admin { background: #fef3c7; color: #d97706; }
.rating { color: #f59e0b; font-weight: 700; font-size: 0.85rem; display: flex; align-items: center; gap: 4px; }
.card-body h3 { font-size: 1.15rem; margin-bottom: 10px; line-height: 1.3; }
.card-body h3 a { text-decoration: none; color: #0f172a; }
.stretched-link::after { content: ""; position: absolute; inset: 0; z-index: 1; }
.desc { color: #64748b; font-size: 0.95rem; margin-bottom: 20px; flex-grow: 1; }
.card-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed #e2e8f0; padding-top: 16px; flex-wrap: nowrap; gap: 8px; }

/* Фикс разрыва цен */
.price { font-size: 1.35rem; font-weight: 800; color: #0f172a; white-space: nowrap; flex-shrink: 0; }
.card-footer .btn-primary { position: relative; z-index: 2; white-space: nowrap; padding: 10px 16px; flex-shrink: 0; }

/* Детальная страница */
.product-detail { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; background: #fff; padding: 32px; border-radius: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; }
.detail-image img { width: 100%; border-radius: 16px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
.detail-info h1 { font-size: 2.2rem; line-height: 1.2; margin: 12px 0; color: #0f172a; }
.detail-price { font-size: 2.2rem; font-weight: 900; color: #0284c7; margin: 16px 0; white-space: nowrap; }
.detail-desc { font-size: 1.1rem; color: #475569; margin-bottom: 24px; line-height: 1.6; }

.size-selector { margin-bottom: 24px; }
.size-selector label { display: block; font-weight: 600; margin-bottom: 8px; color: #475569; }
.size-chips { display: flex; gap: 8px; flex-wrap: wrap; }
.size-chip { border: 2px solid #e2e8f0; background: #f8fafc; padding: 8px 14px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: 0.2s; }
.size-chip:hover { border-color: #cbd5e1; }
.size-chip.active { background: #0284c7; color: #fff; border-color: #0284c7; }

.detail-actions { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 24px; padding-top: 24px; border-top: 1px solid #f1f5f9; }

/* ОТЗЫВЫ */
.reviews-section { margin-top: 48px; background: transparent; }
.reviews-layout { display: grid; grid-template-columns: 1.5fr 1fr; gap: 32px; align-items: start; }
.review-card { background: #fff; padding: 24px; border-radius: 20px; margin-bottom: 16px; box-shadow: 0 4px 16px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; display: flex; gap: 16px; }
.review-avatar { width: 50px; height: 50px; flex-shrink: 0; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #0284c7); color: #fff; font-size: 1.4rem; font-weight: bold; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(2,132,199,0.3); }
.review-content { flex: 1; }
.review-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
.review-header strong { font-size: 1.1rem; color: #0f172a; }
.review-date { color: #94a3b8; font-size: 0.85rem; }
.review-stars { display: flex; gap: 2px; margin-bottom: 10px; }
.review-text { color: #475569; font-size: 1rem; line-height: 1.5; }
.empty-reviews { text-align: center; padding: 40px; background: #fff; border-radius: 20px; border: 1px dashed #cbd5e1; color: #64748b; }

.review-form-modern { background: #fff; padding: 32px; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,0.04); position: sticky; top: 100px; }
.review-form-modern h3 { margin-bottom: 20px; font-size: 1.4rem; }
textarea { width: 100%; min-height: 100px; padding: 14px; border-radius: 12px; border: 2px solid #e2e8f0; background: #f8fafc; font-size: 1rem; resize: none; outline: none; font-family: inherit; field-sizing: content; }
textarea:focus { border-color: #0284c7; }

.star-rating-widget { 
    display: flex !important; 
    flex-direction: row-reverse !important; 
    justify-content: space-evenly !important; 
    background: #f8fafc !important; 
    padding: 12px 20px !important; 
    border-radius: 12px !important; 
    border: 2px solid #e2e8f0 !important; 
    width: 100% !important; 
}
.star-rating-widget input { display: none !important; }
.star-rating-widget label { 
    font-size: 3rem !important; 
    color: #cbd5e1 !important; 
    cursor: pointer !important; 
    transition: color 0.2s, transform 0.2s !important; 
    flex: 1 !important; 
    text-align: center !important; 
}
.star-rating-widget label:hover,
.star-rating-widget label:hover ~ label,
.star-rating-widget input:checked ~ label { color: #f59e0b !important; }
.star-rating-widget label:hover { transform: scale(1.2) !important; }

/* Пустая корзина по центру */
.empty-state-centered { background: #fff; padding: 48px 32px; border-radius: 24px; text-align: center; max-width: 500px; margin: 40px auto; border: 1px solid #e2e8f0; box-shadow: 0 8px 24px rgba(0,0,0,0.03); }

/* Корзина */
.cart-layout { display: grid; grid-template-columns: 1.6fr 1fr; gap: 32px; margin-top: 24px; }
.cart-row { background: #fff; padding: 20px; border-radius: 20px; display: flex; align-items: center; gap: 20px; margin-bottom: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
.cart-row img { width: 100px; height: 80px; object-fit: cover; border-radius: 12px; }
.cart-row-info { flex: 1; }
.cart-row-info h4 { font-size: 1.1rem; margin-bottom: 4px; }
.cart-qty { display: flex; align-items: center; gap: 12px; background: #f1f5f9; padding: 6px; border-radius: 12px; }
.qty-btn { width: 32px; height: 32px; border-radius: 8px; border: none; background: #fff; cursor: pointer; font-weight: 700; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: 0.2s; }
.qty-btn:hover { background: #e0f2fe; color: #0284c7; }
.cart-checkout-card { background: #fff; padding: 32px; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,0.04); align-self: start; position: sticky; top: 100px; }

/* Профиль */
.profile-dashboard { display: grid; grid-template-columns: 320px 1fr; gap: 32px; align-items: start; margin-top: 24px; }
.profile-card-modern { background: #fff; padding: 32px 24px; border-radius: 24px; border: 1px solid #e2e8f0; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
.profile-avatar { width: 90px; height: 90px; background: linear-gradient(135deg, #0284c7, #3b82f6); color: #fff; font-size: 2.5rem; font-weight: 700; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: 0 auto 20px; box-shadow: 0 8px 20px rgba(2,132,199,0.25); }
.profile-actions-column { display: flex; flex-direction: column; gap: 12px; margin-top: 24px; width: 100%; }
.profile-actions-column a { width: 100%; display: block; box-sizing: border-box; }

.orders-modern { background: #fff; padding: 32px; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
.orders-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 24px; }
.badge-count { background: #e0f2fe; color: #0369a1; padding: 4px 12px; border-radius: 20px; font-weight: 700; font-size: 1rem; }
.order-item-modern { border: 2px solid #e2e8f0; border-radius: 16px; padding: 24px; margin-bottom: 20px; transition: border-color 0.2s; background: #fff; }
.order-item-modern:hover { border-color: #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
.order-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.order-id { font-weight: 800; font-size: 1.15rem; color: #0f172a; }
.order-date { font-size: 0.9rem; color: #64748b; margin-left: 8px; }
.status-processing { background: #fef3c7; color: #b45309; padding: 6px 12px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; }
.status-cancelled { background: #fee2e2; color: #b91c1c; padding: 6px 12px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; }
.order-items-box { background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px dashed #cbd5e1; color: #475569; margin-bottom: 16px; line-height: 1.6; }
.order-footer-info { display: flex; justify-content: space-between; align-items: center; }
.order-price { font-size: 1.3rem; color: #0f172a; white-space: nowrap; }

.auth-card { max-width: 440px; margin: 40px auto; background: #fff; padding: 36px; border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
.form-group { margin-bottom: 18px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.95rem; color: #475569; }
.form-group input, .admin-input { width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; background: #f8fafc; font-size: 1rem; transition: 0.2s; outline: none; }
.form-group input:focus, .admin-input:focus { border-color: #0284c7; background: #fff; }

/* Админка */
.admin-layout { display: grid; grid-template-columns: 250px 1fr; gap: 24px; align-items: start; }
.admin-sidebar { background: #fff; padding: 24px; border-radius: 24px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
.admin-sidebar a { padding: 12px 16px; border-radius: 12px; text-decoration: none; color: #475569; font-weight: 600; transition: 0.2s; }
.admin-sidebar a.active, .admin-sidebar a:hover { background: #0284c7; color: #fff; }
.admin-content { background: #fff; padding: 32px; border-radius: 24px; border: 1px solid #e2e8f0; overflow-x: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
.table-responsive { overflow-x: auto; }
.admin-table { width: 100%; border-collapse: collapse; margin-top: 16px; min-width: 600px; }
.admin-table th, .admin-table td { padding: 14px; border-bottom: 1px solid #f1f5f9; text-align: left; vertical-align: middle; }
.admin-table th { color: #64748b; font-weight: 600; font-size: 0.9rem; }
.admin-table tr:hover { background: #f8fafc; }
.admin-add-form { background: #f8fafc; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; }

.site-footer { background: #0f172a; color: #94a3b8; padding: 48px 0; margin-top: auto; border-top: 4px solid #3b82f6; }
.footer-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 32px; }
.site-footer a { color: #cbd5e1; text-decoration: none; transition: color 0.2s; }
.site-footer a:hover { color: #3b82f6; }

/* ==================================================
   МОБИЛЬНЫЙ ПАТЧ: Адаптация под смартфоны
   ================================================== */
@media (max-width: 768px) {
    .product-detail, .cart-layout, .profile-dashboard, .reviews-layout, .admin-layout { 
        grid-template-columns: 1fr; 
        gap: 24px; 
    }
    
    .hero { padding: 24px 16px; border-radius: 20px; }
    .hero h1 { font-size: 1.6rem; }
    
    .toolbar-bar { flex-direction: column; }
    .search-btn { width: 100%; }
    
    /* ----- ИСПРАВЛЕННАЯ ШАПКА ДЛЯ ТЕЛЕФОНОВ ----- */
    .nav-container { 
        flex-wrap: wrap; /* Разрешаем перенос строк */
        padding: 16px; 
    }
    
    .logo { 
        margin-right: auto; /* Прижимает профиль вправо */
        order: 1; /* Логотип всегда первый */
    }
    
    .auth-links { 
        order: 2; /* Профиль всегда второй (в одной строке с логотипом) */
        margin-left: auto;
    }
    
    .user-pill-name { display: none; }
    .user-pill { padding: 6px; border-radius: 50%; }
    
    .auth-link-simple { background: #f1f5f9; padding: 8px 16px; border-radius: 12px; border: 1px solid #e2e8f0; }
    .btn-register { padding: 8px 16px; }
    
    .nav-links { 
        order: 3; /* Ссылки всегда третьи (переносятся на новую строку) */
        width: 100%; 
        margin-right: 0;
        margin-top: 16px;
        justify-content: space-between; 
        gap: 8px; 
    }
    
    .nav-links > a {
        flex: 1; text-align: center; justify-content: center;
        background: #f1f5f9; padding: 10px 8px; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 0.9rem;
    }
    
    .nav-links .cart-link { background: #e0f2fe; border-color: #bae6fd; }
    .cart-text { display: none; }
    .cart-link svg { margin-right: 0 !important; }
    /* ------------------------------ */
    
    .cart-row { flex-direction: column; align-items: center; text-align: center; }
    .cart-row img { width: 100%; height: auto; aspect-ratio: 4/3; }
    .cart-qty { justify-content: center; margin: 12px 0; }
    
    .admin-sidebar { flex-direction: row; overflow-x: auto; padding: 12px; }
    .admin-sidebar a { white-space: nowrap; }
    .admin-content { padding: 16px; }
    
    .detail-actions { flex-direction: column; }
    .detail-actions .btn-primary, .detail-actions .btn-secondary { width: 100%; text-align: center; justify-content: center; }
    
    .product-grid { grid-template-columns: 1fr; }
}
EOD;

foreach ($files as $filename => $content) {
    file_put_contents(__DIR__ . '/' . $filename, $content);
}
header("Location: index.php");
exit;