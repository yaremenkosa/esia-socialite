<?php

namespace Yaremenkosa\Esia;

use Esia\Config;
use Esia\Exceptions\AbstractEsiaException;
use Esia\Exceptions\InvalidConfigurationException;
use Esia\OpenId;
use Esia\Signer\CliSignerPKCS7;
use Esia\Signer\Exceptions\SignFailException;
use Esia\Signer\SignerInterface;
use Laravel\Socialite\AbstractUser;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

/**
 * Class Provider
 * @package Yaremenkosa\Esia
 */
class Provider extends AbstractProvider
{
    /** @var OpenId */
    protected $esia;

    /** @var string */
    protected string $serviceUrl;

    /** @var Config */
    protected $esiaConfig;

    /** @var array */
    protected static $contactFields = [
        'EML' => 'email',
    ];

    protected function esiaConfig(): Config
    {
        if (!$this->esiaConfig) {
            $this->esiaConfig = $this->makeConfig($this->getConfig());
        }

        return $this->esiaConfig;
    }

    protected function esia(): OpenId
    {
        if (!$this->esia) {
            $this->esia = new OpenId($this->esiaConfig());
            $this->esia()->setSigner($this->makeSigner($this->getConfig('signer') ?? CliSignerPKCS7::class));
        }

        return $this->esia;
    }

    /**
     * @inheritDoc
     */
    protected function getAuthUrl($state)
    {
        return $this->buildUrl();
    }


    /**
     * @inheritDoc
     */
    public function user()
    {
        $response = $this->getAccessTokenResponse($this->getCode());

        return $this->mapUserToObject($this->getUserByToken($response));
    }

    /**
     * @inheritDoc
     */
    protected function getTokenUrl()
    {
        return $this->esiaConfig()->getPortalUrl().'/aas/oauth2/te';
    }

    /**
     * @inheritDoc
     * @throws AbstractEsiaException
     */
    protected function getUserByToken($token)
    {
        $personInfo = $this->esia()->getPersonInfo();
        $contactInfo = $this->mapContactInfo($this->esia()->getContactInfo());

        return $personInfo + $contactInfo + ['oid' => $this->esia()->getConfig()->getOid()];
    }

    /**
     * @param  array  $user
     * @return AbstractUser
     */
    protected function mapUserToObject(array $user): AbstractUser
    {
        return (new User)
            ->setRaw($user)
            ->map(
                [
                    'id' => $user['oid'],
                    'name' => $user['lastName'].' '.$user['firstName'].' '.$user['middleName'],
                    'email' => $user['email'],
                ]
            );
    }

    /**
     * @param  array  $contacts
     * @return array
     */
    protected function mapContactInfo(array $contactInfo): array
    {
        $result = [];
        foreach ($contactInfo as $info) {
            if (array_key_exists($info['type'], self::$contactFields)) {
                $result[self::$contactFields[$info['type']]] = $info['value'];
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     * @throws AbstractEsiaException
     */
    public function getAccessTokenResponse($code): array
    {
        $token = $this->esia()->getToken($code);
        $payload = json_decode($this->base64UrlSafeDecode(explode('.', $token)[1]), true);

        return [
            'access_token' => $token,
            'expires_in' => $payload['exp'],
            'refresh_token' => null,
        ];
    }

    /**
     * @param  array  $config
     * @return Config
     * @throws InvalidConfigurationException
     */
    protected function makeConfig(array $config): Config
    {
        return new Config(
            [
                'clientId' => $config['client_id'],
                'redirectUrl' => $config['redirect'],
                'privateKeyPath' => $config['private_key_path'],
                'certPath' => $config['cert_path'],
                'portalUrl' => $config['service_url'],
                'scope' => $this->scopes,
            ]
        );
    }

    /**
     * @param  string  $signer
     * @return SignerInterface
     */
    protected function makeSigner(string $signer): SignerInterface
    {
        return new $signer(
            $this->esiaConfig()->getCertPath(),
            $this->esiaConfig()->getPrivateKeyPath(),
            $this->esiaConfig()->getPrivateKeyPassword(),
            $this->esiaConfig()->getTmpPath(),
        );
    }

    /**
     * Url safe for base64
     * @param  string  $string
     * @return string
     */
    private function base64UrlSafeDecode(string $string): string
    {
        $base64 = strtr($string, '-_', '+/');

        return base64_decode($base64);
    }

    /**
     * @return string
     * @throws SignFailException
     */
    public function buildUrl(): string
    {
        return $this->esia()->buildUrl();
    }

    public static function additionalConfigKeys()
    {
        return [
            'service_url',
            'private_key_path',
            'private_key_password',
            'cert_path',
            'tmp_path',
            'signer',
        ];
    }
}
