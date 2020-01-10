#!/usr/bin/env php

<?php

$buildRoot = __DIR__;
$phar = new Phar($buildRoot . '/build/system-monitor.phar', 0, 'system-monitor.phar');
$include = '/^(?=(.*src|.*bin|.*vendor|.*config|.*public|.*var))(.*)$/i';
$phar->buildFromDirectory($buildRoot, $include);
$phar->addFile('.env.local.php');
$phar->setStub("#!/usr/bin/env php\n" . $phar->createDefaultStub("bin/console"));
