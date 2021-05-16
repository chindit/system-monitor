<?php

namespace App\Command;

use App\Enum\NotificationType;
use App\Enum\Priority;
use App\Event\NotificationEvent;
use App\Model\Notification;
use App\Service\AlertingService;
use App\Service\SystemctlService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ServicesCheckCommand extends Command
{
	protected static $defaultName = 'services:check';
	private bool $quiet = false;

	public function __construct(
		private SystemctlService $systemctlService,
		private EventDispatcherInterface $dispatcher
	)
    {
	    parent::__construct();
    }

	protected function configure()
    {
        $this
            ->setDescription('Check overall services status')
            ->addOption('no-notification', 's', InputOption::VALUE_NONE, 'Don\'t send any notification')
	        ->addOption('no-restart', 'r', InputOption::VALUE_NONE, 'Don\'t restart failed services')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->quiet = (bool)$input->getOption('no-notification');

        $io->comment('Checking services status');

        /*if (!$this->systemctlService->isSystemDegraded()) {
        	$io->success('System is running smoothly.  Enjoy');

        	return 0;
        }*/

        $io->warning('System is unstable');

        $failedServicesCount = $this->systemctlService->countFailedStatus();

        if ($failedServicesCount === 0) {
        	$io->comment('No failed services detected');

        	if (!$this->quiet) {
        		$this->dispatcher->dispatch(
        			new NotificationEvent(
	                    new Notification(
		                    'Degraded state without failed services',
					        sprintf(
					            "0 failed services have been found but system state is degraded.\n\nCurrent status is \"%s\"",
						        $this->systemctlService->getCurrentSystemStatus()
					        ),
					        Priority::LOW(),
					        NotificationType::SYSTEM_DEGRADED(),
				        ),
			        ),
			        NotificationEvent::NAME
		        );
	        }

			return 0;
        }

	    $failedServices = $this->systemctlService->getFailedServices();

        $io->warning(sprintf('%d services are failed.  Services are %s', $failedServicesCount, implode(',', $failedServices)));

        if ($input->getOption('no-restart')) {
	        if (!$this->quiet)
	        {
		        $this->dispatcher->dispatch(
		        	new NotificationEvent(
				        new Notification(
					        'Degraded system state',
					        sprintf(
						        "%d failed services have been found.\n\nFailing services are :\n%s",
						        $failedServicesCount,
						        implode("\n", $failedServices)
					        ),
					        Priority::MEDIUM(),
					        NotificationType::SYSTEM_DEGRADED(),
				        )
			        ),
			        NotificationEvent::NAME
		        );
	        }

        	$io->comment('Services won\'t be restarted as flag is enabled.');
        	$io->comment('End of work.  Bye');

        	return 0;
        }

	    /**
	     * List of services that couldn't have been relaunched
	     */
        $criticalServices = [];
	    /**
	     * List of relaunched services
	     */
	    $relaunchedServices = [];

        for ($i = 1; $i <= $failedServicesCount; $i++) {
        	$io->comment(sprintf('Relaunching %s', $failedServices[$i-1]));

			if ($this->systemctlService->restartService($failedServices[$i-1])) {
				$io->success(sprintf('Service %s relaunched', $failedServices[$i-1]));
				$relaunchedServices[] = $failedServices[$i-1];
			} else {
				$io->error(sprintf('Couldn\'t restart %s', $failedServices[$i-1]));
				$criticalServices[] = [
					'service' => $failedServices[$i-1],
					'logs' => $this->systemctlService->getServiceLog($failedServices[$i-1]),
				];
			}
        }

        // All services have been successfully relaunched
        if (empty($criticalServices)) {
        	// Check server state
	        if (!$this->systemctlService->isSystemDegraded())
	        {
	        	$io->success('All failed services were relaunched');
		        if (!$this->quiet)
		        {
			        $this->dispatcher->dispatch(
			        	new NotificationEvent(
					        new Notification(
						        'System restored - Services relaunched',
						        sprintf(
							        "%d services were failing.  All of there were relaunched and server is now stable.\n\nThis is the list of failed services:\n",
							        $failedServicesCount
						        ) . implode("\n", $relaunchedServices),
						        Priority::MEDIUM(),
						        NotificationType::SYSTEM_SERVICES_RELAUNCHED(),
					        )
				        ),
				        NotificationEvent::NAME
			        );
		        }

		        return 0;
	        } else {
	        	$io->warning('All failed services have been relaunched but system is still unstable');
	        	if (!$this->quiet)
		        {
			        $this->dispatcher->dispatch(
			        	new NotificationEvent(
					        new Notification(
						        'System unstable despite services relaunch',
						        sprintf(
						            "System was unstable.  %d services were relaunched but system is still unstable.\n\n
					                   Actual system status: %s\n\n
					                   Restarted services:\n",
							        $failedServicesCount,
							        $this->systemctlService->getCurrentSystemStatus()) . implode("\n", $relaunchedServices
						        ),
						        Priority::MEDIUM(),
						        NotificationType::SYSTEM_SERVICES_RELAUNCHED(),
					        )
				        ),
				        NotificationEvent::NAME
			        );
		        }

	        	return 0;
	        }
        } else {
        	// System is still unstable
	        $io->error('Unable to restart some services');
	        $servicesFailure = [];
	        foreach ($criticalServices as $service) {
	        	$servicesFailure[] = $service['service'];
	        }
	        if (!$this->quiet)
	        {
		        $errorMessage = 'System is unstable.  Some services are actually failing.' . "\n\n" . 'Here are the failed services with their respective logs:' . "\n\n";
		        foreach ($criticalServices as $service) {
		        	$errorMessage .= $service['service'] . ' : ' . "\n" . $service['logs'] . "\n\n";
		        }
		        $errorMessage .= 'Please check these services as soon as possible.' . "\n" . 'Thanks';

		        $this->dispatcher->dispatch(
		        	new NotificationEvent(
				        new Notification(
					        sprintf('System unstable.  Failed services: %s', implode(',', $servicesFailure)),
					        $errorMessage,
					        Priority::HIGH(),
					        NotificationType::SYSTEM_SERVICES_RELAUNCHED(),
				        )
			        ),
			        NotificationEvent::NAME
		        );
	        }

	        return 0;
        }
    }
}
