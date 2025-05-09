<?php

namespace App\Service;

use App\Service\DownloadFile;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;
use React\HttpClient\Client;
use Symfony\Component\Console\Cursor;

class DownloadManager
{
    private int $maxConcurrent = 2;
    private int $active = 0;

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
        $cursor = new Cursor($output);

        $lineIndexes = [];

        // Reserve lines for all files
        foreach ($queuedFiles as $i => $file) {
            $fileName = basename(parse_url($file->getUrl(), PHP_URL_PATH));
            $output->writeln(sprintf('%-20s %3d%%', $fileName, 0));
            $lineIndexes[spl_object_hash($file)] = $i;
        }

        $this->loop->addPeriodicTimer(1, function () use (&$pending, $output, $cursor, $lineIndexes) {
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
                $lineIndex
            );

            $task->start($queuedFile, $output);
            // $task->start($queuedFile, $output)->then(
            //     fn () => $this->active--,
            //     fn () => $this->active--
            // );
        });
    }
}