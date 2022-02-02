<?php

namespace Infocamere\Telemaco\Utils;

class IntesaProvider implements SpidProviderInterface
{
    private $url = 'https://spid.intesa.it';
    private $client;
    private $crawler;
    
    public function login(string $username, string $password, object $client, object $crawler)
    {
        $this->client = $client;
        
        $this->crawler = $crawler;
        
        $formLoginSpid = $this->crawler->filter('#outer')->form();
        
        $this->crawler = $this->client->submit($formLoginSpid, [
            'ctl00$ctl00$MainContent$LoginForm$nome_utente' => $username,
            'ctl00$ctl00$MainContent$LoginForm$password' => $password
        ]);
        
        return ['client' => $this->client, 'crawler' => $this->crawler];
    }

    public function getUrl()
    {
        return $this->url;
    }
}