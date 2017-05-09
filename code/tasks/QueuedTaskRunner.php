<?php

namespace Modular\Tasks;

use Modular\Fields\EventDate;
use Modular\Fields\JSONData;
use Modular\Fields\MethodName;
use Modular\Fields\Outcome;
use Modular\Models\QueuedTask;
use Modular\Traits\timeout;

class QueuedTaskRunner extends QueuedTaskHandler {
	// run as soon as the EventDate is passed
	const GracePeriodField = EventDate::class;

	private static $grace_period = 'Now';

	protected $description = "Runs queued tasks which match passed parameters. Should be setup to run on a regular basis e.g. via cron.";

	/**
	 * @param array|\ArrayAccess $params
	 *
	 * @param string             $resultMessage
	 *
	 * @return mixed|void
	 * @throws \Exception
	 * @throws \InvalidArgumentException
	 * @throws \ValidationException
	 */
	public function execute( $params = [], &$resultMessage = '' ) {
		$this->timeout();

		$tasks = $this->tasks( $params );

		$this->debug_trace($tasks->sql());

		/** @var QueuedTask $task */

		$count = $tasks->count();

		$this->debug_info( "running $count tasks" );

		$tally   = 0;
		$skipped = 0;
		foreach ( $tasks as $task ) {
			if ( ! $task->canRun() ) {
				$skipped ++;
				// skip it
				continue;
			}
			$task->markRunning();

			$args       = array_merge(
				(array) JSONData::object( $task->{JSONData::Name} ),
				$params
			);
			$methodName = $task->{MethodName::Name};

			$this->debug_info( "calling $methodName on task '" . $task->Title . "'" );
			// call the method on the queued task, and handle the returned result

			try {
				if ( $task->$methodName( $args, $resultMessage ) ) {
					$task->markComplete( Outcome::Success );
				} else {
					$task->markComplete( Outcome::Failed );
				}
			} catch ( \Exception $e ) {
				$task->markComplete( Outcome::Error );
			}

			$tally ++;
		}
		$resultMessage = $resultMessage ?: "processed $tally tasks of $count, skipped $skipped";
		$this->trackable_end( $resultMessage );
	}

}