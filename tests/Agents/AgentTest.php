<?php

namespace Tests\Utopia\Agents;

use PHPUnit\Framework\TestCase;
use Utopia\Agents\Adapter;
use Utopia\Agents\Adapters\OpenAI;
use Utopia\Agents\Agent;

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

    public function testConstructor(): void
    {
        $this->assertSame($this->mockAdapter, $this->agent->getAdapter());
        $this->assertEquals('', $this->agent->getDescription());
        $this->assertEquals([], $this->agent->getCapabilities());
    }

    public function testSetDescription(): void
    {
        $description = 'Test agent description';

        $result = $this->agent->setDescription($description);

        $this->assertSame($this->agent, $result);
        $this->assertEquals($description, $this->agent->getDescription());
    }

    public function testSetCapabilities(): void
    {
        $capabilities = [
            'Capability 1' => 'This is capability 1',
            'Capability 2' => 'This is capability 2'
        ];

        $result = $this->agent->setCapabilities($capabilities);

        $this->assertSame($this->agent, $result);
        $this->assertEquals($capabilities, $this->agent->getCapabilities());
    }

    public function testAddCapability(): void
    {
        // Test adding a single capability
        $result = $this->agent->addCapability('Capability 1', 'This is capability 1');
        $this->assertSame($this->agent, $result);
        $this->assertEquals([
            'Capability 1' => 'This is capability 1'
        ], $this->agent->getCapabilities());

        // Test adding a duplicate capability (should update the content)
        $this->agent->addCapability('Capability 1', 'Updated content');
        $this->assertEquals([
            'Capability 1' => 'Updated content'
        ], $this->agent->getCapabilities());

        // Test adding a second capability
        $this->agent->addCapability('Capability 2', 'This is capability 2');
        $this->assertEquals([
            'Capability 1' => 'Updated content',
            'Capability 2' => 'This is capability 2'
        ], $this->agent->getCapabilities());
    }

    public function testFluentInterface(): void
    {
        $description = 'Test Description';
        $capabilities = [
            'Cap 1' => 'Content 1',
            'Cap 2' => 'Content 2'
        ];

        $result = $this->agent
            ->setDescription($description)
            ->setCapabilities($capabilities)
            ->addCapability('Cap 3', 'Content 3');

        $this->assertSame($this->agent, $result);
        $this->assertEquals($description, $this->agent->getDescription());
        $this->assertEquals([
            'Cap 1' => 'Content 1',
            'Cap 2' => 'Content 2',
            'Cap 3' => 'Content 3'
        ], $this->agent->getCapabilities());
    }
}
