<?php

namespace App\Service;

class DownloadPathService
{
    public function __construct(
        private readonly string $tmpDir,
        private readonly string $finalDir
    ) {}

    public function getTempPath(string $filename): string
    {
        return rtrim($this->tmpDir, '/') . '/' . $filename;
    }

    public function getFinalPath(string $filename): string
    {
        return rtrim($this->finalDir, '/') . '/' . $filename;
    }
}