<?php
// ClientLogin用Authクラス
require_once __DIR__.'/GapiGoogle_ClientLogin.php';
// データオブジェクトクラス
require_once __DIR__.'/GAPIResult.php';


// GAから複数回に分けてデータを取得するクラス
class GAPI
{
    // データ取得形式
    const FETCH_ARRAY = 0;    // 配列
    const FETCH_OBJECT = 1;    // オブジェクト

    public $service = null;
    public $client = null;
    public $result_mode = 'data';
    public $ga_profile_id = null;
    public $ga_start_date= null;
    public $ga_end_date = null;
    public $ga_metrics = null;
    public $ga_account_id = null;
    public $ga_web_property_id = null;
    public $ga_options = array();
    public $max_results = 0;
    public $max_session_results = 0;
    public $results = array();
    public $results_column_index = array();
    public $results_index = 1;
    public $session_index = 0;

    
    /**
    　* __construct
    　* 
    　* 設定の読み込み、設定の更新、GAPIの初期化などを行う
      *
    　* @param string $auth_type : 認証の種類
    　* @param int $max_session_results : セッション単位でのデータ取得件数
    　* @return instance
    　*/
    public function __construct($auth_type, $max_session_results = 0)
    {
        global $apiConfig;
        
        $this->max_session_results = $max_session_results;
        
        // GoogleAPIClientの設定
        switch ($auth_type) {
            case 'ClientLogin':
                // ClientLogin
                $apiConfig['authClass'] = 'GapiGoogle_ClientLogin';
                $apiConfig['oauth2_access_type'] = 'analytics';
                break;
            case 'OAuth2':
                // OAuth2.0(GoogleLib)
                $apiConfig['authClass'] = 'Google_OAuth2';
                break;
            default:
                throw new Exception(sprintf('Error: Unsupported Auth "%s".', $auth_type));
        }
        
        // GoogleAPIClientを生成
        $this->client = new Google_Client();
        $this->service = new Google_AnalyticsService($this->client);
    }
    
    /**
    　* requestAccountData
    　* 
    　* プロファイルを取得
      *
    　* @param string $ga_account_id : 
    　* @param string $ga_web_property_id : 
    　* @param int $ga_start_index : データ取得開始インデックス
    　* @param int $ga_max_results : データ取得件数
    　* @return void
    　*/
    public function requestAccountData($ga_account_id='~all', $ga_web_property_id='~all', $ga_start_index=1, $ga_max_results=20)
    {
        $this->result_mode = 'profile';
        
        $this->ga_account_id = $ga_account_id;
        $this->ga_web_property_id = $ga_web_property_id;
        
        // start-index
        $this->results_index = $ga_start_index - 1;

        $this->ga_options = array();
        // max-index
        if ($ga_max_results > 0) {
            $this->ga_options['max-results'] = $ga_max_results;
            $this->max_results  = $ga_max_results;
        } else {
            $this->max_results  = 0;
        }
    }
    
    /**
    　* requestGoogleAnalyticsData
    　* 
    　* GoogleAnalyticsからのデータ取得設定
      * GoogleClientLibrary準拠
      *
    　* @param string $ga_profile_id : プロファイルID
    　* @param string $ga_start_date : データ取得開始日
    　* @param int $ga_end_date : データ取得終了日
    　* @param int $ga_metrics : 取得するメトリクス
    　* @param int $ga_options : オプション
    　* @return void
    　*/
    public function requestGoogleAnalyticsData($ga_profile_id, $ga_start_date, $ga_end_date, $ga_metrics, $ga_options)
    {
        $this->result_mode = 'data';
        
        $this->ga_profile_id = $ga_profile_id;
        $this->ga_start_date = $ga_start_date;
        $this->ga_end_date = $ga_end_date;
        $this->ga_metrics = $ga_metrics;
        $this->ga_options = $ga_options;
        $this->max_results = @$ga_options['max-results'];    // ga_optionsからmax-resultsを取得する
        if (isset($ga_options['start-index'])) {
            $this->results_index = $ga_options['start-index'] - 1;
        } else {
            $this->results_index = 0;
        }
        // セッション番号クリア
        $this->session_index = 0;
    }
    
