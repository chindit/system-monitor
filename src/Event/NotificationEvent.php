<?php
declare(strict_types=1);

namespace App\Event;

use App\Model\Notification;
use Symfony\Contracts\EventDispatcher\Event;

class NotificationEvent extends Event
{
	public const NAME = 'notification.event';

	public function __construct(protected Notification $notification)
	{
	}

	public function getNotification(): Notification
	{
		return $this->notification;
	}
}
