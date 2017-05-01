<?php

namespace Modular\Fields;

class QueuedState extends StateEngineField {
	const Name = 'QueuedState';

	const Initialising = 'Initialising';    // setting up, not ready to run
	const Queued       = 'Queued';          // ready to run
	const Running      = 'Running';         // actively running
	const Waiting      = 'Waiting';         // waiting for another execution slice or resource
	const Paused       = 'Paused';          // manually paused
	const Cancelled    = 'Cancelled';       // manually cancelled
	const Completed    = 'Completed';       // finished processing, check Outcome
	const Duplicate    = 'Duplicate';       // this one was queued but a similar task already existed for it when it was

	private static $ready_states = [
		self::Queued,
		self::Waiting,
	];

	private static $halt_states = [
		self::Cancelled,
		self::Completed,
	    self::Duplicate
	];

	private static $running_states = [
		self::Running,
	];

	private static $options = [
		self::Initialising => [
			self::Queued,
		    self::Duplicate
		],
		self::Duplicate => [
			self::Queued,
			self::Cancelled
		],
		self::Queued       => [
			self::Running,
			self::Cancelled,
		],
		self::Running      => [
			self::Cancelled,
			self::Completed,
			self::Paused,
			self::Waiting,
		],
		self::Waiting      => [
			self::Cancelled,
			self::Paused,
			self::Running,
		],
		self::Paused       => [
			self::Queued,
			self::Cancelled,
		],
		self::Cancelled    => [],
		self::Completed    => [],
	];

}