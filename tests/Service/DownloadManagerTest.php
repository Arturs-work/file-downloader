<?php

namespace App\Tests\Service;

use App\Entity\DownloadQueue;
use App\Enum\Status;
use App\Service\DownloadFile;
use App\Service\DownloadManager;
use App\Service\DownloadPathService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\HttpClient\Client;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Output\OutputInterface;

class DownloadManagerTest extends TestCase
{
    private DownloadManager $manager;
    private Client $client;
    private LoopInterface $loop;
    private EntityManagerInterface $em;
    private DownloadPathService $downloadPathService;
    private OutputInterface $output;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->loop = $this->createMock(LoopInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->downloadPathService = $this->createMock(DownloadPathService::class);
        $this->output = $this->createMock(OutputInterface::class);

        $this->manager = new DownloadManager(
            $this->client,
            $this->loop,
            $this->em,
            $this->downloadPathService
        );
    }

    public function testRunWithQueuedFiles(): void
    {
        $queuedFile = new DownloadQueue('http://example.com/test.txt');
        $queuedFiles = [$queuedFile];

        // Mock loop timer
        $this->loop->expects($this->exactly(2))
            ->method('addPeriodicTimer')
            ->withConsecutive(
                [1, $this->anything()],
                [10, $this->anything()]
            );

        // Mock output
        $this->output->expects($this->once())
            ->method('writeln')
            ->with($this->stringContains('test.txt'));

        $this->manager->run($queuedFiles, $this->output);
    }
} 