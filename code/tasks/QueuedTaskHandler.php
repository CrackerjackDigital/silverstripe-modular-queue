<?php
namespace Modular\Tasks;

use Modular\Fields\EndDate;
use Modular\Fields\JSONData;
use Modular\Fields\MethodName;
use Modular\Fields\Outcome;
use Modular\Fields\QueuedState;
use Modular\Fields\QueueName;
use Modular\Fields\StartDate;
use Modular\Models\QueuedTask;
use Modular\Task;

/**
 * QueueHandler scans a queue for QueuedTask and executes them. It normally run by a cron job.
 *
 * @package Modular\Tasks
 */
class QueuedTaskHandler extends Task {
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
	 * @param \SS_HTTPRequest $request
	 */
	public function execute( $request ) {
		$queueName = $request->requestVar( self::QueueNameParameter ) ?: static::QueueName;

		$tasks = QueuedTask::get()->filter( [
			QueuedState::Name => QueuedState::Queued,
			Outcome::Name     => Outcome::NotDetermined,
		] )->filter(
			$queueName ? [ QueueName::Name => $queueName ] : []
		)->limit( $this->batchSize( $request )
		)->sort( $this->processingOrder( $request ) );

		/** @var QueuedTask $task */

		foreach ( $tasks as $task ) {
			$task->update( [
				QueuedState::Name => QueuedState::Running,
				StartDate::Name   => StartDate::now(),
			] )->write();

			$methodName = $task->{MethodName::Name};
			$args       = $task->{JSONData::DecodeMethod}();

			// call the method on the queued task
			$task->$methodName( $request, $args );

			if ( $task->{Outcome::Name} == Outcome::NotDetermined ) {
				$task->update( [ QueuedState::Name => QueuedState::Waiting ] )->write();
			} else {
				$task->update( [
					QueuedState::Name => QueuedState::Completed,
					EndDate::Name     => EndDate::now(),
				] )->write();
			}
		}
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