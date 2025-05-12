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

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error = null;

    public function __construct(
        string $url
    ) {
        $this->url = $url;
        $this->status = Status::PENDING;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @param string $url
     *
     * @return self
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return Status
     */
    public function getStatus(): Status
    {
        return $this->status;
    }

    /**
     * @param Status $status
     *
     * @return self
     */
    public function setStatus(Status $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getBytesDownloaded(): ?string
    {
        return $this->bytesDownloaded;
    }

    /**
     * @param string $bytesDownloaded
     *
     * @return self
     */
    public function setBytesDownloaded(string $bytesDownloaded): self
    {
        $this->bytesDownloaded = $bytesDownloaded;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getExpectedSize(): ?string
    {
        return $this->expectedSize;
    }

    /**
     * @param string $expectedSize
     *
     * @return self
     */
    public function setExpectedSize(string $expectedSize): self
    {
        $this->expectedSize = $expectedSize;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @param string|null $error
     *
     * @return void
     */
    public function setError(?string $error = null): void
    {
        $this->error = $error;
    }

    /**
     * @param int $bytes
     *
     * @return void
     */
    public function updateProgress(int $bytes): void
    {
        $this->setBytesDownloaded($bytes);
    }
}
