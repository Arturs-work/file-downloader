<?php

namespace App\Entity;

use App\Repository\DownloadQueueRepository;
use App\Enum\Status;
use App\Entity\Traits\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DownloadQueueRepository::class)]
#[ORM\HasLifecycleCallbacks]
class DownloadQueue
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $url;

    #[ORM\Column(enumType: Status::class)]
    private Status $status;

    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $bytesDownloaded = '0';

    #[ORM\Column(type: 'bigint', nullable: true, options: ['unsigned' => true])]
    private ?string $expectedSize = null;

    public function __construct(
        string $url
    ) {
        $this->url = $url;
        $this->status = Status::PENDING;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getBytesDownloaded(): ?string
    {
        return $this->bytesDownloaded;
    }

    public function setBytesDownloaded(string $bytesDownloaded): self
    {
        $this->bytesDownloaded = $bytesDownloaded;

        return $this;
    }

    public function getExpectedSize(): ?string
    {
        return $this->expectedSize;
    }

    public function setExpectedSize(string $expectedSize): self
    {
        $this->expectedSize = $expectedSize;

        return $this;
    }

    public function markAsComplete(): void
    {
        $this->setStatus(Status::DONE);
    }

    public function updateProgress(int $bytes): void
    {
        $this->setBytesDownloaded($bytes);
    }
}
