<?php

namespace Tests\Unit\Services;

use App\Services\ComponentBuilder;
use Illuminate\Support\MessageBag;
use Tests\TestCase;

class ComponentBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear session before each test
        session()->flush();
    }

    /** @test */
    public function messages_from_session_returns_null_when_no_messages()
    {
        $result = ComponentBuilder::messagesFromSession();
        
        $this->assertNull($result);
    }

    /** @test */
    public function messages_from_session_handles_success_messages()
    {
        session(['success' => 'Operation completed successfully']);
        
        $result = ComponentBuilder::messagesFromSession();
        
        $this->assertNotNull($result);
        $this->assertEquals('messages', $result['type']);
        $this->assertStringContainsString('Operation completed successfully', $result['data']['messages'][0]['text']);
        $this->assertEquals('success', $result['data']['messages'][0]['type']);
    }

    /** @test */
    public function messages_from_session_handles_error_messages()
    {
        session(['error' => 'Something went wrong']);
        
        $result = ComponentBuilder::messagesFromSession();
        
        $this->assertNotNull($result);
        $this->assertEquals('messages', $result['type']);
        $this->assertStringContainsString('Something went wrong', $result['data']['messages'][0]['text']);
        $this->assertEquals('error', $result['data']['messages'][0]['type']);
    }

    /** @test */
    public function messages_from_session_handles_warning_messages()
    {
        session(['warning' => 'This is a warning']);
        
        $result = ComponentBuilder::messagesFromSession();
        
        $this->assertNotNull($result);
        $this->assertEquals('messages', $result['type']);
        $this->assertStringContainsString('This is a warning', $result['data']['messages'][0]['text']);
        $this->assertEquals('warning', $result['data']['messages'][0]['type']);
    }

    /** @test */
    public function messages_from_session_handles_info_messages()
    {
        session(['info' => 'Here is some information']);
        
        $result = ComponentBuilder::messagesFromSession();
        
        $this->assertNotNull($result);
        $this->assertEquals('messages', $result['type']);
        $this->assertStringContainsString('Here is some information', $result['data']['messages'][0]['text']);
        $this->assertEquals('info', $result['data']['messages'][0]['type']);
    }

    /** @test */
    public function messages_from_session_handles_single_validation_error()
    {
        $errors = new MessageBag(['reps' => 'The reps field must be at least 1']);
        session(['errors' => $errors]);
        
        $result = ComponentBuilder::messagesFromSession();
        
        $this->assertNotNull($result);
        $this->assertEquals('messages', $result['type']);
        $this->assertCount(1, $result['data']['messages']);
        $this->assertEquals('The reps field must be at least 1', $result['data']['messages'][0]['text']);
        $this->assertEquals('error', $result['data']['messages'][0]['type']);
    }

    /** @test */
    public function messages_from_session_displays_multiple_validation_errors_as_separate_messages()
    {
        $errors = new MessageBag([
            'reps' => 'The reps field must be at least 1',
            'rounds' => 'The rounds field must be at least 1',
            'weight' => 'The weight field is required'
        ]);
        session(['errors' => $errors]);
        
        $result = ComponentBuilder::messagesFromSession();
        
        $this->assertNotNull($result);
        $this->assertEquals('messages', $result['type']);
        $this->assertCount(3, $result['data']['messages']);
        
        // Each error should be a separate message
        $errorMessages = array_column($result['data']['messages'], 'text');
        $this->assertContains('The reps field must be at least 1', $errorMessages);
        $this->assertContains('The rounds field must be at least 1', $errorMessages);
        $this->assertContains('The weight field is required', $errorMessages);
        
        // All should be error type
        foreach ($result['data']['messages'] as $message) {
            $this->assertEquals('error', $message['type']);
        }
    }

    /** @test */
    public function messages_from_session_handles_multiple_message_types()
    {
        session([
            'success' => 'Operation completed',
            'warning' => 'Be careful',
            'info' => 'Additional info'
        ]);
        
        $result = ComponentBuilder::messagesFromSession();
        
        $this->assertNotNull($result);
        $this->assertEquals('messages', $result['type']);
        $this->assertCount(3, $result['data']['messages']);
        
        // Check that all message types are present
        $messageTypes = array_column($result['data']['messages'], 'type');
        $this->assertContains('success', $messageTypes);
        $this->assertContains('warning', $messageTypes);
        $this->assertContains('info', $messageTypes);
    }

    /** @test */
    public function messages_from_session_combines_flash_messages_and_validation_errors()
    {
        session(['success' => 'Something worked']);
        
        $errors = new MessageBag(['reps' => 'The reps field must be at least 1']);
        session(['errors' => $errors]);
        
        $result = ComponentBuilder::messagesFromSession();
        
        $this->assertNotNull($result);
        $this->assertEquals('messages', $result['type']);
        $this->assertCount(2, $result['data']['messages']);
        
        // Check that both success and error messages are present
        $messageTypes = array_column($result['data']['messages'], 'type');
        $this->assertContains('success', $messageTypes);
        $this->assertContains('error', $messageTypes);
        
        $messageTexts = array_column($result['data']['messages'], 'text');
        $this->assertContains('Something worked', $messageTexts);
        $this->assertContains('The reps field must be at least 1', $messageTexts);
    }

    /** @test */
    public function messages_from_session_ignores_empty_validation_errors()
    {
        $errors = new MessageBag([]);
        session(['errors' => $errors]);
        
        $result = ComponentBuilder::messagesFromSession();
        
        $this->assertNull($result);
    }

    /** @test */
    public function messages_from_session_handles_html_content_in_validation_errors_safely()
    {
        $errors = new MessageBag(['field' => 'Error with <script>alert("xss")</script> content']);
        session(['errors' => $errors]);
        
        $result = ComponentBuilder::messagesFromSession();
        
        $this->assertNotNull($result);
        $errorMessage = $result['data']['messages'][0]['text'];
        
        // The error message should contain the HTML as-is since Laravel validation messages
        // are considered safe content and will be escaped by Blade when rendered
        $this->assertStringContainsString('Error with <script>alert("xss")</script> content', $errorMessage);
    }

    /** @test */
    public function messages_from_session_handles_validation_errors_with_multiple_messages_per_field()
    {
        $errors = new MessageBag([
            'password' => ['The password field is required.', 'The password must be at least 8 characters.']
        ]);
        session(['errors' => $errors]);
        
        $result = ComponentBuilder::messagesFromSession();
        
        $this->assertNotNull($result);
        $this->assertCount(2, $result['data']['messages']);
        
        // Should show both validation messages as separate error messages
        $messageTexts = array_column($result['data']['messages'], 'text');
        $this->assertContains('The password field is required.', $messageTexts);
        $this->assertContains('The password must be at least 8 characters.', $messageTexts);
        
        // Both should be error type
        foreach ($result['data']['messages'] as $message) {
            $this->assertEquals('error', $message['type']);
        }
    }
}