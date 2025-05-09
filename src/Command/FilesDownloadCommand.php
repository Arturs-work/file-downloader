<?php

namespace App\Command;

use App\Entity\DownloadQueue;
use App\Service\DownloadManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use React\EventLoop\Loop;

#[AsCommand(
    name: 'files:download',
    description: 'Download a files from urls',
)]
class FilesDownloadCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private DownloadManager $manager
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('urls', InputArgument::IS_ARRAY, 'List of urls to download');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queuedFiles = $this->em->getRepository(DownloadQueue::class)->getQueuedFiles();

        if (empty($queuedFiles)) {
            $output->writeln('[Idle] No downloads in queue.');
        }

        $this->manager->run($queuedFiles, $output);

        Loop::run();

        return Command::SUCCESS;
    }
}
