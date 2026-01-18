<?php
/**
 * predicted_service.php
 * 自社予想問題（スプレッドシート）の読み込み・書き込み管理
 */

require_once __DIR__ . '/vendor/autoload.php';

/**
 * スプレッドシートから問題を読み込む
 */
function getPredictedQuestionsFromSheet($sheetName) {
    // IDが環境変数から取れない場合は直接指定を使用
    $spreadsheetId = getenv('PREDICTED_SHEET_ID') ?: ($_ENV['PREDICTED_SHEET_ID'] ?? '1zOKhqEA4QJtVyo6kuadYRMUdIs_U9D3vs8lx8VT4sV0');

    if (function_exists('restore_credentials')) {
        restore_credentials('GOOGLE_CREDENTIALS_ROOT_B64');
    }

    $client = new Google\Client();
    $client->setAuthConfig($_ENV['GOOGLE_APPLICATION_CREDENTIALS']);
    $client->addScope(Google\Service\Sheets::SPREADSHEETS_READONLY);
    
    $service = new Google\Service\Sheets($client);
    
    // A列からM列（13列目）まで取得
    $range = $sheetName . "!A2:M100"; 
    
    try {
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues();
        
        $questions = [];
        if (!empty($values)) {
            foreach ($values as $row) {
                // 配列の添字が不足してエラーにならないよう調整
                $questions[] = [
                    'id'           => $row[0] ?? '',
                    'question'     => $row[1] ?? '',
                    'options'      => [
                        $row[2] ?? '', 
                        $row[3] ?? '', 
                        $row[4] ?? '', 
                        $row[5] ?? '', 
                        $row[6] ?? ''
                    ],
                    'answer'       => $row[7] ?? '',
                    'explanation'  => $row[8] ?? '',
                    'origin'       => $row[9] ?? '',  // 試験回-問題番号
                    'category_sub' => $row[10] ?? '', // 推測された小項目
                    'image_url'    => $row[12] ?? ''  // PHP読込み（M列　インデックス12）
                ];
            }
        }
        return $questions;
    } catch (Exception $e) {
        error_log("Sheet Read Error: " . $e->getMessage());
        return [];
    }
}

/**
 * スプレッドシートの最終行に問題を一括追加する
 */
function appendQuestionsToSheet($sheetName, $questions) {
    $spreadsheetId = getenv('PREDICTED_SHEET_ID') ?: ($_ENV['PREDICTED_SHEET_ID'] ?? '1zOKhqEA4QJtVyo6kuadYRMUdIs_U9D3vs8lx8VT4sV0');
    
    if (function_exists('restore_credentials')) {
        restore_credentials('GOOGLE_CREDENTIALS_ROOT_B64');
    }

    $client = new Google\Client();
    $client->setAuthConfig($_ENV['GOOGLE_APPLICATION_CREDENTIALS']);
    // 書き込みのために「READONLY」ではないスコープを設定
    $client->addScope(Google\Service\Sheets::SPREADSHEETS); 
    
    $service = new Google\Service\Sheets($client);

    $rows = [];
    foreach ($questions as $q) {
        // スプレッドシートの列順 (A:M) に合わせて配列化
        $rows[] = [
            $q['id'] ?? '',
            $q['question'] ?? '',
            $q['option1'] ?? '',
            $q['option2'] ?? '',
            $q['option3'] ?? '',
            $q['option4'] ?? '',
            $q['option5'] ?? '',
            $q['answer'] ?? '',
            $q['explanation'] ?? '',
            $q['origin'] ?? '',
            $q['category_sub'] ?? '',
            '', // L列 (予備)
            $q['image_url'] ?? '' // M列
        ];
    }

    $body = new Google\Service\Sheets\ValueRange(['values' => $rows]);
    $params = ['valueInputOption' => 'RAW'];
    
    try {
        // A1を指定することで、シート内のデータがある最終行の次から自動追加される
        return $service->spreadsheets_values->append($spreadsheetId, $sheetName . "!A1", $body, $params);
    } catch (Exception $e) {
        error_log("Sheet Write Error: " . $e->getMessage());
        throw $e;
    }
}