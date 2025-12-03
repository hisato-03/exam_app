<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    // DB接続情報を環境変数から取得
    $host = getenv('DB_HOST') ?: 'db';
    $db   = getenv('DB_NAME') ?: 'exam_app';
    $user = getenv('DB_USER') ?: 'exam_user';
    $pass = getenv('DB_PASS') ?: 'exam_pass';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // ユーザー検索（idも取得）
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && password_verify($password, $row['password_hash'])) {
        // ログイン成功：セッションに保存
        $_SESSION["user_id"] = $row["id"];
        $_SESSION["user"] = $row["username"];
        header("Location: test.php");
    exit;
} else {
    $error = "ログイン失敗：ユーザー名またはパスワードが違います";
}

    } catch (PDOException $e) {
        $error = "❌ DB接続エラー: " . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>ログイン</title>
  <style>
    body, html {
      height: 100%;
      margin: 0;
      font-family: "Segoe UI", "Helvetica", "Arial", sans-serif;
      background-color: #f5f5f5;
    }
    .login-container {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100%;
    }
    .login-form {
      background-color: #fff;
      padding: 30px 40px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      width: 380px;
      text-align: center;
    }
    .login-form h2 {
      margin-bottom: 20px;
      color: #333;
    }
    .login-form input[type="text"],
    .login-form input[type="password"] {
      width: 100%;
      padding: 12px;
      margin-bottom: 16px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 1em;
    }
    .login-form button {
      width: 100%;
      padding: 12px;
      background-color: #2196F3;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-size: 1em;
      font-weight: bold;
      cursor: pointer;
      margin-bottom: 10px;
    }
    .login-form button:hover {
      background-color: #1976D2;
    }
    .error-message {
      color: red;
      margin-top: 15px;
      font-weight: bold;
    }
    .register-link {
      margin-top: 20px;
    }
    .register-link button {
      width: 100%;
      padding: 12px;
      background-color: #4CAF50;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-size: 1em;
      font-weight: bold;
      cursor: pointer;
    }
    .register-link button:hover {
      background-color: #388E3C;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <form method="post" class="login-form">
      <h2>ログインフォーム</h2>
      <input type="text" name="username" placeholder="ユーザー名" required>
      <input type="password" name="password" placeholder="パスワード" required>
      <button type="submit">ログイン</button>
      <?php if (isset($error)) echo "<p class='error-message'>$error</p>"; ?>

      <div class="register-link">
        <p>まだアカウントをお持ちでない方はこちら:</p>
        <a href="account.php"><button type="button">新規アカウント登録</button></a>
      </div>
    </form>
  </div>
</body>
</html>
