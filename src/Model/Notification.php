<?php
declare(strict_types=1);

namespace App\Model;

use App\Enum\NotificationType;
use App\Enum\Priority;
use JetBrains\PhpStorm\Pure;

class Notification
{
	public function __construct(
		private string $object,
		private ?string $message,
		private Priority $priority,
		private NotificationType $type,
		private array $context = []
	)
	{
		if ($this->message === null) {
			$this->message = $this->object;
		}
	}

	#[Pure] public function getCode(): string
	{
		return $this->type->getValue() . '_' . $this->priority->getKey();
	}

	public function getPriority(): Priority
	{
		return $this->priority;
	}

	public function getContext(): array
	{
		return array_merge($this->context, ['createdAt' => new \DateTimeImmutable()]);
	}

	public function getObject(): string
	{
		return $this->object;
	}

	public function getMessage(): string
	{
		return $this->message;
	}
}
