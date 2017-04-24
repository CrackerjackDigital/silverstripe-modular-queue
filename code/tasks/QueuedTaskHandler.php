<?php

namespace Modular\Tasks;

use Modular\Fields\ModelRef;
use Modular\Fields\Outcome;
use Modular\Fields\QueuedState;
use Modular\Fields\QueueName;
use Modular\Models\QueuedTask;

/**
 * QueueHandler scans a queue for QueuedTask and executes them. It normally run by a cron job.
 * I will only try and run tasks whose EventDate is less than or equal to the current date.
 *
 * @package Modular\Tasks
 */
abstract class QueuedTaskHandler extends QueueHandler {

	protected $description = "Scans a Queue or all Queues for tasks to run. Queue Name can be specified with 'qn' query string parameter, batch size with 'bs'";

	/**
	 * Returns a list of QueuedTask models filtered by parameters passed.
	 *
	 * @param array  $params
	 *
	 * @param string $graceDateField use this field, e.g. 'EndDate' or 'EventDate'
	 *                               to filter by grace date from parameters
	 *
	 * @return \DataList
	 * @throws \InvalidArgumentException
	 */
	public function tasks( $params = [] ) {
		// get tasks which have a queued halt state and a halt outcome
		$tasks = QueuedTask::get()
		                   ->sort( $this->processingOrder( $params ) );

		if ( isset( $params[ static::TaskIDParameter ] ) ) {
			if ( is_int( $params[ static::TaskIDParameter ] ) ) {
				$tasks = $tasks->byID(
					$params[ static::TaskIDParameter ]
				);
			}

		} elseif ( isset( $params[ static::ModelRefIDParameter ] ) ) {
			if ( is_int( $params[ static::ModelRefIDParameter ] ) ) {
				$tasks = $tasks->filter( [
					ModelRef::field_name() => $params[ static::ModelRefIDParameter ],
				] );
			}
		} else {
			if ( isset( $params[ static::ModelRefIDParameter ] ) && is_int( $params[ static::ModelRefIDParameter ] ) ) {
				$tasks = $tasks->filter( [
					ModelRef::Name => $params[ static::TaskIDParameter ],
				] );
			}

			// filter by supplied or default QueuedState values
			$queuedStates = $this->queuedStates( $params );
			if ( $queuedStates && ! $this->isAll( $queuedStates ) ) {
				$tasks = $tasks->filter( [
					QueuedState::Name => $queuedStates,
				] );
			}

			$outcomes = $this->outcomes( $params );
			if ( $outcomes && ! $this->isAll( $outcomes ) ) {
				$tasks = $tasks->filter( [
					Outcome::Name => $outcomes,
				] );
			}

			if ( $queueName = $this->queueName( $params ) ) {
				$tasks = $tasks->filter( [
					QueueName::Name => $queueName,
				] );
			}

			$gracePeriodField = static::GracePeriodField;
			if ( $gracePeriodField && ( $graceDate = $this->graceDate( $params ) ) ) {
				$tasks = $tasks->filter( [
					$gracePeriodField::field_name( ':LessThan' ) => $graceDate,
				] );
			}
			$tasks = $tasks->limit( $this->batchSize( $params ) );
		}

		return $tasks;
	}

}