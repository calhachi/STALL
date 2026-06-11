    <header id="top">
        <div class="header">
            <h1><a href="<?= $_ENV['APP_URL'] ?>"><img src="<?= $_ENV['APP_URL'] ?>/images/stall_logo.svg" alt="STALL"></a></h1>
            <form action="<?= $_ENV['APP_URL'] ?>/search" method="get">
                <input type="search" name="keyword" placeholder="タイトル・作者・システムetc">
                <button type="submit">検索</button>
            </form>
            <div>
                <?php if (!empty($_SESSION['username'])): ?>
                    <p>ようこそ、<?= h($_SESSION['username']) ?>さん</p>
                <?php else: ?>
                    <p>ようこそ、ゲストさん</p>
                <?php endif; ?>
                <a href="<?= $_ENV['APP_URL'] ?>/mypage"><img src="<?= $_ENV['APP_URL'] ?>/images/mypage_icon.svg" alt="マイページ"></a>
                <a href="<?= $_ENV['APP_URL'] ?>/mypage/favorite"><img src="<?= $_ENV['APP_URL'] ?>/images/favorite_icon.svg" alt="お気に入り"></a>
                <a href="<?= $_ENV['APP_URL'] ?>/cart"><img src="<?= $_ENV['APP_URL'] ?>/images/cart_icon.svg" alt="カート"></a>
            </div>
        </div>
    </header>