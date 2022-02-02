<?php

namespace Infocamere\Telemaco\Utils;

class NamirialProvider implements SpidProviderInterface
{
    private $url = 'https://idp.namirialtsp.com/idp';
    private $client;
    private $crawler;
    
    public function login(string $username, string $password, object $client, object $crawler)
    {
        $this->client = $client;
        
        $this->crawler = $crawler;
        
        $formLoginSpid = $this->crawler->filter('#fm1')->form();
        
        $this->crawler = $this->client->submit($formLoginSpid, ['input_username' => $username, 'input_password' => $password]);
        
        return ['client' => $this->client, 'crawler' => $this->crawler];
    }

    public function getUrl()
    {
        return $this->url;
    }
}