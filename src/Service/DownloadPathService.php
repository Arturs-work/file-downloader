<?php

namespace App\Service;

class DownloadPathService
{
    public function __construct(
        private readonly string $tmpDir,
        private readonly string $finalDir
    ) {}

    /**
     * @param string $filename
     *
     * @return string
     */
    public function getTempPath(string $filename): string
    {
        return rtrim($this->tmpDir, '/') . '/' . $filename;
    }

    /**
     * @param string $filename
     *
     * @return string
     */
    public function getFinalPath(string $filename): string
    {
        return rtrim($this->finalDir, '/') . '/' . $filename;
    }
}