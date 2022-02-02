<?php

namespace Infocamere\Telemaco\Utils;

class InfocertProvider implements SpidProviderInterface
{
    private $url = 'https://identity.infocert.it';
    private $client;
    private $crawler;
    
    public function login(string $username, string $password, object $client, object $crawler)
    {
        $this->client = $client;
        
        $this->crawler = $crawler;
        
        $formLoginSpid = $this->crawler->filter('#spid-login')->form();
        
        $this->crawler = $this->client->submit($formLoginSpid, ['username' => $username, 'password' => $password]);

        if ($this->crawler->filter('.pa-message')->count() == 0) {
            return false;
        }

        $formAutorizza = $this->crawler->filter('#spid-login')->form();

        $this->crawler = $this->client->submit($formAutorizza);

        $formRedirect = $this->crawler->filter('.button-continue')->form();

        $this->crawler = $this->client->submit($formRedirect);
        
        return ['client' => $this->client, 'crawler' => $this->crawler];     
    }

    public function getUrl()
    {
        return $this->url;
    }
}