    /**
    　* requestReportData
    　* 
    　* GoogleAnalyticsからのデータ取得設定
      * GAPI準拠
      *
    　* @param string $ga_profile_id : プロファイルID
    　* @param string $ga_dimensions : 取得ディメンション
    　* @param int $ga_metrics : 取得メトリクス
    　* @param int $ga_sort_metric : ソート
    　* @param int $ga_filter : フィルター
    　* @param int $ga_start_date : データ取得開始日
    　* @param int $ga_end_date : データ取得終了日
    　* @param int $ga_start_index : データ取得開始インデックス
    　* @param int $ga_max_results : データ取得件数
    　* @return void
    　*/
    public function requestReportData($ga_profile_id, $ga_dimensions, $ga_metrics, $ga_sort_metric=null, $ga_filter=null, $ga_start_date=null, $ga_end_date=null, $ga_start_index=1, $ga_max_results=-1)
    {
        $this->result_mode = 'data';
        
        // profile_id
        $this->ga_profile_id = 'ga:' . $ga_profile_id;
        // metrics
        if (!is_array($ga_metrics)) {
            $ga_metrics = array($ga_metrics);
        }
        foreach($ga_metrics as &$met) {
            $met = 'ga:' . $met;
        }
        $this->ga_metrics = implode(',', $ga_metrics);


        // option
        $this->ga_options = array();

        // dimensions
        if (!is_array($ga_dimensions)) {
            $ga_dimensions = array($ga_dimensions);
        }
        foreach($ga_dimensions as &$s) {
            $s = 'ga:' . $s;
        }
        $this->ga_options['dimensions'] = implode(',', $ga_dimensions);
        
        // sort
        if ($ga_sort_metric) {
            if (!is_array($ga_sort_metric)) {
                $ga_sort_metric = array($ga_sort_metric);
            }
            foreach($ga_sort_metric as &$s) {
                if (preg_match('/^-/', $s)) {
                    $s = '-ga:' . substr($s, 1);
                } else {
                    $s = 'ga:' . $s;
                }
            }
            $this->ga_options['sort'] = implode(',', $ga_sort_metric);
        }

        // filter
        if ($ga_filter) {
            $process_filter = $this->processFilter($ga_filter);
            if ($process_filter) {
                $this->ga_options['filters'] = $process_filter;
            }
        }
        
        // start-index
        $this->results_index = $ga_start_index - 1;

        // max-index
        if ($ga_max_results > 0) {
            $this->ga_options['max-results'] = $ga_max_results;
            $this->max_results  = $ga_max_results;
        } else {
            $this->max_results  = 0;
        }
        
        // start-date
        if (!$ga_start_date) {
            // デフォルトは1ヵ月前から
            $ga_start_date = date('Y-m-d', strtotime('1 month ago'));
        }
        $this->ga_start_date = $ga_start_date;
        
        // end-date
        if (!$ga_end_date) {
            // 現在まで
            $ga_end_date = date('Y-m-d');
        }
        $this->ga_end_date = $ga_end_date;
        
        // セッション番号クリア
        $this->session_index = 0;
    }
    
    /**
    　* filterValue
    　* 
    　* フィルタの右辺（値）を整形する
      *
    　* @param string $value : 整形する値
    　* @return string $value : 整形後の値
    　*/
    public function filterValue($value)
    {
        $replace_str = array(
            ',' => '\,',
            ';' => '\;',
            '\"' => '"',
            "\\'" => "'",
        );

        $s = substr($value, 0, 1);
        $e = substr($value, -1, 1);
        if (($s == '"' and $e == '"') or ($s == "'" and $e == "'")) {
            $value = substr($value, 1, -1);
        }
        $value = str_replace(array_keys($replace_str), array_values($replace_str), $value);
        
        return $value;
    }
    
