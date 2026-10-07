<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_sin_sesion_manda_al_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
