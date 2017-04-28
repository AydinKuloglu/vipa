<?php

namespace Vipa\JournalBundle\Tests\Controller;

use Vipa\CoreBundle\Tests\BaseTestSetup as BaseTestCase;

class DesignControllerTest extends BaseTestCase
{

    public function testIndex()
    {
        $this->logIn();
        $client = $this->client;
        $client->request('GET', '/journal/1/design/');

        $this->assertStatusCode(200,$client);
    }

    public function testNew()
    {
        $this->logIn();
        $client = $this->client;
        $client->request('GET', '/journal/1/design/new');

        $this->assertStatusCode(200,$client);
    }

    public function testShow()
    {
        $this->logIn();
        $client = $this->client;
        $client->request('GET', '/journal/1/design/1/show');

        $this->assertStatusCode(200,$client);
    }

    public function testEdit()
    {
        $this->logIn();
        $client = $this->client;
        $client->request('GET', '/journal/1/design/1/edit');

        $this->assertStatusCode(200,$client);
    }

}