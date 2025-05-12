<?php

namespace App\Tests\Service;

use App\Service\DownloadPathService;
use PHPUnit\Framework\TestCase;

class DownloadPathServiceTest extends TestCase
{
    private DownloadPathService $service;
    private string $tmpDir;
    private string $finalDir;

    protected function setUp(): void
    {
        $this->tmpDir = '/tmp/downloads';
        $this->finalDir = '/var/downloads';
        $this->service = new DownloadPathService($this->tmpDir, $this->finalDir);
    }

    public function testGetTempPath(): void
    {
        $filename = 'test.txt';
        $expectedPath = '/tmp/downloads/test.txt';
        
        $this->assertEquals($expectedPath, $this->service->getTempPath($filename));
    }

    public function testGetFinalPath(): void
    {
        $filename = 'test.txt';
        $expectedPath = '/var/downloads/test.txt';
        
        $this->assertEquals($expectedPath, $this->service->getFinalPath($filename));
    }
} 