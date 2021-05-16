<?php

namespace App\Service;

use Ovh\Sms\SmsApi;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class AlertingService
{
	public function __construct(
		private MailerInterface $mailer,
		private SmsApi $smsApi,
		private string $emailSender,
		private string $emailReceiver,
		private string $smsReceiver
	)
	{
	}

	public function sendMail(string $object, string $body): bool
	{
		$email = (new Email())
			->from($this->emailSender)
			->to($this->emailReceiver)
			->priority(Email::PRIORITY_HIGH)
			->subject($object)
			->text($body);

		try
		{
			$this->mailer->send($email);

			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function sendSMS(string $message): bool
	{
		$accounts = $this->smsApi->getAccounts();
		$this->smsApi->setAccount($accounts[0]);
		try {
			$sms = $this->smsApi->createMessage(false);
			$sms->addReceiver($this->smsReceiver);
			$sms->setIsMarketing(false);
			$sms->send($message);

			return true;
		} catch (\Exception $e) {
			return false;
		}
	}
}
