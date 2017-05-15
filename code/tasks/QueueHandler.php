<?php

namespace Modular\Tasks;

use Modular\Fields\Outcome;
use Modular\Fields\QueuedState;
use Modular\Fields\QueuePriority;
use Modular\Task;
use Modular\Traits\params;

abstract class QueueHandler extends Task {
	use params;

	// can be set in derived class to make a QueueHandler which only
	// processes tasks created with a specific QueueName field
	const QueueName = '';

	// provide if a 'grace period', e.g. -2 days is required from now as comparison against e.g. an EndDate
	const GracePeriodParameter = 'gp';

	// when filtering use this field name as the date field to compare against,
	// e.g. 'EndDate' or 'EventDate'
	const GracePeriodField = '';

	// used to pass a queue name on url with this parameter name
	const QueueNameParameter = 'qn';

	// batch size, this is SS ORM style start/limit of tasks fetched in 'ready to run' state
	const BatchSizeParameter = 'bs';

	// this is a SQL order by parameter which can be passed
	const ProcessingOrderParameter = 'po';

	// filter queue by items with a QueuedState provided by this parameter
	const QueuedStatesParameter = 'qs';

	// filter queue by items with an Outcome provided by this parameter
	const OutcomesParameter = 'oc';

	// process this specific task ID only, all other filters will be ignored so caution
	const TaskIDParameter = 'tid';

	// use this id as the ModelRef ID if the task has a ModelRef field.
	const ModelRefIDParameter = 'mid';

	private static $batch_size = 10;

	// only jobs older than this will be processed (requires also the GracePeriodField be set)
	private static $grace_period = '-2 days';

	// by default we process in order they were added to the queue (fifo)
	// so operations can be sequenced more easily
	private static $processing_order = [ 'ID' => 'ASC' ];

	// override self.QueueName, used if no queue name is passed when handler is run
	private static $queue_name = '';

	// where multiple values for a parameter can be given seperate them with this
	// (e.g. multiple QueuedStates)
	private static $parameter_separator = ',';

	// provide this parameter as a wildcard to mean all options, e.g. all Queued States
	private static $wildcard_all = '*';

	/**
	 * Return the queue name(s) to process.
	 *
	 * @param array|\ArrayAccess $params
	 *
	 * @return array
	 */
	protected function queueName( $params = [] ) {
		return $this->param(
			$params,
			self::QueueNameParameter,
			$this->config()->get( 'queue_name' ) ?: static::QueueName
		);
	}

	/**
	 * Return the processing order (sort by clauses) from passed parameters or config.processing_order.
	 * Always merges in QueuePriority sort ascending first so lower priorities come first.
	 *
	 * @param array|\ArrayAccess $params
	 *
	 * @return array
	 */
	protected function processingOrder( $params ) {
		$secondary = $this->paramArray(
			$params,
			self::ProcessingOrderParameter,
			$this->config()->get( 'processing_order' )
		);
		return array_merge(
			[
				QueuePriority::Name => 'ASC'
			],
			$secondary
		);
	}

	/**
	 * @param array|\ArrayAccess $params
	 *
	 * @return array
	 */
	protected function queuedStates( $params ) {
		return $this->paramArray(
			$params,
			self::QueuedStatesParameter,
			QueuedState::ready_states()
		);
	}

	/**
	 * @param array|\ArrayAccess $params
	 *
	 * @return array
	 */
	protected function outcomes( $params ) {
		// filter by supplied or default Outcome values
		return $this->paramArray(
			$params,
			self::OutcomesParameter,
			Outcome::ready_states()
		);

	}

	/**
	 * Return the batch size (limit clause) from passed parameters or config.batch_size
	 *
	 * @param array|\ArrayAccess $params
	 *
	 * @return int
	 */
	protected function batchSize( $params ) {
		return (int)$this->param(
			$params,
			self::BatchSizeParameter,
			$this->config()->get( 'batch_size' )
		);
	}

	/**
	 * Return a date which offset by a grace period from now.
	 *
	 * @param array|\ArrayAccess $params
	 *
	 * @return string
	 */
	protected function graceDate( $params ) {
		$graceDate = '';

		$gracePeriod = isset( $params[ self::GracePeriodParameter ] )
			? $params[ self::GracePeriodParameter ]
			: $this->config()->get( 'grace_period' );

		if ( $gracePeriod && ! $this->isAll( $gracePeriod ) ) {
			if ( $graceTime = strtotime( $gracePeriod ) ) {
				$graceDate = date( 'Y-m-d h:i:s', $graceTime );
			}
		}

		return $graceDate;
	}

	/**
	 * Test passed parameter to see if it is the 'all' wildcard. If an array then tests against first element.
	 *
	 * @param string|array $parameterValue
	 *
	 * @return bool
	 */
	protected function isAll( $parameterValue ) {
		return is_array( $parameterValue )
			? ( reset( $parameterValue ) == $this->config()->get( 'wildcard_all' ) )
			: ( $parameterValue == $this->config()->get( 'wildcard_all' ) );
	}

}