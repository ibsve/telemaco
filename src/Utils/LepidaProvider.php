<?php

namespace Infocamere\Telemaco\Utils;

class LepidaProvider implements SpidProviderInterface
{
    private $url = 'https://id.lepida.it/idp/shibboleth';
    private $client;
    private $crawler;
    
    public function login(string $username, string $password, object $client, object $crawler)
    {
        $this->client = $client;
        
        $this->crawler = $crawler;
        
        $formLoginSpid = $this->crawler->filter('#spid-login')->form();
        
        $this->crawler = $this->client->submit($formLoginSpid, ['j_username' => $username, 'j_password' => $password]);
        
        return ['client' => $this->client, 'crawler' => $this->crawler];
    }

    public function getUrl()
    {
        return $this->url;
    }
}