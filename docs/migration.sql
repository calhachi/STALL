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
