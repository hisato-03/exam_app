<?php
session_start();
require "auth.php";
require_once __DIR__ . '/load_credentials.php';
restore_credentials('GOOGLE_CREDENTIALS_ROOT_B64');
require __DIR__ . '/vendor/autoload.php';

// .env からDB設定読み込み
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $pdo = new PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4", $_ENV['DB_USER'], $_ENV['DB_PASS']);
    
    // Google API 設定
    $client = new Google\Client();
    $client->setAuthConfig(__DIR__ . '/credentials.json');
    $client->setScopes([Google\Service\Sheets::SPREADSHEETS_READONLY]);
    $service = new Google\Service\Sheets($client);
    $sheetId = '1LDr4Acf_4SE-Wzp-ypPxM6COZdOt2QYumak8hIVVdxo';
    
    // A2:O (15カラム分) を取得
    $response = $service->spreadsheets_values->get($sheetId, 'dictionary_upload!A2:O');
    $values = $response->getValues() ?? [];

    $pdo->beginTransaction();

    // 既存データを一度消すか、あるいは ON DUPLICATE KEY UPDATE で上書き
    foreach ($values as $row) {
        if (empty($row[3])) continue; // search_key(D列)が空ならスキップ

        $sql = "INSERT INTO master_dictionary 
                (base, text_ruby, meaning, search_key, image_url, 
                 trans_en, trans_tl, trans_my, trans_th, trans_ne, 
                 trans_hi, trans_id, trans_zh, trans_ko, trans_vi) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                 base=VALUES(base), text_ruby=VALUES(text_ruby), meaning=VALUES(meaning),
                 image_url=VALUES(image_url), trans_en=VALUES(trans_en), trans_tl=VALUES(trans_tl),
                 trans_my=VALUES(trans_my), trans_th=VALUES(trans_th), trans_ne=VALUES(trans_ne),
                 trans_hi=VALUES(trans_hi), trans_id=VALUES(trans_id), trans_zh=VALUES(trans_zh),
                 trans_ko=VALUES(trans_ko), trans_vi=VALUES(trans_vi)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $row[0]??'', $row[1]??'', $row[2]??'', $row[3]??'', $row[4]??'',
            $row[5]??'', $row[6]??'', $row[7]??'', $row[8]??'', $row[9]??'',
            $row[10]??'', $row[11]??'', $row[12]??'', $row[13]??'', $row[14]??''
        ]);
    }

    $pdo->commit();
    echo "同期成功: " . count($values) . " 件のデータを更新しました。";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("同期エラー: " . $e->getMessage());
}