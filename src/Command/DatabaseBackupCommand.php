<?php

namespace App\Command;

use App\Service\BackupInterface;
use App\Service\MySqlBackupService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DatabaseBackupCommand extends Command
{
    protected static $defaultName = 'database:backup';

	/**
	 * @var MySqlBackupService
	 */
	private $sqlBackupService;

	/**
	 * @var BackupInterface
	 */
	private $backup;

	protected function configure()
    {
        $this
            ->setDescription('Backup a database')
        ;
    }

    public function __construct(MySqlBackupService $sqlBackupService, BackupInterface $backup)
    {
	    parent::__construct();
	    $this->sqlBackupService = $sqlBackupService;
	    $this->backup = $backup;
    }

	protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->comment('Starting backup');
		if (!$this->sqlBackupService->backup()) {
			$io->error('Backup failed.  Stopping process');

			return 0;
		}

		$io->comment('Backup done');
		$io->comment('Starting compression');

		if (!$this->sqlBackupService->compress()) {
			$io->error('Compression failed.  Stopping process');

			return 0;
		}

		$io->comment('Compression done');
		$io->comment('Starting upload');
		if (!$this->backup->save($this->sqlBackupService->getBackupFilePath()))
		{
			$io->error('Upload to FTP failed.  Stopping process');

			return 0;
		}
		$io->comment('Upload done');

		$io->comment('Cleaning files');
		$this->sqlBackupService->cleanFiles();
		$io->comment('Files cleaned');

		$io->success('Database backed up successfully');

        return 0;
    }
}
