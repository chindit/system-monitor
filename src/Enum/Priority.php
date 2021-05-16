<?php
declare(strict_types=1);

namespace App\Enum;

use MyCLabs\Enum\Enum;

final class Priority extends Enum
{
	private const LOW = 1;
	private const MEDIUM = 2;
	private const HIGH = 3;
	private const CRITICAL = 4;
}
