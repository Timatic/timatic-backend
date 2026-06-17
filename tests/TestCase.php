<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Concerns\LoginUser;

abstract class TestCase extends BaseTestCase
{
    use LoginUser;
    use WithFaker;

    protected bool $seed = true;
}
