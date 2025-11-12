<?php

namespace Tests\Unit\Services;

use App\Services\RedirectService;
use Illuminate\Http\Request;
use Tests\TestCase;

class RedirectServiceTest extends TestCase
{
    protected RedirectService $redirectService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->redirectService = new RedirectService();
    }

    /** @test */
    public function it_returns_mobile_entry_redirect_for_lift_logs_store()
    {
        $request = Request::create('/', 'POST', [
            'redirect_to' => 'mobile-entry',
            'date' => '2024-01-15',
        ]);

        $redirect = $this->redirectService->getRedirect(
            'lift_logs',
            'store',
            $request,
            ['submitted_lift_log_id' => 123],
            'Success!'
        );

        $this->assertEquals(302, $redirect->getStatusCode());
        $this->assertStringContainsString('mobile-entry/lifts', $redirect->getTargetUrl());
        $this->assertEquals('Success!', $redirect->getSession()->get('success'));
    }

    /** @test */
    public function it_returns_default_redirect_when_no_redirect_to_specified()
    {
        $request = Request::create('/', 'POST', [
            'date' => '2024-01-15',
        ]);

        $redirect = $this->redirectService->getRedirect(
            'lift_logs',
            'store',
            $request,
            [
                'submitted_lift_log_id' => 123,
                'exercise' => 456,
            ],
            'Success!'
        );

        $this->assertEquals(302, $redirect->getStatusCode());
        $this->assertStringContainsString('exercises', $redirect->getTargetUrl());
    }

    /** @test */
    public function it_validates_redirect_targets()
    {
        $this->assertTrue(
            $this->redirectService->isValidRedirectTarget('lift_logs', 'store', 'mobile-entry')
        );

        $this->assertTrue(
            $this->redirectService->isValidRedirectTarget('lift_logs', 'store', 'default')
        );

        $this->assertFalse(
            $this->redirectService->isValidRedirectTarget('lift_logs', 'store', 'invalid-target')
        );
    }

    /** @test */
    public function it_handles_dynamic_redirects()
    {
        $request = Request::create('/', 'POST', [
            'redirect_to' => 'mobile-entry.foods',
            'date' => '2024-01-15',
        ]);

        $redirect = $this->redirectService->getRedirect(
            'food_logs',
            'destroy',
            $request,
            ['date' => '2024-01-15'],
            'Deleted!'
        );

        $this->assertEquals(302, $redirect->getStatusCode());
        $this->assertStringContainsString('mobile-entry/foods', $redirect->getTargetUrl());
    }

    /** @test */
    public function it_throws_exception_for_invalid_configuration()
    {
        $this->expectException(\InvalidArgumentException::class);

        $request = Request::create('/', 'POST');

        $this->redirectService->getRedirect(
            'invalid_controller',
            'invalid_action',
            $request,
            [],
            'Message'
        );
    }

    /** @test */
    public function it_resolves_parameters_from_context()
    {
        $request = Request::create('/', 'POST', [
            'redirect_to' => 'mobile-entry',
            'date' => '2024-01-15',
        ]);

        $redirect = $this->redirectService->getRedirect(
            'lift_logs',
            'store',
            $request,
            [
                'submitted_lift_log_id' => 999,
                'exercise' => 123,
            ],
            'Success!'
        );

        $url = $redirect->getTargetUrl();
        $this->assertStringContainsString('date=2024-01-15', $url);
        $this->assertStringContainsString('submitted_lift_log_id=999', $url);
    }

    /** @test */
    public function it_handles_body_logs_redirects()
    {
        $request = Request::create('/', 'POST', [
            'redirect_to' => 'mobile-entry-measurements',
            'date' => '2024-01-15',
        ]);

        $redirect = $this->redirectService->getRedirect(
            'body_logs',
            'store',
            $request,
            [],
            'Measurement logged!'
        );

        $this->assertEquals(302, $redirect->getStatusCode());
        $this->assertStringContainsString('mobile-entry/measurements', $redirect->getTargetUrl());
    }

    /** @test */
    public function it_handles_workout_templates_redirect()
    {
        $request = Request::create('/', 'POST', [
            'redirect_to' => 'workout-templates',
            'date' => '2024-01-15',
            'template_id' => 42,
        ]);

        $redirect = $this->redirectService->getRedirect(
            'lift_logs',
            'store',
            $request,
            ['submitted_lift_log_id' => 123],
            'Success!'
        );

        $this->assertEquals(302, $redirect->getStatusCode());
        $this->assertStringContainsString('workout-templates', $redirect->getTargetUrl());
    }
}
