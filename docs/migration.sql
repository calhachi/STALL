-- STALLサイト DBマイグレーション
-- phpMyAdmin の SQL タブで実行してください
-- ※ すでに適用済みの項目はスキップされます

-- worksテーブルへの追加（未追加の場合のみ個別に実行）
-- ALTER TABLE works ADD COLUMN view_count INT NOT NULL DEFAULT 0;
-- ALTER TABLE works ADD COLUMN updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;
-- ↑ すでに追加済みであれば実行不要

-- usersテーブルへの追加（未追加の場合のみ実行）
-- ALTER TABLE users ADD COLUMN last_login_at DATETIME DEFAULT NULL;
-- ↑ すでに追加済みであれば実行不要

-- purchaseテーブルの作成（まだ存在しない場合に実行）
CREATE TABLE IF NOT EXISTS purchase (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    work_id      INT UNSIGNED NOT NULL,
    purchased_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (work_id) REFERENCES works(id) ON DELETE CASCADE
);

-- worksテーブルへの追加（管理者による作品非表示機能、未追加の場合のみ実行）
-- ALTER TABLE works ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0;
-- ↑ すでに追加済みであれば実行不要

-- reportsテーブルの作成（作品通報機能、まだ存在しない場合に実行）
-- reason: 1=第三者の権利侵害, 2=過度にグロテスク・公序良俗違反, 3=利用者が不快になる可能性, 4=その他
-- status: 0=未対応, 1=対応済み
CREATE TABLE IF NOT EXISTS reports (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    work_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    reason     TINYINT UNSIGNED NOT NULL,
    detail     TEXT DEFAULT NULL,
    status     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_work (user_id, work_id),
    FOREIGN KEY (work_id) REFERENCES works(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
