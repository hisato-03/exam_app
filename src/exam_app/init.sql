CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user VARCHAR(50) NOT NULL,          -- ログインユーザー名（guest含む）
    question_id INT NOT NULL,           -- 問題ID
    exam_number VARCHAR(50),            -- 試験番号
    answer VARCHAR(255),                -- 回答
    correct VARCHAR(255),               -- 正解
    is_correct TINYINT(1),              -- 判定フラグ (1=正解,0=不正解)
    subject VARCHAR(50),                -- 科目
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
