# Utopia Agents

[![Build Status](https://travis-ci.org/utopia-php/agents.svg?branch=master)](https://travis-ci.org/utopia-php/agents)
![Total Downloads](https://img.shields.io/packagist/dt/utopia-php/agents.svg)
[![Discord](https://img.shields.io/discord/564160730845151244?label=discord)](https://appwrite.io/discord)

Utopia Agents is a simple and lite library for building and managing AI agents in PHP applications. This library provides a collection of tools and utilities for creating, managing, and orchestrating AI agents with support for multiple AI providers. This library is maintained by the [Appwrite team](https://appwrite.io).

Although this library is part of the [Utopia Framework](https://github.com/utopia-php/framework) project it is dependency free and can be used as standalone with any other PHP project or framework.

## Getting Started

Install using composer:
```bash
composer require utopia-php/agents
```

## System Requirements

Utopia Framework requires PHP 8.0 or later. We recommend using the latest PHP version whenever possible.

## Features

- **Multiple AI Providers** - Support for OpenAI, Anthropic, Deepseek, Perplexity, and XAI APIs
- **Flexible Message Types** - Support for text and structured content in messages
- **Conversation Management** - Easy-to-use conversation handling between agents and users
- **Model Selection** - Choose from various AI models (GPT-4, Claude 3, Deepseek Chat, Sonar, Grok, etc.)
- **Parameter Control** - Fine-tune model behavior with temperature and token controls

## Usage

### Basic Example

```php
<?php

use Utopia\Agents\Agent;
use Utopia\Agents\Roles\User;
use Utopia\Agents\Messages\Text;
use Utopia\Agents\Conversation;
use Utopia\Agents\Adapters\OpenAI;

// Create an agent with OpenAI
$adapter = new OpenAI('your-api-key', OpenAI::MODEL_GPT_4_TURBO);
$agent = new Agent($adapter);

// Create a user
$user = new User('user-1', 'John');

// Start a conversation
$conversation = new Conversation($agent);
$conversation
    ->message($user, new Text('What is artificial intelligence?'))
    ->send();
```

### Using Different AI Providers

#### OpenAI

```php
use Utopia\Agents\Adapters\OpenAI;

$openai = new OpenAI(
    apiKey: 'your-api-key',
    model: OpenAI::MODEL_GPT_4_TURBO,
    maxTokens: 2048,
    temperature: 0.7
);
```

Available OpenAI Models:
- `MODEL_GPT_4_5_PREVIEW`: GPT-4.5 Preview - OpenAI's most advanced model with enhanced reasoning, broader knowledge, and improved instruction following
- `MODEL_GPT_4_1`: GPT-4.1 - Advanced large language model with strong reasoning capabilities and improved context handling
- `MODEL_GPT_4O`: GPT-4o - Multimodal model optimized for both text and image processing with faster response times
- `MODEL_O4_MINI`: o4-mini - Compact version of GPT-4o offering good performance with higher throughput and lower latency
- `MODEL_O3`: o3 - Balanced model offering good performance for general language tasks with efficient resource usage
- `MODEL_O3_MINI`: o3-mini - Streamlined model optimized for speed and efficiency while maintaining good capabilities for routine tasks

#### Anthropic

```php
use Utopia\Agents\Adapters\Anthropic;

$anthropic = new Anthropic(
    apiKey: 'your-api-key',
    model: Anthropic::MODEL_CLAUDE_3_HAIKU,
    maxTokens: 2048,
    temperature: 0.7
);
```

Available Anthropic Models:
- `MODEL_CLAUDE_3_OPUS`: Most powerful model
- `MODEL_CLAUDE_3_HAIKU`: Balanced performance
- `MODEL_CLAUDE_3_HAIKU`: Fast and efficient
- `MODEL_CLAUDE_2_1`: Previous generation

#### Deepseek

```php
use Utopia\Agents\Adapters\Deepseek;

$deepseek = new Deepseek(
    apiKey: 'your-api-key',
    model: Deepseek::MODEL_DEEPSEEK_CHAT,
    maxTokens: 2048,
    temperature: 0.7
);
```

Available Deepseek Models:
- `MODEL_DEEPSEEK_CHAT`: General-purpose chat model
- `MODEL_DEEPSEEK_CODER`: Specialized for code-related tasks

#### Perplexity

```php
use Utopia\Agents\Adapters\Perplexity;

$perplexity = new Perplexity(
    apiKey: 'your-api-key',
    model: Perplexity::MODEL_SONAR,
    maxTokens: 2048,
    temperature: 0.7
);
```

Available Perplexity Models:
- `MODEL_SONAR`: General-purpose search model
- `MODEL_SONAR_PRO`: Enhanced search model
- `MODEL_SONAR_DEEP_RESEARCH`: Advanced search model
- `MODEL_SONAR_REASONING`: Reasoning model
- `MODEL_SONAR_REASONING_PRO`: Enhanced reasoning model

#### XAI

```php
use Utopia\Agents\Adapters\XAI;

$xai = new XAI(
    apiKey: 'your-api-key',
    model: XAI::MODEL_GROK_2_LATEST,
    maxTokens: 2048,
    temperature: 0.7
);
```

Available XAI Models:
- `MODEL_GROK_2_LATEST`: Latest Grok model
- `MODEL_GROK_2_IMAGE`: Latest Grok model with image support

### Managing Conversations

```php
use Utopia\Agents\Roles\User;
use Utopia\Agents\Roles\Assistant;
use Utopia\Agents\Messages\Text;

// Create a conversation with system instructions
$agent = new Agent($adapter);
$agent->setInstructions([
    'description' => 'You are a helpful assistant that can answer questions and help with tasks.',
    'tone' => 'friendly and helpful',
]);

// Initialize roles
$user = new User('user-1'); 
$assistant = new Assistant('assistant-1');

$conversation = new Conversation($agent);
$conversation
    ->message($user, new Text('Hello!'))
    ->message($assistant, new Text('Hi! How can I help you today?'))
    ->message($user, new Text('What is the capital of France?'));

// Send and get response
$response = $conversation->send();
```

### Working with Messages

```php
use Utopia\Agents\Messages\Text;
use Utopia\Agents\Messages\Image;

// Text message
$textMessage = new Text('Hello, how are you?');

// Image message
$imageMessage = new Image($imageBinaryContent);
$mimeType = $imageMessage->getMimeType(); // Get the MIME type of the image
```

## Schema and Schema Objects

You can use the `Schema` class to define a schema for a structured output. The `Schema` class utilizes `SchemaObject`s to define each property of the schema, following the [JSON Schema](https://json-schema.org/) format.

```php
use Utopia\Agents\Schema\Schema;
use Utopia\Agents\Schema\SchemaObject;

$object = new SchemaObject();
$object->addProperty('location', [
    'type' => SchemaObject::TYPE_STRING,
    'description' => 'The city and state, e.g. San Francisco, CA',
]);

$schema = new Schema(
    name: 'get_weather',
    description: 'Get the current weather in a given location in well structured JSON',
    object: $object,
    required: $object->getNames()
);

$agent->setSchema($schema);
```

## Tests

To run all unit tests, use the following Docker command:

```bash
docker compose exec tests vendor/bin/phpunit --configuration phpunit.xml tests
```

To run static code analysis, use the following Psalm command:

```bash
docker compose exec tests vendor/bin/psalm --show-info=true
```

## Security

We take security seriously. If you discover any security-related issues, please email security@appwrite.io instead of using the issue tracker.

## Contributing

All code contributions - including those of people having commit access - must go through a pull request and be approved by a core developer before being merged. This is to ensure a proper review of all the code.

We truly ❤️ pull requests! If you wish to help, you can learn more about how you can contribute to this project in the [contribution guide](CONTRIBUTING.md).

## Copyright and license

The MIT License (MIT) [http://www.opensource.org/licenses/mit-license.php](http://www.opensource.org/licenses/mit-license.php) 