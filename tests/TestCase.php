<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Every test starts with the club's languages in place. */
    protected bool $seed = true;

    /**
     * Test requests otherwise say "en-us" like a browser would, which would pick the language;
     * tests that need a browser language send the header themselves.
     */
    protected $defaultHeaders = ['Accept-Language' => ''];
}
