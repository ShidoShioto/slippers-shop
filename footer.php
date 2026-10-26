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