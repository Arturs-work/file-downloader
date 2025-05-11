<?php

namespace App\Service;

use App\Entity\DownloadQueue;
use App\Enum\Status;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use React\HttpClient\Client;
use React\EventLoop\LoopInterface;
use React\HttpClient\Response;
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
    private bool $receivedData = false;

    public function __construct(
        private Client $client,
        private LoopInterface $loop,
        private EntityManagerInterface $em,
        private DownloadPathService $downloadPathService,
        private Output $output,
        private Cursor $cursor,
        private int $lineIndex,
        private int $totalFiles
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
        $this->tmpFile = $this->downloadPathService->getTempPath($this->fileName);
        $this->finalFile = $this->downloadPathService->getFinalPath($this->fileName);
        $resume = file_exists($this->tmpFile) ? filesize($this->tmpFile) : 0;

        $request = $this->client->request('GET', $url, [
            'Range' => sprintf('bytes=%u-', $resume)
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

            $response->on('data', function ($chunk) use ($stream, $deferred, $queuedFile) {
                $stream->write($chunk);

                $this->downloadedBytes += strlen($chunk);

                if ($this->expectedSize > 0) {
                    $percent = (int)(($this->downloadedBytes / $this->expectedSize) * 100);

                    if ($percent !== $this->lastPercent) {
                        $this->receivedData = true;
                        $this->lastPercent = $percent;
                        $queuedFile->setBytesDownloaded((string)$this->downloadedBytes);
                        $this->em->flush();

                        $this->updateConsoleValue($percent, true);
                    }
                }
            });

            $response->on('end', function () use ($stream, $deferred, $queuedFile) {
                $stream->on('close', function () use ($deferred, $queuedFile) {
                    try {
                        if ($this->downloadedBytes >= $this->expectedSize &&
                            !rename($this->tmpFile, $this->finalFile)) {
                            $queuedFile->setStatus(Status::ERROR);
                            $queuedFile->setError("Failed to move file to final location");
                            $this->updateConsoleValue('error');
                        } else {
                            $queuedFile->setStatus(Status::DONE);
                            $queuedFile->setError();
                            $this->updateConsoleValue('done');
                        }
                    } catch (Exception $e) {
                        $queuedFile->setStatus(Status::ERROR);
                        $queuedFile->setError($e->getMessage());
                    }

                    if ($this->downloadedBytes >= $this->expectedSize) {
                        $deferred->resolve(true);
                    } else {
                        //update the downloaded bytes count with the actual downloaded value
                        $queuedFile->setBytesDownloaded(filesize($this->tmpFile));

                        $deferred->reject(new \RuntimeException("File incomplete"));
                    }

                    $this->em->flush();
                });

                $stream->end();
            });
        });

        $request->on('error', function (\Exception $e) use ($deferred, $queuedFile) {
            $this->updateConsoleValue('error');

            $queuedFile->setStatus(Status::ERROR);
            $queuedFile->setError($e->getMessage());
            $this->em->flush();

            $deferred->reject($e);
        });

        $request->end();

        return $deferred->promise();
    }

    private function detectExpectedSize(Response $response, int $resumeFrom): ?int
    {
        $headers = $response->getHeaders();
        $contentRange = 'Content-Range';
        $contentLength = 'Content-Length';

        if (isset($headers[$contentRange]) &&
            preg_match('/\/(\d+)$/', $headers[$contentRange], $matches)) {
            return (int)$matches[1];
        }

        if (isset($headers[$contentLength])) {
            return $resumeFrom + (int)$headers[$contentLength];
        }

        return null;
    }

    public function updateConsoleValue(string $value, bool $percent = false): void
    {
        $linesToMoveUp = $this->totalFiles - $this->lineIndex;

        $this->cursor->moveUp($linesToMoveUp);
        $this->cursor->moveToColumn(1);
        $this->cursor->clearLine();

        if ($percent) {
             $this->output->writeln(sprintf('%-20s %3d%%', $this->fileName, $value));
        } else {
            $this->output->writeln(sprintf('%-20s %s', $this->fileName, $value));
        }

        $this->cursor->moveDown($linesToMoveUp - 1);
    }

    public function madeProgress(): bool
    {
        return $this->receivedData;
    }
}
