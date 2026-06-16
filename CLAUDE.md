# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

**STALL** は TRPGシナリオ販売サイト。PHP (フレームワークなし) + MySQL + XAMPP 環境で動作する。

## 開発環境のセットアップ

XAMPPのApache + MySQL上で動作する。ドキュメントルートは `D:\xampp\htdocs\stall\`。

`.env` ファイル（gitignore済み）を `stall/` 直下に配置:
```
APP_URL=http://localhost/stall
DB_DSN=mysql:host=localhost;dbname=stall;charset=utf8mb4
DB_USER=root
DB_PASS=
```

依存パッケージのインストール:
```
composer install
```

## バッチ処理

`common/batch/dailyBatch.php` を毎日1回cronで実行し、`userdata/temp/` 内の24時間超過ファイルを削除する:
```
php D:\xampp\htdocs\stall\common\batch\dailyBatch.php
```

## アーキテクチャ

### ページ構成

フレームワークなしのPHP。各PHPファイルがページに対応する。`.htaccess` により `/login/index` → `/login/index.php` と拡張子省略でアクセス可能。

すべてのページは冒頭で以下を読み込む:
```php
require_once __DIR__ . '/../common/bootstrap.php';  // セッション開始・.env読込・定数定義・h()関数
require_once __DIR__ . '/../common/dbConnect.php';   // dbConnect()関数
```

### 共通ヘルパー (`common/`)

| ファイル | 役割 |
|---|---|
| `bootstrap.php` | セッション開始、.env読込、ファイルパス定数定義、`h()`エスケープ関数 |
| `dbConnect.php` | `dbConnect()` → PDO接続を返す。失敗時は `error.php` にリダイレクト |
| `requireLogin.php` | `requireLogin()` → 未ログイン時にログインページへリダイレクト（戻り先を `$_SESSION['checkPoint']` に保存） |
| `requireGuest.php` | `requireGuest()` → ログイン済みならトップへリダイレクト |
| `varidateImage.php` | `validateImage($file, $maxSize)` → 画像のMIME・拡張子・サイズ検証。エラー文字列または空文字を返す |
| `script.js` | 全ページ共通JS（文字カウンター、画像プレビュー、カテゴリ連動選択、AJAX削除） |

### ファイルパス定数 (`bootstrap.php` で定義)

```php
TEMP_DIR       // userdata/temp/    投稿フォーム一時保存領域
WORKS_DIR      // userdata/works/   作品ファイル（pdf/txt）
THUMBNAIL_DIR  // userdata/thumbnail/
WORKS_IMAGES_DIR // userdata/works-image/
```

`userdata/` は `.gitignore` 対象。

### 2段階フォーム確認パターン

このプロジェクトはフォーム確認画面を **セッションを使ったPOST→確認→コミット** の流れで実装している:

**ユーザー登録フロー:**
1. `login/registration.php` → バリデーション → `$_SESSION['registration']` に保存 → `login/check.php` へ
2. `login/check.php` → 確認表示 → `login/preregistration.php` へPOST
3. `login/preregistration.php` → DBに仮登録（`is_verified=0`）+ 確認メール送信（`register_token`付きURL）
4. `login/done.php` → トークン検証 → `is_verified=1` に更新 → セッション開始

**作品投稿フロー:**
1. `mypage/post.php` → バリデーション → ファイルを `TEMP_DIR` に保存 → `$_SESSION['postWork']` に保存 → `mypage/post-check.php` へ
2. `mypage/post-check.php` → 確認表示
3. `mypage/works.php` → `$_SESSION['postWork']` があればDBにINSERT＋ファイルを正式フォルダへ移動（DB commit後）

### セッション変数

```php
$_SESSION['userId']      // ログイン中ユーザーID
$_SESSION['username']    // 表示名
$_SESSION['role']        // ロール
$_SESSION['checkPoint']  // ログイン後のリダイレクト先
$_SESSION['registration'] // 登録確認ページ用一時データ
$_SESSION['postWork']    // 作品投稿確認ページ用一時データ
```

### DBスキーマ（主要テーブル）

- `users`: id, username, email, password_hash, icon_image, profile_text, role, register_token, is_verified, created_at
- `works`: id, user_id, title, price, category_id, subcategory_id, min_players, max_players, text_hours, voice_hours, description, file_name, original_name, thumbnail_name, posted_at
- `works_images`: id, work_id, image_name, display_order
- `works_tag`: work_id, tag_id
- `categories` / `subcategories` / `tags`: カテゴリ階層（categories → subcategories → tags はすべて category_id で紐付け）
- `news`: id, title, body, image_name（nullable）, category（'お知らせ'/'アップデート'/'イベント'）, created_at, updated_at

### カテゴリ構造

カテゴリID=1（シナリオ）の場合のみ `#trpgOnlyFields`（プレイ人数・プレイ時間）を表示する。この判定はサーバーサイド（`mypage/post.php`）とクライアントサイド（`common/script.js`）の両方で行う。

### 画像ファイルの格納先

| ディレクトリ | 用途 |
|---|---|
| `userdata/` | ユーザーがアップロードしたファイル（`.gitignore` 対象） |
| `images/` | サイト本体で使用する画像（git管理対象） |

`images/news/` にお知らせのアイキャッチ画像を格納する。ファイル名は `news{id}.{拡張子}`（DBのidが確定後にINSERTしてから保存・UPDATE）。

### 管理機能 (`admin/`)

`$_SESSION['role']` が `1`（admin）のユーザーのみアクセス可能。`role === 0` の場合はトップへリダイレクト。

| ファイル | 役割 |
|---|---|
| `admin/index.php` | 管理画面トップ |
| `admin/newsadd.php` | お知らせ追加。newsテーブルにINSERT。画像は `images/news/` に保存 |

### お知らせ機能 (`news/`)

| ファイル | 役割 |
|---|---|
| `news/index.php` | お知らせ詳細。`GET id` でnewsテーブルから1件取得して表示 |
| `news/news-list.php` | お知らせ一覧 |

`index.php`（トップページ）の「お知らせ」セクションでは最新3件を取得して表示する。newsクエリは他のクエリより**後ろ**に配置すること（失敗時に他セクションへの影響を防ぐため）。

### API (`api/`)

`api/deleteWork.php` はJSON APIエンドポイント。`common/script.js` の `.deleteButton` クリックからfetchで呼ばれる。ページ遷移なしで作品削除を行う。所有者チェック（`WHERE id=:id AND user_id=:userId`）必須。

### XSS対策

出力時は必ず `h()` 関数（`htmlspecialchars` ラッパー）を使う。`common/bootstrap.php` で定義されており、全ページで使用可能。
