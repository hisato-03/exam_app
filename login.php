<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"];
    $password = $_POST["password"];
    $found = false;

    $filePath = __DIR__ . '/users.csv';
    if (($file = fopen($filePath, "r")) !== false) {
        while (($data = fgetcsv($file)) !== false) {
            if ($data[0] === $username && $data[1] === $password) {
                $_SESSION["user"] = $username;
                $found = true;
                break;
            }
        }
        fclose($file);
    }

    if ($found) {
        header("Location: test.php"); // ログイン成功 → test.phpへ
        exit;
    } else {
        $error = "ログイン失敗：ユーザー名またはパスワードが違います";
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
      width: 380px; /* ← 幅を広げた */
      text-align: center;
    }
    .login-form h2 {
      margin-bottom: 20px;
      color: #333;
    }
    .login-form input[type="text"],
    .login-form input[type="password"] {
      width: 100%;
      padding: 12px; /* 少し余裕を持たせる */
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
      background-color: #4CAF50; /* 緑系で区別 */
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

      <!-- 新規アカウント登録ボタンをカード下に配置 -->
      <div class="register-link">
        <p>まだアカウントをお持ちでない方はこちら:</p>
        <a href="account.php"><button type="button">新規アカウント登録</button></a>
      </div>
    </form>
  </div>
</body>
</html>
