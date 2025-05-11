<?php

namespace App\Service;

use App\Entity\DownloadQueue;
use App\Enum\Status;
use App\Service\DownloadFile;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;
use React\HttpClient\Client;
use Symfony\Component\Console\Cursor;

class DownloadManager
{
    private int $maxConcurrent = 1;
    private int $active = 0;
    private int $maxRetries = 3;
    private array $retryCounts = [];

    public function __construct(
        private Client $client,
        private LoopInterface $loop,
        private EntityManagerInterface $em
    ) {}

    public function run(
        array $queuedFiles,
        OutputInterface $output
    ): void
    {
        $pending = $queuedFiles;
        $total = count($queuedFiles);
        $cursor = new Cursor($output);

        $lineIndexes = [];

        // Reserve output lines for all files
        foreach ($queuedFiles as $i => $file) {
            $fileName = basename(parse_url($file->getUrl(), PHP_URL_PATH));
            $output->writeln(sprintf('%-20s %s', $fileName, 'waiting...'));
            $lineIndexes[spl_object_hash($file)] = $i;
        }

        $this->loop->addPeriodicTimer(1, function () use (&$pending, $output, $cursor, $lineIndexes, $total) {
            if (empty($pending)) {
                return;
            }

            if ($this->active >= $this->maxConcurrent) {
                return;
            }

            $queuedFile = array_shift($pending);

            $this->active++;

            $fileHash = spl_object_hash($queuedFile);
            $lineIndex = $lineIndexes[$fileHash] ?? 0;

            $task = new DownloadFile(
                $this->client,
                $this->loop,
                $this->em,
                $output,
                $cursor,
                $lineIndex,
                $total
            );

            $task->start($queuedFile, $output)->then(
                fn () => $this->active--,
                function (\Throwable $e) use ($queuedFile, &$pending, $task) {
                    $this->active--;

                    if ($task->madeProgress()) {
                        unset($this->retryCounts[$queuedFile->getId()]);
                    }

                    if ($this->shouldRetry($queuedFile)) {
                        $this->scheduleRetry($queuedFile, $pending, $task);
                    } else {
                        $task->updateConsoleValue('failed');
                        $queuedFile->setStatus(Status::FAILED);
                        $this->em->flush();
                    }
                }
            );
        });
    }

    private function getRetryKey(DownloadQueue $file): string
    {
        return (string) $file->getId();
    }

    private function shouldRetry(DownloadQueue $file): bool
    {
        $key = $this->getRetryKey($file);
        return ($this->retryCounts[$key] ?? 0) < $this->maxRetries;
    }

    private function scheduleRetry(DownloadQueue $file, array &$pending, DownloadFile $task): void
    {
        $key = $this->getRetryKey($file);

        if (!isset($this->retryCounts[$key])) {
            $this->retryCounts[$key] = 0;
        }

        $attempt = ++$this->retryCounts[$key];
        $delay = match($attempt) {
            1 => 10,
            2 => 20,
            3 => 30,
            default => 30
        };

        $task->updateConsoleValue(sprintf('retrying in %u seconds...', $delay));

        $this->loop->addTimer($delay, function () use ($file, &$pending) {
            array_push($pending, $file); // add back to the back of the queue
        });
    }
}