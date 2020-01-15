<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;


class MySqlBackupService
{
	/**
	 * @var string
	 */
	private $mysqlUser;

	/**
	 * @var string
	 */
	private $mysqlPassword;

	/**
	 * @var Filesystem
	 */
	private $filesystem;

	/**
	 * @var string
	 */
	private $directory;

	/**
	 * @var string
	 */
	private $backup;

	public function __construct(Filesystem $filesystem, string $mysqlUser, string $mysqlPassword)
	{
		$this->mysqlUser = $mysqlUser;
		$this->mysqlPassword = $mysqlPassword;
		$this->filesystem = $filesystem;
		$this->directory = $this->getBackupDirectory();
		$this->backup = '';
	}

	public function backup(): bool
	{
		// Backup DB
		$backupProcess = Process::fromShellCommandline(sprintf('mysql -u%1$s -p%2$s -N -e \'show databases\' | while read dbname; do mysqldump -u%1$s -p%2$s --complete-insert --routines --triggers --single-transaction "$dbname" > "%3$s$dbname".sql; done;', $this->mysqlUser, $this->mysqlPassword, $this->directory));
		$backupProcess->run();

		if (!$backupProcess->isSuccessful()) {
			dump($backupProcess->getErrorOutput());
		}
		return $backupProcess->isSuccessful();
	}

	public function compress(): bool
	{
		// Create on file
		$archive = $this->concatFiles($this->directory);

		if (!$this->filesystem->exists($archive)) {
			return false;
		}

		// Compress
		$this->backup = $this->compressBackup($archive);

		if (!$this->filesystem->exists($this->backup)) {
			return false;
		}

		return true;
	}

	public function cleanFiles(): void
	{
		$this->filesystem->remove($this->directory);
		$this->filesystem->remove($this->backup);
		$this->filesystem->remove(substr($this->backup, 0, -5));
	}

	public function getBackupFilePath(): string
	{
		return $this->backup;
	}

	protected function getBackupDirectory(): string
	{
		// Prepare target directory
		$storageDirectory = sys_get_temp_dir() . '/db/';
		if($this->filesystem->exists($storageDirectory)) {
			$this->filesystem->remove($storageDirectory);
		}
		$this->filesystem->mkdir($storageDirectory);

		return $storageDirectory;
	}

	protected function concatFiles(string $directory): string
	{
		$filename = $this->getFilename() . '.tar';
		$tempDirectory = sys_get_temp_dir();
		$process = Process::fromShellCommandline(sprintf('tar -cvf %s %s', $tempDirectory . '/' . $filename, $directory));

		$process->run();

		return $tempDirectory . '/' . $filename;

	}

	protected function compressBackup(string $archive): string
	{
		$process = Process::fromShellCommandline(sprintf('zstd -10 --long -o %s %s', $archive . '.zstd', $archive));
		$process->run();

		return $archive . '.zstd';
	}

	protected function getFilename(): string
	{
		$date = new \DateTimeImmutable();


		return 'backup-database-' . $date->format('Y-m-d-h-i-s');
	}
}
