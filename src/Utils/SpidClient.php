<?php

namespace Infocamere\Telemaco\Utils;

class SpidClient
{
    private $provider;
    private $client;
    private $crawler;

    public function setProvider(SpidProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    public function getClient()
    {
        return $this->client;
    }

    public function setClient(\Goutte\Client $client)
    {
        $this->client = $client;
    }

    public function getCrawler()
    {
        return $this->crawler;
    }

    public function setCrawler(\Symfony\Component\DomCrawler\Crawler $crawler)
    {
        $this->crawler = $crawler;
    }

    public function getUrl()
    {
        return $this->provider->getUrl();
    }

    public function doLogin(string $username, string $password)
    {
        $provider = $this->provider->login($username, $password, $this->client, $this->crawler);

        if ($provider) {
            $this->client = $provider['client'];

            $this->crawler = $provider['crawler'];

            return true;
        }

        return false;
    }
}