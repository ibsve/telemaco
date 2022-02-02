<?php

namespace Infocamere\Telemaco\Utils;

class PosteProvider implements SpidProviderInterface
{
    private $url = 'https://posteid.poste.it';
    private $client;
    private $crawler;
    
    public function login(string $username, string $password, object $client, object $crawler)
    {
        $this->client = $client;
        
        $this->crawler = $crawler;
        
        $formLoginSpid = $this->crawler->selectButton('Entra con SPID')->form();

        $this->crawler = $this->client->submit($formLoginSpid, ['username' => $this->config['username'], 'password' => $this->config['password']]);

        if ($this->crawler->filter('.pa-message')->count() == 0) {
            return false;
        }
        
        $ccc = $this->crawler->filter('#consent_sc')->attr('value');

        $this->crawler = $this->client->request('POST', 'https://posteid.poste.it/jod-fs/consent-login', [
            "consent" => "1",
            "consent_sc" => $ccc,
            "cookies" => $this->client->getCookieJar()->all()
        ]);

        $formRedirect = $this->crawler->selectButton('Invia Autorizzazione di Autenticazione')->form();

        $this->crawler = $this->client->submit($formRedirect);
        
        return ['client' => $this->client, 'crawler' => $this->crawler];
    }

    public function getUrl()
    {
        return $this->url;
    }
}