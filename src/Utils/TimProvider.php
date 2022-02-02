<?php

namespace Infocamere\Telemaco\Utils;

class TimProvider implements SpidProviderInterface
{
    private $url = 'https://loginspid.aruba.it';
    private $client;
    private $crawler;
    
    public function login(string $username, string $password, object $client, object $crawler)
    {
        $this->client = $client;
        
        $this->crawler = $crawler;
        
        $formLoginSpid = $this->crawler->filter('#spid-login')->form();
        
        $this->crawler = $this->client->submit($formLoginSpid, ['username' => $username, 'password' => $password]);

        return ['client' => $this->client, 'crawler' => $this->crawler];
    }

    public function getUrl()
    {
        return $this->url;
    }
}