<?php

namespace App\Command;

use App\Service\MirrorService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MirrorCheckCommand extends Command
{
    protected static $defaultName = 'mirror:check';
	private MirrorService $mirrorService;

	protected function configure()
    {
        $this
            ->setDescription('Add a short description for your command')
        ;
    }

    public function __construct(MirrorService $mirrorService)
    {
	    parent::__construct();
	    $this->mirrorService = $mirrorService;
    }

	protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->comment('Checking mirror status from online source');
        if (!$this->mirrorService->isMirrorUpToDateOnline()) {
        	$io->error('Unable to check online mirror completion.  An error occurred');

        	return 0;
        }
        $io->comment('End of online mirror status check');

        if ($this->mirrorService->getMirrorCompletion() > 98) {
        	$io->success(sprintf('Mirror is in-sync.  Current completion is %f%%', $this->mirrorService->getMirrorCompletion()));
        } else if ($this->mirrorService->getMirrorCompletion() > 88) {
        	$io->warning(sprintf('Mirror is out of sync but still usable.  Current completion is %f%%', $this->mirrorService->getMirrorCompletion()));
        } else {
        	$io->error(sprintf('Mirror is severely out of sync.  Current completion is %f%%', $this->mirrorService->getMirrorCompletion()));
        }

        $io->comment('Checking mirror status from local source');
        if (!$this->mirrorService->isMirrorUpToDateOffline()) {
        	$io->error('Unable to check local mirror status');

        	return 0;
        }
        $io->comment('End of local mirror status check');

        $io->table(['Action', 'Date'], [['Last sync', $this->mirrorService->getLastSyncDate()->format('Y-m-d H:i:s')], ['Last update', $this->mirrorService->getLastUpdateDate()->format('Y-m-d H:i:s')]]);

        return 0;
    }
}
