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