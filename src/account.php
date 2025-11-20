<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);

    // DB接続情報を環境変数から取得
    $host = getenv('DB_HOST') ?: 'db';
    $db   = getenv('DB_NAME') ?: 'exam_app';
    $user = getenv('DB_USER') ?: 'exam_user';
    $pass = getenv('DB_PASS') ?: 'exam_pass';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // ユーザー名のバリデーション: 半角英数字のみ、3〜12文字
        if (!preg_match('/^[a-zA-Z0-9]{3,12}$/', $username)) {
            $message = "ユーザー名は半角英数字で3〜12文字以内にしてください。";
        } else {
            // 既存ユーザー名チェック
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $exists = $stmt->fetchColumn() > 0;

            if ($exists) {
                $message = "ユーザー名「{$username}」は既に登録されています。別の名前を入力してください。";
            } else {
                // パスワードを自動生成（例: ランダム8文字）
                $assignedPassword = bin2hex(random_bytes(4));
                $hashedPassword = password_hash($assignedPassword, PASSWORD_DEFAULT);

                // DBに保存
                $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $stmt->execute([$username, $hashedPassword]);

                $message = "ユーザー名「{$username}」を登録しました。<br>
                あなたのパスワードは「<strong>{$assignedPassword}</strong>」です。<br>
                <span style='color:red;'>※生成されたパスワードは必ず保存してください。再表示はできません。</span><br><br>
                <a href='login.php'><button type='button'>ログイン画面へ</button></a>";
            }
        }
    } catch (PDOException $e) {
        $message = "❌ DB接続エラー: " . htmlspecialchars($e->getMessage());
    }
}


?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>新規アカウント登録</title>
  <style>
    body, html {
      height: 100%;
      margin: 0;
      font-family: "Segoe UI", "Helvetica", "Arial", sans-serif;
      background-color: #f5f5f5;
    }
    .register-container {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100%;
    }
    .register-form {
      background-color: #fff;
      padding: 30px 40px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      width: 380px; /* login.php と同じ幅 */
      text-align: center;
    }
    .register-form h2 {
      margin-bottom: 20px;
      color: #333;
    }
    .register-form input[type="text"] {
      width: 100%;
      padding: 12px;
      margin-bottom: 16px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 1em;
    }
    .register-form button {
      width: 100%;
      padding: 12px;
      background-color: #4CAF50; /* 緑系で区別 */
      color: #fff;
      border: none;
      border-radius: 6px;
      font-size: 1em;
      font-weight: bold;
      cursor: pointer;
      margin-bottom: 10px;
    }
    .register-form button:hover {
      background-color: #388E3C;
    }
    .message {
      margin-top: 20px;
      font-size: 0.95em;
    }
  </style>
</head>
<body>
  <div class="register-container">
    <form method="post" class="register-form">
      <h2>新規アカウント登録</h2>
      <input type="text" name="username" placeholder="ユーザー名 (半角英数字3〜12文字)" required>
      <button type="submit">登録</button>
      <?php if (isset($message)) echo "<div class='message'>{$message}</div>"; ?>
    </form>
  </div>
</body>
</html>
