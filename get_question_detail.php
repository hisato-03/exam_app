<?php
require "auth.php"; // ログインチェック
require_once __DIR__ . '/load_credentials.php';
restore_credentials('GOOGLE_CREDENTIALS_ROOT_B64');
require __DIR__ . '/vendor/autoload.php';

// JSON形式で返却することをブラウザに伝える
header('Content-Type: application/json; charset=utf-8');

$qid = $_GET['qid'] ?? '';
$subject = $_GET['subject'] ?? '';

if (!$qid || !$subject) {
    echo json_encode(['error' => '問題IDまたは科目が指定されていません']);
    exit;
}

// norm_id関数（history.phpと同じもの）
function norm_id($s) {
    return strtoupper(mb_convert_kana(trim((string)$s), 'as'));
}

try {
    $client = new Google\Client();
    $client->setAuthConfig(__DIR__ . '/credentials.json');
    $client->setScopes([Google\Service\Sheets::SPREADSHEETS_READONLY]);
    $service = new Google\Service\Sheets($client);
    $sheetId = '1wBLqdju-BmXS--aPCMMC3PipvCpBFXmdVemT0X2rKew';

    // --- 修正箇所：タブ名の特定 ---
    $tabName = $subject;
    
    // もし科目が「すべて」や空だった場合、全タブを検索するか、
    // あるいは特定のデフォルトタブを指定する必要があります。
    // ここでは、エラーを回避するためにスプレッドシートの全タブ名を取得してチェックする構成にします。
    
    $spreadsheet = $service->spreadsheets->get($sheetId);
    $sheets = $spreadsheet->getSheets();
    $allTabNames = [];
    foreach ($sheets as $s) {
        $allTabNames[] = $s->getProperties()->getTitle();
    }

    // もし指定された $subject がタブ名として存在しない場合
    if (!in_array($tabName, $allTabNames)) {
        // 全タブを順番に探して、問題ID(qid)が一致するものを探す
        foreach ($allTabNames as $t) {
            if ($t === 'dictionary_upload') continue; // 辞書用タブはスキップ
            
            $range = $t . '!A2:A'; // ID列だけまず確認（高速化）
            $response = $service->spreadsheets_values->get($sheetId, $range);
            $ids = $response->getValues() ?? [];
            
            foreach ($ids as $row) {
                if (norm_id($row[0] ?? '') === norm_id($qid)) {
                    $tabName = $t; // 見つかったのでこのタブを使う
                    break 2;
                }
            }
        }
    }

    // 確定したタブ名でデータを取得
    $range = $tabName . '!A2:I';
    $response = $service->spreadsheets_values->get($sheetId, $range);
    $values = $response->getValues() ?? [];
    // --- 修正箇所ここまで ---

    $foundData = null;
    $targetQid = norm_id($qid);

    foreach ($values as $row) {
        if (norm_id($row[0] ?? '') === $targetQid) {
            $foundData = [
                'text'    => $row[1] ?? '',
                'choices' => array_map('trim', array_slice($row, 2, 5)),
                'correct' => isset($row[7]) ? trim($row[7]) : '',
                'explain' => $row[8] ?? '',
                'subject' => $tabName
            ];
            break;
        }
    }

    if ($foundData) {
        echo json_encode($foundData, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['error' => '指定されたIDの問題が見つかりませんでした']);
    }

} catch (Exception $e) {
    echo json_encode(['error' => 'Google Sheets 通信エラー: ' . $e->getMessage()]);
}