    /**
    　* parseFilter
    　* 
    　* フィルタを左辺（dimensions or metrics）、オペレータ、値に分割して整形する
      *
    　* @param string $filter : 整形する条件分
    　* @return string $valid_filter : 整形後の条件分
    　*/
    public function parseFilter($filter)
    {
        $valid_operators = '(!~|=~|==|!=|>|<|>=|<=|=@|!@)';
        $filter= trim($filter);
        preg_match('/(.*)'. $valid_operators .'(.*)/', $filter, $match);

        if ($match) {
            $key = 'ga:' . trim($match[1]);
            $operator = trim($match[2]);
            $value = $this->filterValue(trim($match[3]));
            return $key . $operator . $value;
        }
        
        return $this->filterValue($filter);
    }

    /**
    　* processFilter
    　* 
    　* フィルタを条件単位で分割して整形する
      *
    　* @param string $filter : 整形する条件分
    　* @return string $valid_filter : 整形後の条件分
    　*/
    public function processFilter($filter)
    {
        $parse_operator = array('||' => ',', '&&' => ';');    //パース文字列
        $string_char = array('"', "'");    // 文字列として扱う
        $process_filter = '';
        $s = 0;
        $end_of_str = null;
        $next_escape = false;
        $len = strlen($filter);
        for($i=0; $i < $len; $i++) {
            $c = $filter[$i];
            // \の次の文字はエスケープ(無視)する
            if ($next_escape) {
                $next_escape = false;
                continue;
            } elseif ($c == "\\") {
                $next_escape = true;
                continue;
            }
            // 'もしくは"で囲まれた部分は文字列として扱う
            if ($end_of_str) {
                if ($c == $end_of_str) {
                    $end_of_str = null;
                }
                continue;
            } elseif (in_array($c, $string_char)) {
                $end_of_str = $c;
            }
            // &&もしくは||でパースして整形する
            foreach($parse_operator as $op => $rep) {
                $pos = strpos($filter, $op, $i);
                if (false !== $pos and $pos == $i) {
                    $f = substr($filter, $s, $i-$s);
                    $process_filter .= $this->parseFilter($f) . $rep;
                    $i += strlen($op);
                    $s = $i;
                    break;
                }
            }
        }
        $f = substr($filter, $s);
        $process_filter .= $this->parseFilter($f);
        
        return $process_filter;
    }

    /**
    　* setMaxSessionResults
    　* 
    　* 1回の通信で取得するデータ数を設定
      *
    　* @param string $max_session_results : 1通信の取得データ数
    　* @return void
    　*/
    public function setMaxSessionResults($max_session_results)
    {
        $this->max_session_results = $max_session_results;
    }
    
    /**
    　* getResultIndex
    　* 
    　* 現在取得中のデータ番号取得
     *
    　* @param void
    　* @return int データ番号
    　*/
    public function getResultIndex()
    {
        return $this->results_index;
    }
    
    /**
    　* getSessionIndex
    　* 
    　* 現在のセッション番号取得
      *
    　* @param void
    　* @return int セッション番号
    　*/
    public function getSessionIndex()
    {
        return $this->session_index;
    }
    
