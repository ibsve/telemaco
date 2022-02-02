<?php

namespace Infocamere\Telemaco\Utils;

class SielteProvider implements SpidProviderInterface
{
    private $url = 'https://identity.sieltecloud.it';
    private $client;
    private $crawler;
    
    public function login(string $username, string $password, object $client, object $crawler)
    {
        $this->client = $client;
        
        $this->crawler = $crawler;
        
        $formLoginSpid = $this->crawler->filter('#piLoginForm')->form();
        
        $this->crawler = $this->client->submit($formLoginSpid, ['username' => $username, 'password' => $password]);
        
        return ['client' => $this->client, 'crawler' => $this->crawler];
    }

    public function getUrl()
    {
        return $this->url;
    }
}