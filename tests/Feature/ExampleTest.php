<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_루트는_관리_화면으로_이동한다(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }
}
