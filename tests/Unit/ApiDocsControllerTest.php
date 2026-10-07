<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controller\ApiDocsController;
use App\Utils\Config;
use flight\Engine;
use PHPUnit\Framework\TestCase;

class ApiDocsControllerTest extends TestCase
{
    public function testOpenapiReturnsDocumentWithConfiguredServer(): void
    {
        $json = [];
        $app = $this->getMockBuilder(Engine::class)
            ->disableOriginalConstructor()
            ->addMethods(['json'])
            ->getMock();
        $app->method('json')->willReturnCallback(
            static function (array $payload, int $status) use (&$json): void {
                $json = ['payload' => $payload, 'status' => $status];
            }
        );

        $controller = new ApiDocsController(
            $app,
            new Config(['app' => ['base_url' => '/saku-wni']])
        );
        $controller->openapi();

        $this->assertSame(200, $json['status']);
        $this->assertIsArray($json['payload']);
        $document = $json['payload'];
        $this->assertSame('3.0.3', $document['openapi']);
        $this->assertSame('/saku-wni/', $document['servers'][0]['url']);
        $this->assertArrayHasKey('/api/games/{id}/close', $document['paths']);
        $this->assertArrayHasKey('/api/auth/verify-otp', $document['paths']);
    }
}
