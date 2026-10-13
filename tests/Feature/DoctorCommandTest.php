<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_command_runs_successfully(): void
    {
        $this->artisan('maysha:doctor')
            ->expectsOutputToContain('Maysha Backend System Health & Diagnostics')
            ->expectsOutputToContain('All 27 core e-commerce tables exist in the database.')
            ->expectsOutputToContain('All critical e-commerce and auth columns verified.')
            ->assertExitCode(0);
    }
}
