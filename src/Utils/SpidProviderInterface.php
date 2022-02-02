<?php

namespace Infocamere\Telemaco\Utils;

interface SpidProviderInterface
{
    public function login(string $username, string $password, object $client, object $crawler);

    public function getUrl();
}