<?php

namespace Grav\Plugin\EmbedFontawesome;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Symfony\Component\HttpClient\HttpClient;

class FontawesomeNpm
{
    // registries
    const DEFAULT_NPM_REGISTRY = 'https://registry.npmjs.org/';
    const FONTAWESOME_NPM_REGISTRY = 'https://npm.fontawesome.com/';

    // packages
    const FONTAWESOME_FREE = '@fortawesome/fontawesome-free';
    const FONTAWESOME_PRO = '@fortawesome/fontawesome-pro';

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var \Symfony\Contracts\HttpClient\HttpClientInterface
     */
    protected $client;

    /**
     * @var string
     */
    protected $accessToken;

    public function __construct()
    {
        $this->config = Grav::instance()->get('config');
        $this->accessToken = $this->config->get('plugins.embed-fontawesome.pro_access_token', '');

        $options = [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ];

        // setup authentication for http client, when pro access token is available
        if (!empty($this->accessToken)) {
            $options['auth_bearer'] = $this->accessToken;
        }

        $this->client = HttpClient::create($options);
    }

    public function getLatest(): array
    {
        $url = empty($this->accessToken)
            ? self::DEFAULT_NPM_REGISTRY . self::FONTAWESOME_FREE
            : self::FONTAWESOME_NPM_REGISTRY . self::FONTAWESOME_PRO;
        $response = $this->client->request('GET', $url . '/latest');
        return $response->toArray();
    }

    public function downloadLatest()
    {
        $latest = $this->getLatest();
        $version = new FontawesomeVersion($latest);

        // extract tarball url for content download
        $tarball = $latest['dist']['tarball'];
        // fetch content from tarball, can be serialized later
        $response = $this->client->request('GET', $tarball);
        return [$version, $response->getContent()];
    }
}
