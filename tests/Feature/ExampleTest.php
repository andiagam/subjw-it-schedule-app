<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_roster_requires_a_signed_in_user(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
