<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EspaceClientTest extends TestCase
{
    public function test_guest_can_open_the_client_space_pages(): void
    {
        $this->get('/espace-client/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('EspaceClient/Index')
                ->where('sieges.SGK', 'Siège Garage Kagnan')
                ->where('sieges.ZGK', 'Zone Industrielle Garage Kagnan')
                ->where('sieges.YGK', 'Yamoussoukro Garage Kagnan'));
        $this->get('/espace-client/tableau-de-bord')->assertOk();
    }
}
