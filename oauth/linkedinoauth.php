<?php
/*
 * A class handling the OAuth verification procedure for LinkedIn
 *
 * Based on the Twitter version by Abraham Williams (abraham@abrah.am) http://abrah.am
 *
 */

/* Load OAuth lib. You can find it at http://oauth.net */
require_once('oauth.php');

class LinkedInOAuth {
    // Contains the last HTTP status code returned
    private $http_status;

    // Contains the last API call
    private $last_api_call;

    // The base of the LinkedIn OAuth URLs
    public $LINKEDIN_API_ROOT = 'https://api.linkedin.com/';

    public $request_options = array(
    );

    /**
    * Set API URLS
    */
    function requestTokenURL() { return $this->LINKEDIN_API_ROOT.'uas/oauth/requestToken'; }
    function authorizeURL() { return $this->LINKEDIN_API_ROOT.'uas/oauth/authorize'; }
    function accessTokenURL() { return $this->LINKEDIN_API_ROOT.'uas/oauth/accessToken'; }

    /**
    * Debug helpers
    */
    function lastStatusCode() { return $this->http_status; }
    function lastAPICall() { return $this->last_api_call; }

    function __construct($consumer_key, $consumer_secret, $oauth_token = NULL, $oauth_token_secret = NULL) {/*{{{*/
        $this->sha1_method = new OAuthSignatureMethod_HMAC_SHA1();
        $this->consumer = new OAuthConsumer($consumer_key, $consumer_secret);
        if (!empty($oauth_token) && !empty($oauth_token_secret)) {
            $this->token = new OAuthConsumer($oauth_token, $oauth_token_secret);
        } else {
            $this->token = NULL;
        }
    }/*}}}*/

    /**
    * Get a request_token from LinkedIn
    *
    * @returns a key/value array containing oauth_token and oauth_token_secret
    */
    function getRequestToken() {/*{{{*/
        $requesturl = $this->requestTokenURL();
        $r = $this->oAuthRequest($requesturl, $this->request_options, 'GET');
        error_log('OAuth request: '.$requesturl);
        error_log('OAuth Response: '.print_r($r, true));
        $token = $this->oAuthParseResponse($r);
        $this->token = new OAuthConsumer($token['oauth_token'], $token['oauth_token_secret']);
        return $token;
    }/*}}}*/

    /**
    * Parse a URL-encoded OAuth response
    *
    * @return a key/value array
    */
    function oAuthParseResponse($responseString) {
        $r = array();
        foreach (explode('&', $responseString) as $param) {
            $pair = explode('=', $param, 2);
            if (count($pair) != 2) continue;
            $r[urldecode($pair[0])] = urldecode($pair[1]);
        }
        return $r;
    }

    /**
    * Get the authorize URL
    *
    * @returns a string
    */
    function getAuthorizeURL($token, $callbackurl) {/*{{{*/
        if (is_array($token)) $token = $token['oauth_token'];
        $result = $this->authorizeURL();
        $result .= '?oauth_token=' . $token;
        $result .= '&oauth_callback=' . urlencode($callbackurl);
        
        return $result;
    }/*}}}*/

    /**
    * Exchange the request token and secret for an access token and
    * secret, to sign API calls.
    *
    * @returns array("oauth_token" => the access token,
    *                "oauth_token_secret" => the access secret)
    */
    function getAccessToken($verifier) {/*{{{*/
        $r = $this->oAuthRequest($this->accessTokenURL(), array('oauth_verifier' => $verifier), 'GET');
        error_log('$r: '.print_r($r, true));
        $token = $this->oAuthParseResponse($r);
        $this->token = new OAuthConsumer($token['oauth_token'], $token['oauth_token_secret']);
        return $token;
    }/*}}}*/

    /**
    * Format and sign an OAuth / API request
    */
    function oAuthRequest($url, $args = array(), $method = NULL) {/*{{{*/
        if (empty($method)) $method = empty($args) ? "GET" : "POST";
        $req = OAuthRequest::from_consumer_and_token($this->consumer, $this->token, $method, $url, $args);
        $req->sign_request($this->sha1_method, $this->consumer, $this->token);
        switch ($method) {
            case 'GET': return $this->http($req->to_url());
            case 'POST': return $this->http($req->get_normalized_http_url(), $req->to_postdata());
        }
    }/*}}}*/

    /**
    * Make an HTTP request
    *
    * @return API results
    */
    function http($url, $post_data = null) {/*{{{*/
        error_log("Calling '$url'");
        $ch = curl_init();
        if (defined("CURL_CA_BUNDLE_PATH")) curl_setopt($ch, CURLOPT_CAINFO, CURL_CA_BUNDLE_PATH);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        //////////////////////////////////////////////////
        ///// Set to 1 to verify Twitter's SSL Cert //////
        //////////////////////////////////////////////////
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        if (isset($post_data)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }
        $response = curl_exec($ch);
        $this->http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->last_api_call = $url;
        curl_close ($ch);
        return $response;
    }/*}}}*/
}/*}}}*/