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
use Modular\Traits\trackable;

/**
 * QueueHandler scans a queue for QueuedTask and executes them. It normally run by a cron job.
 *
 * @package Modular\Tasks
 */
class QueuedTaskHandler extends QueueHandler {
	use trackable;

	/**
	 * @param array  $params
	 *
	 * @param string $resultMessage
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
		$runDate = isset( $params['rd'] ) ? $params['rd'] : 'NOW()';

		// get either Queued or Waiting tasks
		$tasks = QueuedTask::get()->filter( [
			QueuedState::Name => QueuedState::ready_states(),
			Outcome::Name     => Outcome::ready_states(),
		] )
		->filter( $queueName ? [ QueueName::Name => $queueName ] : [] )
		->where( EventDate::Name . " <= $runDate " )
		->limit( $this->batchSize( $params ) )
		->sort( $this->processingOrder( $params ) );

		/** @var QueuedTask $task */

		$count = $tasks->count();

		$this->debug_info( "running $count tasks" );

		$tally = 0;
		foreach ( $tasks as $task ) {
			$task->update( [
				QueuedState::Name => QueuedState::Running,
				StartDate::Name   => StartDate::now(),
			] )->write();

			$args       = $task->{JSONData::Name};
			$methodName = $task->{MethodName::Name} ?: 'execute';

			$this->debug_info( "calling $methodName on task '" . $task->Title . "'" );
			// call the method on the queued task
			$task->$methodName( $args );

			// if the task Outcome is no longer 'NotDetermined' then mark the Queued status as Completed
			// otherwise mark as 'Waiting' as there may be more to do.
			if ( $task->{Outcome::Name} == Outcome::NotDetermined ) {
				$this->debug_info( "task not completed, putting into waiting state" );

				$task->update( [ QueuedState::Name => QueuedState::Waiting ] )->write();
			} else {
				$this->debug_info( "task completed" );

				$task->update( [
					QueuedState::Name => QueuedState::Completed,
					EndDate::Name     => EndDate::now(),
				] )->write();
			}
			$tally ++;
		}
		$resultMessage = "processed $tally tasks of $count";
		$this->trackable_end( $resultMessage );
	}

}