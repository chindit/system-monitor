<?php

namespace App\Service;

use Symfony\Component\Process\Process;

class SystemctlService
{
	private string $systemStatus = 'not-checked';
	private int $failedServicesCount = -1;

	public function isSystemDegraded(): bool
	{
		$systemctl = Process::fromShellCommandline('systemctl status | grep State: | head -1 | awk \'{print $2}\'');
		$systemctl->run();

		$this->systemStatus = strtolower(trim($systemctl->getOutput()));

		return !in_array($this->systemStatus, ['starting', 'running']);
	}

	public function countFailedStatus(): int
	{
		if ($this->failedServicesCount < 0)
		{
			$systemctl = Process::fromShellCommandline('systemctl --failed | grep failed | wc -l');
			$systemctl->run();

			$this->failedServicesCount = (int)trim($systemctl->getOutput());
		}

		return $this->failedServicesCount;
	}

	public function getCurrentSystemStatus(): string
	{
		return $this->systemStatus;
	}

	/**
	 * Return an array of services names.  Ex ['nginx.service', 'php-fpm.service']
	 * @return string[]|array
	 */
	public function getFailedServices(): array
	{
		$systemctl = Process::fromShellCommandline('systemctl --state=failed | grep failed | awk \'{print $2}\'');
		$systemctl->run();

		$failedServices = explode("\n", trim($systemctl->getOutput()));

		if (count($failedServices) !== $this->countFailedStatus()) {
			throw new \OutOfRangeException('Failed services found does not match previous count');
		}

		return $failedServices;
	}

	public function restartService(string $serviceName): bool
	{
		$systemctl = Process::fromShellCommandline(sprintf('systemctl restart %s', $serviceName));
		$systemctl->run();

		return $systemctl->isSuccessful();
	}

	public function getServiceLog(string $serviceName): string
	{
		$previousHour = (new \DateTime('last day'));
		$systemctl = Process::fromShellCommandline(sprintf('journalctl -u %s --since \'%s\'', $serviceName, $previousHour->format('Y-m-d H:i:s')));
		$systemctl->run();

		return trim($systemctl->getOutput());
	}
}
