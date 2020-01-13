<?php

namespace App\Service;

interface BackupInterface
{
	public function save(string $file): bool;
}
