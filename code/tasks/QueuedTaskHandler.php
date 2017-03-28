<?php
namespace Modular\Tasks;

use Modular\Fields\EndDate;
use Modular\Fields\JSONData;
use Modular\Fields\MethodName;
use Modular\Fields\Outcome;
use Modular\Fields\QueuedDate;
use Modular\Fields\QueuedState;
use Modular\Fields\QueueName;
use Modular\Fields\StartDate;
use Modular\Task;
use Modular\Traits\trackable;

/**
 * QueueHandler scans a queue for QueuedTask and executes them. It normally run by a cron job.
 *
 * @package Modular\Tasks
 */
class QueuedTaskHandler extends Task {
	use trackable;
	// can be set in derived class to make a QueueHandler which only
	// processes tasks created with a specific QueueName field
	const QueueName = '';

	// used to pass a queue name on url with this parameter name
	const QueueNameParameter       = 'qn';
	// batch size, this is SS ORM style start/limit of tasks fetched in 'ready to run' state
	const BatchSizeParameter       = 'bs';
	// this is a SQL order by parameter which can be passed
	const ProcessingOrderParameter = 'po';

	// only do 1 task at a time
	private static $batch_size = 1;

	// by default we process in order they were added to the queue
	// so operations can be sequenced more easily
	private static $processing_order = 'ID asc';



	/**
	 * @param array $params
	 *
	 * @return mixed|void
	 */
	public function execute( $params = [] ) {
		$this->trackable_start( "QueuedTaskHandler");
		$queueName = $params[self::QueueNameParameter] ?: static::QueueName;

		// get either Queued or Waiting tasks
		$tasks = QueuedTask::get()->filter( [
			QueuedState::Name => QueuedState::ready_states(),
		    Outcome::Name => Outcome::ready_states()
		] )->filter(
			$queueName ? [ QueueName::Name => $queueName ] : []
		)->limit( $this->batchSize( $params )
		)->sort( $this->processingOrder( $params ) );

		/** @var QueuedTask $task */

		$this->debug_info("running " . $tasks->count() . " tasks");

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
				$this->debug_info("task not completed, putting into waiting state");

				$task->update( [ QueuedState::Name => QueuedState::Waiting ] )->write();
			} else {
				$this->debug_info("task completed");

				$task->update( [
					QueuedState::Name => QueuedState::Completed,
					EndDate::Name     => EndDate::now(),
				] )->write();
			}
		}
		$this->trackable_end();
	}

	/**
	 * Return the processing order (sort by clause) from passed parameters or config.processing_order
	 *
	 * @param $request
	 *
	 * @return int
	 */
	protected function processingOrder( $request ) {
		return is_int( $request[ self::ProcessingOrderParameter ] )
			? (int) [ self::ProcessingOrderParameter ]
			: (int) $this->config()->get( 'processing_order' );
	}

	/**
	 * Return the batch size (limit clause) from passed parameters or config.batch_size
	 *
	 * @param $request
	 *
	 * @return int
	 */
	protected function batchSize( $request ) {
		return is_int( $request[ self::BatchSizeParameter ] )
			? (int) [ self::BatchSizeParameter ]
			: (int) $this->config()->get( 'batch_size' );

	}
}