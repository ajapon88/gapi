<?php
/**
 * GooglAPI用ClientLoginクラス
 * Googleアカウントを使用して認証を行う
 * ClientIdおよびClientSecretをGoogleアカウント情報として使用する
 * 使用するサービスタイプ（）を記述する必要がある
 * @version 0.6.2oauth2_access_type
 */

class GAPI_Google_AuthException extends Google_AuthException
{
}

class GAPI_Google_ClientLogin extends Google_Auth {

    const CLIENT_LOGIN_URI = 'https://www.google.com/accounts/ClientLogin';

    public $accessToken = null;
    public $clientId = null;        // email
    public $clientSecret = null;    // パスワード
    public $accessType = null;
    public $developerKey = null;

    public function __construct() {
        $this->clientId = $this->getApiConfig('oauth2_client_id');
        $this->clientSecret = $this->getApiConfig('oauth2_client_secret');
        $this->accessType = $this->getApiConfig('oauth2_access_type');
        $this->developerKey = $this->getApiConfig('developer_key');
    }
    
    function getApiConfig($key, $default = '') {
        global $apiConfig;
        return isset($apiConfig[$key]) ? $apiConfig[$key] : $default;
    }
    
    public function setDeveloperKey($key) {/* noop*/}
    public function setAccessToken($accessToken) {/* noop*/}
    public function getAccessToken() {return $this->accessToken;}
    public function createAuthUrl($scope) {/* noop*/}
    public function revokeToken() {/* noop*/}
    
    public function authenticate($service, $code = null)
    {
        global $apiConfig;
        
        // apiConfigeに記述されているservicesのscopeからserviceを取得する
        $target_scope = $service['scope'];
        foreach($apiConfig['services'] as $type => $scope) {
            if (!is_array($scope)) {
                $scope = array($scope);
            }
            foreach($scope as $seq_scope) {
                if ($seq_scope == $target_scope) {
                    $this->accessType = $type;
                    return $this->refreshToken();
                }
            }
        }
        
        if ($this->accessType) {
            return $this->refreshToken();
        }
        
        return false;
    }

    // ClientLoginでaccess_token取得する
    public function refreshToken($refreshToken=null)
    {
        $post_variables = array(
            'accountType' => 'GOOGLE',
            'Email' => $this->clientId,
            'Passwd' => $this->clientSecret,
            'service' => $this->accessType,
        );
        
        $request = Google_Client::$io->makeRequest(new Google_HttpRequest(self::CLIENT_LOGIN_URI, 'POST', array(), $post_variables));

        if ($request->getResponseHttpCode() == 200) {
            parse_str(str_replace(array("\n","\r\n"), '&', $request->getResponseBody()), $access_token);
            $this->accessToken = $access_token['Auth'];
            return $this->getAccessToken();
        } else {
            $response = $request->getResponseBody();
            $decodedResponse = json_decode($response, true);
            if ($decodedResponse != null && $decodedResponse['error']) {
                $response = $decodedResponse['error'];
            }
            throw new GAPI_Google_AuthException("Error fetching ClientLogin access token, message: '{$response}'", $request->getResponseHttpCode());
        }
    }
    
    // トークンセット
    public function sign(Google_HttpRequest $request)
    {
        // add the developer key to the request before signing it
        if ($this->developerKey) {
            $requestUrl = $request->getUrl();
            $requestUrl .= (strpos($request->getUrl(), '?') === false) ? '?' : '&';
            $requestUrl .=  'key=' . urlencode($this->developerKey);
            $request->setUrl($requestUrl);
        }
        
        if (!$this->getAccessToken()) {
            $this->refreshToken();
        }

        // Add the ClientLogin header to the request
        if ($this->getAccessToken()) {
            $request->setRequestHeaders(
                array('Authorization' => 'GoogleLogin auth=' . $this->getAccessToken())
            );
        }

        return $request;
    }
}
