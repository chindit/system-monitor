<?php

namespace App\Service;

class FtpBackup implements BackupInterface
{
	private string $error = '';

	public function __construct(
		private string $server,
		private string $username,
		private string $password)
	{
	}

	public function save(string $file): bool
	{
		$ftpConnection = ftp_connect($this->server);

		if (!$ftpConnection || !ftp_login($ftpConnection, $this->username, $this->password)) {
			$this->error = 'Unable to connect';
			return false;
		}

		ftp_pasv($ftpConnection, true);

		$upload = ftp_put($ftpConnection, substr($file, 4), $file, FTP_BINARY);
		$this->error = sprintf('Upload returned %d', $upload);

		ftp_close($ftpConnection);

		return (bool)$upload;
	}

	public function getLastError(): string
	{
		return $this->error;
	}
}
