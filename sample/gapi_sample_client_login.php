<?php
/**************************************************
 * GAPI CientLoginサンプル
 **************************************************/
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__.'/../GAPI.php';

// ClientLoginでGAPI初期化
$gapi = new GAPI(GAPI::AUTHTYPE_CLIENTLOGIN, 'account@example.com', 'passwd');

?><html>
<head>
    <title>GAPI ClientLoginテスト</title>
</head>
<body>
<?php
// GA設定
$ga_profile_id = '00000000';                            // プロファイルID
$ga_dimensions = array('pageTitle','pagePath');         // ページ名とURL取得
$ga_metrics = array('pageviews');                       // Pageviews取得
$ga_sort_metric = '-pageviews';                         // pageviewsで降順に並び替え
$ga_filter = '';                                        // filter：フィルタ無し
$ga_start_date = date('Y-m-d', strtotime('-7 days'));   // 取得開始日：7日前
$ga_end_date = date('Y-m-d', strtotime('-0 days'));     // 取得終了日：1日前
$ga_start_index = 1;                                    // 開始index
$ga_max_results = 1000;                                 // 最大取得件数
$max_session_results = 5;                               // 一度に取得する件数
try {
    // 1回の接続で取得する最大件数を設定
    $gapi->setMaxSessionResults($max_session_results);
    // GAへのリクエスト内容をGAPI準拠で設定
    $gapi->requestAccountData();
    echo '<table border="1"><tr><th>セッション番号</th><th>取得番号</th><th>ID</th><th>名前</th><th>WebPropertyID</th></tr>', PHP_EOL;
    // 1件ごとにデータ取得して表示
    while($result = $gapi->fetchResult(GAPI::FETCH_OBJECT)) {
        echo '<tr>',
             '<td>', htmlspecialchars($gapi->getSessionIndex(), ENT_QUOTES), '</td>',
             '<td>', htmlspecialchars($gapi->getResultIndex(), ENT_QUOTES), '</td>',
             '<td>', htmlspecialchars($result->getId(), ENT_QUOTES), '</td>',
             '<td>', htmlspecialchars($result->getName(), ENT_QUOTES), '</td>',
             '<td>', htmlspecialchars($result->getWebPropertyId(), ENT_QUOTES), '</td>',
             '</tr>', PHP_EOL;
    }
    echo '</table>', PHP_EOL;
    // GAへのリクエスト内容をgapi準拠で設定
    $gapi->requestReportData($ga_profile_id, $ga_dimensions, $ga_metrics, $ga_sort_metric, $ga_filter, $ga_start_date, $ga_end_date, $ga_start_index, $ga_max_results);
    echo '<table border="1"><tr><th>セッション番号</th><th>順位</th><th>ページタイトル</th><th>URL</th><th>PageViews</th></tr>', PHP_EOL;
    // 1件ごとにデータ取得して表示
    while($result = $gapi->fetchResult(GAPI::FETCH_OBJECT)) {
        echo '<tr>',
             '<td>', htmlspecialchars($gapi->getSessionSequenceNumber(), ENT_QUOTES), '</td>',
             '<td>', htmlspecialchars($gapi->getResultIndex(), ENT_QUOTES), '</td>',
             '<td>', htmlspecialchars($result->getPageTitle(), ENT_QUOTES), '</td>',
             '<td>', htmlspecialchars($result->getPagePath(), ENT_QUOTES), '</td>',
             '<td>', htmlspecialchars($result->getPageviews(), ENT_QUOTES), '</td>',
             '</tr>', PHP_EOL;
    }
    echo '</table>', PHP_EOL;
} catch (Exception $e) {
    throw $e;
}
?>
</body>
</html>