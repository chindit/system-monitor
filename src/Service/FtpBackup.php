<?php

namespace App\Service;

class FtpBackup implements BackupInterface
{
	/**
	 * @var string
	 */
	private $server;

	/**
	 * @var string
	 */
	private $username;

	/**
	 * @var string
	 */
	private $password;

	public function __construct(string $server, string $username, string $password)
	{
		$this->server = $server;
		$this->username = $username;
		$this->password = $password;
	}

	public function save(string $file): bool
	{
		$ftpConnection = ftp_connect($this->server);

		if (!$ftpConnection || !ftp_login($ftpConnection, $this->username, $this->password)) {
			return false;
		}

		$upload = ftp_put($ftpConnection, '/', $file, FTP_BINARY);

		ftp_close($ftpConnection);

		return (bool)$upload;
	}
}
