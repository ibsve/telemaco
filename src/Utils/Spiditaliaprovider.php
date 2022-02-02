<?php

namespace Infocamere\Telemaco\Utils;

class SpiditaliaProvider implements SpidProviderInterface
{
    private $url = 'https://spid.register.it';
    private $client;
    private $crawler;
    
    public function login(string $username, string $password, object $client, object $crawler)
    {
        $this->client = $client;
        
        $this->crawler = $crawler;
        
        $formLoginSpid = $this->crawler->selectButton('Entra con SPID')->form();
        
        $this->crawler = $this->client->submit($formLoginSpid, ['username' => $username, 'password' => $password]);
        
        return ['client' => $this->client, 'crawler' => $this->crawler];
    }

    public function getUrl()
    {
        return $this->url;
    }
}