    /**
    　* fetchResult
    　* 
    　* GAからデータを一つ取得
      * 呼び出すたびにオフセットを進める
      *
    　* @param int $fetch_type : 返すデータの形式
    　* @return mixid : 取得データ。fetch_typeによって形式は変化する。データがなければfalse
    　*/
    public function fetchResult($fetch_type=self::FETCH_ARRAY)
    {
        // データ取得
        $data = next($this->results);
        if (false === $data) {
            // データがなかったので次のデータをGAから取得する
            $this->ga_options['start-index'] = $this->results_index + 1;
            if ($this->max_session_results > 0 and $this->max_results > 0) {
                // 1セッション最大数と最大数が設定されている
                $this->ga_options['max-results'] = min($this->max_session_results, $this->max_results - $this->results_index);
            } elseif ($this->max_session_results > 0) {
                // 1セッション最大数のみ設定されている
                $this->ga_options['max-results'] = $this->max_session_results;
            } elseif ($this->max_results > 0) {
                // 最大数のみが設定されている
                $this->ga_options['max-results'] = $this->max_results - $this->results_index;
            }elseif ($this->session_index > 0) {
                // 最大数およびセッション最大数が未設定で2回目以降であれば終了
                return false;
            }
            
            // max-resultsが0だったら終了
            if (isset($this->ga_options['max-results']) and $this->ga_options['max-results'] <= 0) {
                return false;
            }
            
            $this->session_index++;
            if ($this->result_mode == 'profile') {
                $data = $this->service->management_profiles->listManagementProfiles($this->ga_account_id, $this->ga_web_property_id, $this->ga_options);
                // データがなければ終了
                if (!isset($data['items'])) {
                    return false;
                }
                $this->results = $data['items'];
            } else {
                $data = $this->service->data_ga->get($this->ga_profile_id, $this->ga_start_date, $this->ga_end_date, $this->ga_metrics, $this->ga_options);
                // データがなければ終了
                if (!isset($data['rows'])) {
                    return false;
                }
                // カラム名を取得
                $this->results_column_index = array();
                foreach($data['columnHeaders'] as $idx => $col) {
                    preg_match('/^ga:(.*)/', $col['name'], $match);
                    $this->results_column_index[$match[1]] = $idx;
                }
                $this->results = $data['rows'];
            }
            $data = current($this->results);
        }
        
        if (false !== $data) {
            $this->results_index++;
            // データがあればカラム名を設定して返す
            if ($this->result_mode == 'data') {
                $result = array();
                foreach($this->results_column_index as $name => $idx) {
                    $result[$name] = $data[$idx];
                }
            } else {
                $result = $data;
            }
            switch($fetch_type) {
                case self::FETCH_OBJECT:
                    return new GAPIResult($result);
                    break;
                case self::FETCH_ARRAY:
                    return $result;
                    break;
                default:
                    return $result;
            }
        }
        
        return false;
    }
    
    
    // GoogleClientのラッパーメソッド群
    
    public function authenticate($code=null)
    {
        $this->client->authenticate($code);
    }

    public function createAuthUrl()
    {
        return $this->client->createAuthUrl();
    }
    
    public function setAccessToken($token)
    {
        $this->client->setAccessToken($token);
    }
    
    public function getAccessToken()
    {
        return $this->client->getAccessToken();
    }
    
    public function isAccessTokenExpired()
    {
        return $this->client->isAccessTokenExpired();
    }
    
    public function setDeveloperKey($developerKey)
    {
        $this->client->setDeveloperKey($developerKey);
    }

    public function setState($state)
    {
        $this->client->setState($state);
    }

    public function setAccessType($accessType)
    {
        $this->client->setAccessType($accessType);
    }

    public function setApprovalPrompt($approvalPrompt)
    {
        $this->client->setApprovalPrompt($approvalPrompt);
    }

    public function setApplicationName($applicationName)
    {
        $this->client->setApplicationName($applicationName);
    }

    public function setClientId($clientId)
    {
        $this->client->setClientId($clientId);
    }

    public function getClientId()
    {
        return $this->client->getClientId();
    }

    public function setClientSecret($clientSecret)
    {
        $this->client->setClientSecret($clientSecret);
    }

    public function getClientSecret()
    {
        return $this->client->getClientSecret();
    }

    public function setRedirectUri($redirectUri)
    {
        $this->client->setRedirectUri($redirectUri);
    }

    public function getRedirectUri()
    {
        return $this->client->getRedirectUri();
    }

    public function refreshToken($refreshToken)
    {
        $this->client->refreshToken($refreshToken);
    }

    public function revokeToken($token = null)
    {
        $this->client->revokeToken($token);
    }
    
    public function verifyIdToken($token = null)
    {
        return $this->client->verifyIdToken($token);
    }
}
