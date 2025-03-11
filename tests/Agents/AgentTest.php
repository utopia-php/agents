<?php

namespace Tests\Utopia\Agents;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Utopia\Agents\Agent;
use Utopia\Agents\Adapter;
use Utopia\Agents\Adapters\OpenAI;

class AgentTest extends TestCase
{
    private Agent $agent;
    private Adapter $mockAdapter;

    protected function setUp(): void
    {
        // Create a mock adapter
        $this->mockAdapter = new OpenAI('test-api-key', OpenAI::MODEL_GPT_3_5_TURBO);
        $this->agent = new Agent($this->mockAdapter);
    }

    public function testConstructor()
    {
        $this->assertSame($this->mockAdapter, $this->agent->getAdapter());
        $this->assertEquals('', $this->agent->getDescription());
        $this->assertEquals([], $this->agent->getCapabilities());
    }

    public function testSetDescription()
    {
        $description = 'Test agent description';
        
        $result = $this->agent->setDescription($description);
        
        $this->assertSame($this->agent, $result);
        $this->assertEquals($description, $this->agent->getDescription());
    }

    public function testSetCapabilities()
    {
        $capabilities = ['capability1', 'capability2'];
        
        $result = $this->agent->setCapabilities($capabilities);
        
        $this->assertSame($this->agent, $result);
        $this->assertEquals($capabilities, $this->agent->getCapabilities());
    }

    public function testAddCapability()
    {
        // Test adding a single capability
        $result = $this->agent->addCapability('capability1');
        $this->assertSame($this->agent, $result);
        $this->assertEquals(['capability1'], $this->agent->getCapabilities());

        // Test adding a duplicate capability (should not add)
        $this->agent->addCapability('capability1');
        $this->assertEquals(['capability1'], $this->agent->getCapabilities());

        // Test adding a second unique capability
        $this->agent->addCapability('capability2');
        $this->assertEquals(['capability1', 'capability2'], $this->agent->getCapabilities());
    }

    public function testFluentInterface()
    {
        $description = 'Test Description';
        $capabilities = ['cap1', 'cap2'];

        $result = $this->agent
            ->setDescription($description)
            ->setCapabilities($capabilities)
            ->addCapability('cap3');

        $this->assertSame($this->agent, $result);
        $this->assertEquals($description, $this->agent->getDescription());
        $this->assertEquals(['cap1', 'cap2', 'cap3'], $this->agent->getCapabilities());
    }
} 