<?php

namespace App\Service;

use App\Entity\DownloadQueue;
use App\Enum\Status;
use Doctrine\ORM\EntityManagerInterface;
use React\HttpClient\Client;
use React\EventLoop\LoopInterface;
use React\HttpClient\Response;
use Symfony\Component\Console\Output\OutputInterface;
use React\Promise\Deferred;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Output\Output;

class DownloadFile
{
    private ?string $fileName;
    private ?int $lastPercent = null;
    private ?string $tmpFile = null;
    private ?string $finalFile = null;
    private int $downloadedBytes = 0;
    private int $expectedSize = 0;

    public function __construct(
        private Client $client,
        private LoopInterface $loop,
        private EntityManagerInterface $em,
        private Output $output,
        private Cursor $cursor,
        private int $lineIndex
    ) {}

    public function start(
        DownloadQueue $queuedFile
    ): \React\Promise\PromiseInterface
    {
        $queuedFile->setStatus(Status::IN_PROGRESS);
        $this->em->flush();

        $this->downloadedBytes = (int)$queuedFile->getBytesDownloaded();

        $deferred = new Deferred();

        $url = $queuedFile->getUrl();

        $this->fileName = basename(parse_url($url, PHP_URL_PATH));
        $this->tmpFile = './downloads/tmp/' . $this->fileName;
        $this->finalFile = './downloads/final/' . $this->fileName;
        $resume = file_exists($this->tmpFile) ? filesize($this->tmpFile) : 0;

        $request = $this->client->request('GET', $url, [
            'Range' => 'bytes=' . $resume . '-',
        ]);

        $request->on('response', function ($response) use ($queuedFile, $resume, $deferred) {
            // Check and store size only if not already set
            if (!$this->expectedSize = (int)$queuedFile->getExpectedSize()) {
                $size = $this->detectExpectedSize($response, $resume);

                if ($size !== null) {
                    $this->expectedSize = $size;
                    $queuedFile->setExpectedSize((string)$size);
                    $this->em->flush();
                }
            }

            $stream = new \React\Stream\WritableResourceStream(fopen($this->tmpFile, 'a'), $this->loop);

            $response->on('data', function ($chunk) use ($stream, $queuedFile) {
                $stream->write($chunk);

                $this->downloadedBytes += strlen($chunk);

                if ($this->expectedSize > 0) {
                    $percent = (int)(($this->downloadedBytes / $this->expectedSize) * 100);

                    if ($percent !== $this->lastPercent) {
                        $this->lastPercent = $percent;
                        $queuedFile->setBytesDownloaded((string)$this->downloadedBytes);
                        $this->em->flush();

                        $this->writeToConsole(
                            sprintf('%-20s %3d%%', $this->fileName, $percent)
                        );
                    }
                }
            });

            $response->on('end', function () use ($stream, $deferred, $queuedFile) {
                $stream->on('close', function () use ($deferred, $queuedFile) {
                    $this->writeToConsole(
                        sprintf('%-20s %s', $this->fileName, 'done')
                    );

                    if (!rename($this->tmpFile, $this->finalFile)) {
                        $this->output->writeln("Failed to move file to final location.");
                        $queuedFile->setStatus(Status::ERROR);
                    } else {
                        $queuedFile->setStatus(Status::DONE);
                    }

                    $this->em->flush();
                    $deferred->resolve(true);
                });

                $stream->end();
            });
        });

        $request->on('error', function (\Exception $e) use ($deferred, $queuedFile) {
            $this->output->writeln("Error downloading {$this->fileName}: " . $e->getMessage());
            $queuedFile->setStatus(Status::ERROR);
            $this->em->flush();

            $deferred->reject($e);
        });

        $request->end();

        return $deferred->promise();
    }

    private function detectExpectedSize(Response $response, int $resumeFrom): ?int
    {
        $headers = $response->getHeaders();

        if (isset($headers['Content-Range']) &&
            preg_match('/\/(\d+)$/', $headers['Content-Range'], $matches)) {
            return (int)$matches[1];
        }

        if (isset($headers['Content-Length'])) {
            return $resumeFrom + (int)$headers['Content-Length'];
        }

        return null;
    }

    private function writeToConsole(string $message): void
    {
        $this->cursor->moveUp($this->lineIndex + 1);
        $this->cursor->moveToColumn(1);
        $this->cursor->clearLine();

        $this->output->writeln($message);

        $this->cursor->moveDown($this->lineIndex);
    }
}
