<?php

namespace App\Service;

use App\Enum\NotificationType;
use App\Enum\Priority;
use App\Event\NotificationEvent;
use App\Model\Notification;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MirrorService
{
	private int $completionPercentage = 0;
	private \DateTimeImmutable $lastSync;
	private \DateTimeImmutable $lastUpdate;


	public function __construct(
		private HttpClientInterface $httpClient,
		private Filesystem $filesystem,
		private EventDispatcherInterface $dispatcher,
		private string $mirrorName,
		private string $storagePath)
	{
	}

	public function isMirrorUpToDateOnline(): bool
	{
		try
		{
			$onlineMirrorsStatus = $this->httpClient->request('GET', 'https://www.archlinux.org/mirrors/status/json/');
			$jsonMirrorStatus = $onlineMirrorsStatus->toArray();

			if (!is_iterable($jsonMirrorStatus) || !is_iterable($jsonMirrorStatus['urls'])) {
				return false;
			}

			foreach ($jsonMirrorStatus['urls'] as $mirror) {
				if (!isset($mirror['url']) || !strpos($mirror['url'], $this->mirrorName)) {
					continue;
				}

				$this->completionPercentage = $mirror['completion_pct'];

				if (!isset($mirror['completion_pct']) || $mirror['completion_pct'] < 0.95) {
					$this->dispatcher->dispatch(
						new NotificationEvent(
							new Notification(
								sprintf('Mirror is out of sync.  Actual completion is %f%%', round($mirror['completion_pct'] * 100, 2)),
								null,
								Priority::CRITICAL(),
								NotificationType::MIRROR_COMPLETION(),
								[
									'value' => $mirror['completion_pct'],
								]
							)
						),
						NotificationEvent::NAME
					);

					return true;
				}
			}
		} catch (\Exception $e) {
			return false;
		}
		return true;
	}

	public function getMirrorCompletion(): float
	{
		return round($this->completionPercentage*100, 2);
	}

	public function isMirrorUpToDateOffline(): bool
	{
		if (!$this->filesystem->exists($this->storagePath . '/lastsync') || !$this->filesystem->exists($this->storagePath . '/lastupdate')) {
			return false;
		}

		$this->lastSync = (new \DateTimeImmutable())->setTimestamp(file_get_contents($this->storagePath . '/lastsync'));
		$this->lastUpdate = (new \DateTimeImmutable())->setTimestamp(file_get_contents($this->storagePath . '/lastupdate'));

		if ($this->lastSync < (new \DateTimeImmutable())->sub(new \DateInterval('PT15M'))) {
			$this->dispatcher->dispatch(
				new NotificationEvent(
					new Notification(
						'Mirror potentially out of sync',
						sprintf('Mirror is potentially out of sync.  Last sync was performed at %s', $this->lastSync->format('Y-m-d H:i:s')),
						Priority::MEDIUM(),
						NotificationType::MIRROR_CHECK(),
					)
				),
				NotificationEvent::NAME
			);

			if ($this->lastSync < (new \DateTimeImmutable())->sub(new \DateInterval('PT2H')))
			{
				$this->dispatcher->dispatch(
					new NotificationEvent(
						new Notification(
							sprintf('Mirror potentially out of sync.  Last check was at %s', $this->lastSync->format('Y-m-d H:i:s')),
							null,
							Priority::HIGH(),
							NotificationType::MIRROR_CHECK(),
						)
					),
					NotificationEvent::NAME
				);
			}

			return true;
		}

		if ($this->lastUpdate < (new \DateTimeImmutable())->sub(new \DateInterval('PT6H'))) {
			$this->dispatcher->dispatch(
				new NotificationEvent(
					new Notification(
						'Mirror potentially out of sync',
						sprintf('Mirror is potentially out of sync.  Last update was performed at %s', $this->lastUpdate->format('Y-m-d H:i:s')),
						Priority::MEDIUM(),
						NotificationType::MIRROR_CHECK(),
					)
				),
				NotificationEvent::NAME
			);

			if ($this->lastUpdate < (new \DateTimeImmutable())->sub(new \DateInterval('P1D'))) {
				$this->dispatcher->dispatch(
					new NotificationEvent(
						new Notification(
							sprintf('Mirror potentially out of sync.  Last update was at %s', $this->lastUpdate->format('Y-m-d H:i:s')),
							null,
							Priority::CRITICAL(),
							NotificationType::MIRROR_CHECK(),
						)
					),
					NotificationEvent::NAME
				);
			}

			return true;
		}

		return true;
	}

	public function getLastSyncDate(): \DateTimeImmutable
	{
		return $this->lastSync;
	}

	public function getLastUpdateDate(): \DateTimeImmutable
	{
		return $this->lastUpdate;
	}
}
