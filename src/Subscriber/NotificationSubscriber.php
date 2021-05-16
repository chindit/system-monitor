<?php
declare(strict_types=1);

namespace App\Subscriber;

use App\Enum\Priority;
use App\Event\NotificationEvent;
use App\Model\Notification;
use App\Service\AlertingService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class NotificationSubscriber implements EventSubscriberInterface
{
	public static function getSubscribedEvents()
	{
		return [
			NotificationEvent::NAME => 'onNotification'
		];
	}

	public function __construct(
		private CacheInterface $cache,
		private AlertingService $alerting
	)
	{
	}

	public function onNotification(NotificationEvent $event): void
	{
		$cacheKey = $event->getNotification()->getCode();
		$lastNotification = $this->cache->getItem($cacheKey);
		if ($lastNotification->isHit()) {
			if (
				$lastNotification->get()['value']
				&& $event->getNotification()->getContext()['value']
				&& $event->getNotification()->getContext()['value'] < $lastNotification->get()['value']
			) {
				$this->notify($event->getNotification());
			}
		} else {
			$this->cache->get($cacheKey, function (ItemInterface $item) use ($event) {
				switch ($event->getNotification()->getPriority())
				{
					case Priority::LOW():
						$expiration = new \DateTimeImmutable("+1 day");
						break;
					case Priority::MEDIUM():
						$expiration = new \DateTimeImmutable("+12 hours");
						break;
					case Priority::HIGH():
						$expiration = new \DateTimeImmutable("+ 4 hours");
						break;
					case Priority::CRITICAL():
						$expiration = new \DateTimeImmutable("+2 hours");
						break;
					default:
						$expiration = new \DateTimeImmutable("+2 days");
				}

				$item->expiresAt($expiration);

				$item->set($event->getNotification()->getContext());

				return $event->getNotification()->getContext();
			});

			$this->notify($event->getNotification());
		}
	}

	private function notify(Notification $notification): void
	{
		if ($notification->getPriority()->getValue() >= Priority::HIGH()->getValue()) {
			$this->alerting->sendSMS($notification->getObject());
		}

		$this->alerting->sendMail($notification->getObject(),$notification->getMessage());
	}
}
