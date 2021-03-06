<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\authclient;

use Yii;

/**
 * OAuth2 serves as a client for the OAuth 2 flow.
 *
 * In oder to acquire access token perform following sequence:
 *
 * ```php
 * use yii\authclient\OAuth2;
 *
 * $oauthClient = new OAuth2();
 * $url = $oauthClient->buildAuthUrl(); // Build authorization URL
 * Yii::$app->getResponse()->redirect($url); // Redirect to authorization URL.
 * // After user returns at our site:
 * $code = $_GET['code'];
 * $accessToken = $oauthClient->fetchAccessToken($code); // Get access token
 * ```
 *
 * @see http://oauth.net/2/
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class OAuth2 extends BaseOAuth
{
    /**
     * @var string protocol version.
     */
    public $version = '2.0';
    /**
     * @var string OAuth client ID.
     */
    public $clientId;
    /**
     * @var string OAuth client secret.
     */
    public $clientSecret;
    /**
     * @var string token request URL endpoint.
     */
    public $tokenUrl;


    /**
     * Composes user authorization URL.
     * @param array $params additional auth GET params.
     * @return string authorization URL.
     */
    public function buildAuthUrl(array $params = [])
    {
        $defaultParams = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->getReturnUrl(),
            'xoauth_displayname' => Yii::$app->name,
        ];
        if (!empty($this->scope)) {
            $defaultParams['scope'] = $this->scope;
        }

        return $this->composeUrl($this->authUrl, array_merge($defaultParams, $params));
    }

    /**
     * Fetches access token from authorization code.
     * @param string $authCode authorization code, usually comes at $_GET['code'].
     * @param array $params additional request params.
     * @return OAuthToken access token.
     */
    public function fetchAccessToken($authCode, array $params = [])
    {
        $defaultParams = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $authCode,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->getReturnUrl(),
        ];

        $request = $this->createRequest()
            ->setMethod('POST')
            ->setUrl($this->tokenUrl)
            ->setData(array_merge($defaultParams, $params));

        $response = $this->sendRequest($request);

        $token = $this->createToken(['params' => $response]);
        $this->setAccessToken($token);

        return $token;
    }

    /**
     * @inheritdoc
     */
    public function applyAccessTokenToRequest($request, $accessToken)
    {
        $data = $request->getData();
        $data['access_token'] = $accessToken->getToken();
        $request->setData($data);
    }

    /**
     * Gets new auth token to replace expired one.
     * @param OAuthToken $token expired auth token.
     * @return OAuthToken new auth token.
     */
    public function refreshAccessToken(OAuthToken $token)
    {
        $params = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token'
        ];
        $params = array_merge($token->getParams(), $params);

        $request = $this->createRequest()
            ->setMethod('POST')
            ->setUrl($this->tokenUrl)
            ->setData($params);

        $response = $this->sendRequest($request);

        $token = $this->createToken(['params' => $response]);
        $this->setAccessToken($token);

        return $token;
    }

    /**
     * Composes default [[returnUrl]] value.
     * @return string return URL.
     */
    protected function defaultReturnUrl()
    {
        $params = $_GET;
        unset($params['code']);
        $params[0] = Yii::$app->controller->getRoute();

        return Yii::$app->getUrlManager()->createAbsoluteUrl($params);
    }

    /**
     * Creates token from its configuration.
     * @param array $tokenConfig token configuration.
     * @return OAuthToken token instance.
     */
    protected function createToken(array $tokenConfig = [])
    {
        $tokenConfig['tokenParamKey'] = 'access_token';

        return parent::createToken($tokenConfig);
    }

    /**
     * Authenticate OAuth client directly at the provider without third party (user) involved,
     * using 'client_credentials' grant type.
     * @see http://tools.ietf.org/html/rfc6749#section-4.4
     * @param array $params additional request params.
     * @return OAuthToken access token.
     */
    public function authenticateClient($params = [])
    {
        $defaultParams = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'client_credentials',
        ];

        if (!empty($this->scope)) {
            $defaultParams['scope'] = $this->scope;
        }

        $request = $this->createRequest()
            ->setMethod('POST')
            ->setUrl($this->tokenUrl)
            ->setData(array_merge($defaultParams, $params));

        $response = $this->sendRequest($request);

        $token = $this->createToken(['params' => $response]);
        $this->setAccessToken($token);

        return $token;
    }

    /**
     * Authenticates user directly by username/password pair, using 'password' grant type.
     * @see https://tools.ietf.org/html/rfc6749#section-4.3
     * @param string $username
     * @param string $password
     * @param array $params additional request params.
     * @return OAuthToken access token.
     */
    public function authenticateUser($username, $password, $params = [])
    {
        $defaultParams = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'password',
            'username' => $username,
            'password' => $password,
        ];

        if (!empty($this->scope)) {
            $defaultParams['scope'] = $this->scope;
        }

        $request = $this->createRequest()
            ->setMethod('POST')
            ->setUrl($this->tokenUrl)
            ->setData(array_merge($defaultParams, $params));

        $response = $this->sendRequest($request);

        $token = $this->createToken(['params' => $response]);
        $this->setAccessToken($token);

        return $token;
    }
}
