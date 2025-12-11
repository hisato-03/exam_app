# Video App - 学習動画管理モジュール

このディレクトリは、介護福祉士試験対策アプリの「学習動画管理機能」を提供します。  
Googleスプレッドシートと連携し、科目・単元ごとに動画を整理・再生・履歴管理できます。

---

## 📁 ディレクトリ構成
video_app/ ├── index.php # 学習動画一覧ページ（Google Sheetsと連携） ├── player.php # 動画再生ページ（履歴保存あり） ├── video_history.php # 視聴履歴の表示ページ ├── style.css # 共通スタイルシート ├── videos/ # 動画ファイル格納ディレクトリ（例: videos/SOC/SOC001.mp4） └── credentials.json # Google Sheets API 認証情報（非公開）


---

## ✅ 動作要件

- PHP 8.x
- MySQL 5.7+
- Docker（推奨）
- Google Sheets API 認証設定済み
- `video_history` テーブルが存在すること

---

## 🚀 セットアップ手順

1. `videos/` フォルダに動画ファイルを配置  
   例: `videos/SOC/SOC001.mp4`

2. Googleスプレッドシート「管理表」に以下のような構成で動画情報を記載  
   - A列: 科目コード（例: SOC）
   - B列: 科目名（例: 社会）
   - C列: 節（任意）
   - D列: 単元（例: 社会の仕組み）
   - F列: 動画ファイル名（例: SOC001.mp4）

3. `credentials.json` を配置（Google Cloud Consoleで取得）

4. Docker環境で起動（例: `docker-compose up -d`）

5. ブラウザでアクセス  
http://localhost:8082/exam_app/video_app/index.php


---

## 📚 機能概要

- **動画一覧表示**：Google Sheetsの「管理表」から科目・単元を読み込み、動画一覧を表示
- **動画再生**：`player.php` で `<video>` タグを使って再生
- **履歴保存**：再生時に `video_history` テーブルへ記録
- **履歴表示**：`video_history.php` でユーザーごとの視聴履歴を確認可能

---

## 🛡️ セキュリティ

- `player.php` では `..` を含む不正なパスをブロック
- `file_exists()` による動画ファイルの存在チェックあり
- `auth.php` によるログイン認証を全ページで実施

---

## ✨ 今後の拡張候補

- 再生位置の保存と復元
- 履歴のフィルタリング（科目・日付など）
- 管理者向けの動画アップロード機能

---

## 🦊 作者メモ

このモジュールは、実用性とシンプルさを両立するために設計されています。  
Google Sheets を教材管理の中心に据えることで、非エンジニアでも柔軟に運用できます。
