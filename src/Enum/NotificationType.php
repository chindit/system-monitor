<?php
declare(strict_types=1);

namespace App\Enum;

use MyCLabs\Enum\Enum;

final class NotificationType extends Enum
{
	private const MIRROR_CHECK = 'mirror-sync-check';
	private const MIRROR_COMPLETION = 'mirror-sync-completion';
	private const SYSTEM_DEGRADED = 'services-system-degrated';
	private const SYSTEM_SERVICES_RELAUNCHED = 'services-system-relaunched';
}
