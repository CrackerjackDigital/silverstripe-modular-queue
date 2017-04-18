<?php

namespace Modular\Tasks;

use Modular\Fields\EndDate;
use Modular\Fields\EventDate;
use Modular\Fields\JSONData;
use Modular\Fields\MethodName;
use Modular\Fields\Outcome;
use Modular\Fields\QueuedDate;
use Modular\Fields\QueuedState;
use Modular\Fields\QueueName;
use Modular\Fields\StartDate;
use Modular\Models\QueuedTask;
use Modular\Task;
use Modular\Traits\trackable;

/**
 * QueueHandler scans a queue for QueuedTask and executes them. It normally run by a cron job.
 * I will only try and run tasks whose EventDate is less than or equal to the current date.
 *
 * @package Modular\Tasks
 */
class QueuedTaskHandler extends QueueHandler {
	use trackable;

	protected $description = "Scans a Queue or all Queues for tasks to run. Queue Name can be specified with 'qn' query string parameter, batch size with 'bs'";

	/**
	 * @param array|\ArrayAccess $params
	 *
	 * @param string             $resultMessage
	 *
	 * @return mixed|void
	 * @throws \InvalidArgumentException
	 * @throws \ValidationException
	 */
	public function execute( $params = [], &$resultMessage = '' ) {
		$queueName = isset( $params[ self::QueueNameParameter ] ) ? $params[ self::QueueNameParameter ] : static::QueueName;
		if ( $queueName ) {
			$this->trackable_start( "QueuedTaskHandler", trim( "Checking queue $queueName" ) );
		} else {
			$this->trackable_start( "QueuedTaskHandler", "Checking all queues" );
		}
		if ( isset( $params['rd'] ) && ( $time = strtotime( $params['rd'] ) ) ) {
			$runDate = date( 'Y-m-d h:i:s', $time );
		} else {
			$runDate = date( 'Y-m-d h:i:s' );
		}

		// get either Queued or Waiting tasks
		$tasks = QueuedTask::get()
		                   ->filter( EventDate::Name . ':LessThanOrEqual', $runDate )
		                   ->filter( QueuedState::field_name(), QueuedState::ready_states() )
		                   ->limit( $this->batchSize( $params ) )
		                   ->sort( $this->processingOrder( $params ) );

		if ( $queueName ) {
			$tasks = $tasks->filter( [
				QueueName::Name => $queueName,
			] );
		}

		$sql = $tasks->sql();

		/** @var QueuedTask $task */

		$count = $tasks->count();

		$this->debug_info( "running $count tasks" );

		$tally = 0;
		foreach ( $tasks as $task ) {
			if ( ! $task->canRun() ) {
				// skip it
				continue;
			}
			$task->markRunning();

			$args       = $task->{JSONData::Name};
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
		$resultMessage = $resultMessage ?: "processed $tally tasks of $count";
		$this->trackable_end( $resultMessage );
	}

}