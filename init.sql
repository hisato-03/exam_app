-- ユーザーテーブル
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(12) NOT NULL UNIQUE,   -- 半角英数字3〜12文字に合わせて制約
    password_hash VARCHAR(255) NOT NULL,   -- ハッシュ化されたパスワード
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 試験履歴テーブル
CREATE TABLE IF NOT EXISTS history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,                   -- users.id を参照
    question_id INT NOT NULL,               -- 問題ID
    exam_number VARCHAR(50),                -- 試験番号（文字列管理）
    answer VARCHAR(255),                    -- 回答
    correct VARCHAR(255),                   -- 正解
    is_correct TINYINT(1),                  -- 判定フラグ (1=正解,0=不正解)
    subject VARCHAR(50),                    -- 科目
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_subject (subject),
    INDEX idx_question (question_id)
);

-- searched_wordsテーブル
CREATE TABLE IF NOT EXISTS searched_words (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    word VARCHAR(255) NOT NULL,
    meaning TEXT,
    subject VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_word (word)
);

-- 動画視聴履歴テーブル
CREATE TABLE IF NOT EXISTS video_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,                   -- users.id を参照
    subject VARCHAR(50),                    -- 科目名
    video_title VARCHAR(255),               -- 動画タイトル
    video_path VARCHAR(255),                -- 動画ファイルのパス
    watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,  -- 視聴日時
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_subject (subject)
);
