<?php

namespace Yaremenkosa\Esia;

use Esia\Config;
use Esia\Exceptions\AbstractEsiaException;
use Esia\Exceptions\InvalidConfigurationException;
use Esia\OpenId;
use Esia\Signer\CliSignerPKCS7;
use Esia\Signer\Exceptions\SignFailException;
use Esia\Signer\SignerInterface;
use Illuminate\Http\Request;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

/**
 * Class EsiaProvider
 * @package Yaremenkosa\Esia
 */
class EsiaProvider extends AbstractProvider implements ProviderInterface
{
    /** @var OpenId */
    protected OpenId $esia;

    /** @var string */
    protected string $serviceUrl;
    /**
     * @var Config
     */
    private Config $config;

    /**
     * Provider constructor.
     * @param  Request  $request
     * @param  array  $config
     * @param  array  $guzzle
     * @param  string  $clientSecret
     * @throws InvalidConfigurationException
     */
    public function __construct(
        Request $request,
        array $config,
        array $guzzle = [],
        string $clientSecret = ''
    ) {
        $this->config = $this->makeConfig($config);

        parent::__construct(
            $request,
            $this->config->getClientId(),
            $clientSecret,
            $this->config->getRedirectUrl(),
            $guzzle
        );

        $this->esia = new OpenId($this->config);
        $this->esia->setSigner($this->makeSigner($config['signer'] ?? CliSignerPKCS7::class));
    }

    /**
     * @inheritDoc
     */
    protected function getAuthUrl($state)
    {
        return $this->config->getPortalUrl();
    }

    /**
     * @inheritDoc
     */
    protected function getTokenUrl()
    {
        return $this->config->getPortalUrl().'/aas/oauth2/te';
    }

    /**
     * @inheritDoc
     * @throws AbstractEsiaException
     */
    protected function getUserByToken($token)
    {
        return $this->esia->getPersonInfo() + ['oid' => $this->esia->getConfig()->getOid()];
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
                ]
            );
    }

    /**
     * @inheritDoc
     * @throws AbstractEsiaException
     */
    public function getAccessTokenResponse($code): array
    {
        $token = $this->esia->getToken($code);
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
                'clientId' => $config['clientId'],
                'redirectUrl' => $config['redirectUrl'],
                'privateKeyPath' => $config['privateKeyPath'],
                'certPath' => $config['certPath'],
                'portalUrl' => $config['serviceUrl'],
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
            $this->config->getCertPath(),
            $this->config->getPrivateKeyPath(),
            $this->config->getPrivateKeyPassword(),
            $this->config->getTmpPath(),
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
        return $this->esia->buildUrl();
    }
}