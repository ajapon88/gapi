<?php
// GoogleClientLibrary
define('GOOGL_CLIENT_PATH', realpath(dirname(__FILE__).'/../'));
require_once GOOGL_CLIENT_PATH .'/google-api-php-client/src/Google_Client.php';
require_once GOOGL_CLIENT_PATH . '/google-api-php-client/src/contrib/Google_AnalyticsService.php';
// GAPI
define('GAPI_PATH', realpath(dirname(__FILE__).'/../'));
require_once GAPI_PATH.'/GAPI.php';

session_start();
session_regenerate_id(true);


// OAuth2.0でデータ取得
$auth_type = 'OAuth2';

// リダイレクトURIは現在アクセス中のURIにしておく。事前に登録しておくこと！
$redirect_uri = (isset($_SERVER['HTTPS'])?'https://':'http://') . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'];

// GAPI初期化
$gapi = new GAPI($auth_type);
// ClientID
$gapi->setClientId('');
// ClientSecret
$gapi->setClientSecret('');
// RedirectURI
$gapi->setRedirectUri($redirect_uri);

// codeチェック
if (isset($_GET['code'])) {
    try {
        // 認証
        $gapi->authenticate($_GET['code']);
        if ($gapi->getAccessToken()) {
            // 取得したトークンをセッションにセット
            $_SESSION['token'] = $gapi->getAccessToken();
        }
    } catch(Exception $e) {
        
    }
    // 更新で再度認証に行かないようにリダイレクト
    header('Location: ' . $redirect_uri);
    exit;
}
// トークン取得済みだったらセット
if (isset($_SESSION['token'])) {
    $gapi->setAccessToken($_SESSION['token']);
}

?><html>
<head>
    <title>GAPI OAuth2.0テスト</title>
</head>
<body>
<?php
if ($gapi->getAccessToken()) {
    $ga_profile_id = 'ga:00000000';                         // プロファイルID        
    $ga_metrics = 'ga:pageviews';                           // Pageviews取得
    $ga_start_date = date('Y-m-d', strtotime('-7 day'));    // 取得開始日：7日前
    $ga_end_date = date('Y-m-d', strtotime('-1 day'));      // 取得終了日：1日前
    $max_session_results = 5;                               // 一度に取得する件数
    $ga_options = array(
        'dimensions' => 'ga:pageTitle,ga:pagePath',         // ページ名とURL取得
        'sort' => '-ga:pageviews',                          // pageviewsで降順に並び替え
        'start-index' => 1,                                 // 開始index
        'max-results' => 1000,                              // 最大取得件数
    );
    try {
        // 1回の接続で取得する最大件数を設定
        $gapi->setMaxSessionResults($max_session_results);
        // GAへのリクエスト内容をGoogleLibraly準拠で設定
        $gapi->requestGoogleAnalyticsData($ga_profile_id, $ga_start_date, $ga_end_date, $ga_metrics, $ga_options);
        echo '<table border="1"><tr><th>セッション番号</th><th>順位</th><th>ページタイトル</th><th>URL</th><th>PageViews</th></tr>', PHP_EOL;
        // 1件ごとにデータ取得して表示
        while($result = $gapi->fetchResult(GAPI::FETCH_ARRAY)) {
            echo '<tr>',
                '<td>', htmlspecialchars($gapi->getSessionIndex(), ENT_QUOTES), '</td>',
                '<td>', htmlspecialchars($gapi->getResultIndex(), ENT_QUOTES), '</td>',
                '<td>', htmlspecialchars($result['pageTitle'], ENT_QUOTES), '</td>',
                '<td>', htmlspecialchars($result['pagePath'], ENT_QUOTES), '</td>',
                '<td>', htmlspecialchars($result['pageviews'], ENT_QUOTES), '</td>',
                '</tr>', PHP_EOL;
        }
        echo '</table>';
    } catch (Exception $e) {
        echo $e->getMessage();
    }
} else {
    // code取得用リンク表示
    echo '<a href="', htmlspecialchars($gapi->createAuthUrl(), ENT_QUOTES), '" >認証</a>';
}
?>
</body>
</html>