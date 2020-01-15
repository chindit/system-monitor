<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MirrorService
{
	/**
	 * @var HttpClientInterface
	 */
	private $httpClient;

	/**
	 * @var string
	 */
	private $mirrorName;

	/**
	 * @var string
	 */
	private $storagePath;

	/**
	 * @var AlertingService
	 */
	private $alertingService;

	/**
	 * @var int
	 */
	private $completionPercentage = 0;

	/**
	 * @var Filesystem
	 */
	private $filesystem;

	/**
	 * @var \DateTimeImmutable
	 */
	private $lastSync;

	/**
	 * @var \DateTimeImmutable
	 */
	private $lastUpdate;

	public function __construct(HttpClientInterface $httpClient, AlertingService $alertingService, Filesystem $filesystem, string $mirrorName, string $storagePath)
	{
		$this->httpClient = $httpClient;
		$this->mirrorName = $mirrorName;
		$this->storagePath = $storagePath;
		$this->alertingService = $alertingService;
		$this->filesystem = $filesystem;
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
					$this->alertingService->sendMail('Mirror out of sync',
					                                 sprintf('Mirror is out of sync.  Completion is %f%%', round($mirror['completion_pct'], 2))
					);
					$this->alertingService->sendSMS(sprintf('Mirror is out of sync.  Actual completion is %f%%', round($mirror['completion_pct'], 2)));

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
			$this->alertingService->sendMail('Mirror potentially out of sync',
				sprintf('Mirror is potentially out of sync.  Last sync was performed at %s', $this->lastSync->format('Y-m-d H:i:s')));

			if ($this->lastSync < (new \DateTimeImmutable())->sub(new \DateInterval('PT2H'))) {
				$this->alertingService->sendSMS(sprintf('Mirror potentially out of sync.  Last check was at %s', $this->lastSync->format('Y-m-d H:i:s')));
			}

			return true;
		}

		if ($this->lastUpdate < (new \DateTimeImmutable())->sub(new \DateInterval('PT6H'))) {
			$this->alertingService->sendMail('Mirror potentially out of sync',
			                                 sprintf('Mirror is potentially out of sync.  Last update was performed at %s', $this->lastUpdate->format('Y-m-d H:i:s')));

			if ($this->lastUpdate < (new \DateTimeImmutable())->sub(new \DateInterval('P1D'))) {
				$this->alertingService->sendSMS(sprintf('Mirror potentially out of sync.  Last update was at %s', $this->lastUpdate->format('Y-m-d H:i:s')));
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
