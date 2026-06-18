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

    <div id="reportModalOverlay" class="reportModalOverlay" hidden>
        <div class="reportModal" role="dialog" aria-modal="true" aria-labelledby="reportModalTitle">
            <button type="button" class="reportModalClose" id="reportModalClose" aria-label="閉じる">&times;</button>
            <h2 id="reportModalTitle">この作品を通報する</h2>
            <form id="reportForm">
                <input type="hidden" name="workId" id="reportWorkId">
                <input type="hidden" name="csrf_token" value="<?= h(generateCsrfToken()) ?>">
                <label class="reportReasonOption">
                    <input type="radio" name="reason" value="1" required>
                    第三者の権利を侵害している
                </label>
                <label class="reportReasonOption">
                    <input type="radio" name="reason" value="2">
                    過度にグロテスクな画像・公序良俗に反する
                </label>
                <label class="reportReasonOption">
                    <input type="radio" name="reason" value="3">
                    注意書きのない昆虫の画像等、利用者が不快になる可能性がある
                </label>
                <label class="reportReasonOption">
                    <input type="radio" name="reason" value="4">
                    その他
                </label>
                <textarea name="detail" id="reportDetail" class="reportDetailText" maxlength="500"
                    placeholder="詳細を入力してください" hidden></textarea>
                <p class="reportModalError" id="reportModalError"></p>
                <div class="reportModalActions">
                    <button type="submit" class="reportSubmitButton">通報する</button>
                </div>
            </form>
        </div>
    </div>