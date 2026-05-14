<?php
namespace PHPMailer\PHPMailer;

class OAuth {
    protected $provider;
    protected $oauthToken;
    protected $oauthUserEmail = '';
    protected $oauthClientSecret = '';
    protected $oauthClientId = '';
    protected $oauthRefreshToken = '';

    public function __construct($options) {
        $this->provider = $options['provider'];
        $this->oauthUserEmail = $options['userName'];
        $this->oauthClientSecret = $options['clientSecret'];
        $this->oauthClientId = $options['clientId'];
        $this->oauthRefreshToken = $options['refreshToken'];
    }

    protected function getGrant() {
        return new \League\OAuth2\Client\Grant\RefreshToken();
    }

    protected function getToken() {
        return $this->provider->getAccessToken($this->getGrant(), ['refresh_token' => $this->oauthRefreshToken]);
    }

    public function getOauth64() {
        if (null === $this->oauthToken) $this->oauthToken = $this->getToken();
        return base64_encode('user=' . $this->oauthUserEmail . "\x01auth=Bearer " . $this->oauthToken->getToken() . "\x01\x01");
    }
}
