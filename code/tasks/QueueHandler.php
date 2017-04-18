<?php

namespace Modular\Tasks;

use Modular\Task;

abstract class QueueHandler extends Task {
	// can be set in derived class to make a QueueHandler which only
	// processes tasks created with a specific QueueName field
	const QueueName = '';

	// used to pass a queue name on url with this parameter name
	const QueueNameParameter = 'qn';
	// batch size, this is SS ORM style start/limit of tasks fetched in 'ready to run' state
	const BatchSizeParameter = 'bs';
	// this is a SQL order by parameter which can be passed
	const ProcessingOrderParameter = 'po';

	// only do 1 task at a time
	private static $batch_size = 1;

	// by default we process in order they were added to the queue
	// so operations can be sequenced more easily
	private static $processing_order = 'ID asc';

	// override self.QueueName, used if no queue name is passed when handler is run
	private static $queue_name = '';

	/**
	 * Return the processing order (sort by clause) from passed parameters or config.processing_order
	 *
	 * @param array|\ArrayAccess $request
	 *
	 * @return string
	 */
	protected function queueName( $request = [] ) {
		return ( isset( $request[ self::QueueNameParameter ] ) )
			? $request[ self::QueueNameParameter ]
			: ( $this->config()->get( 'queue_name' ) ?: static::QueueName );
	}

	/**
	 * Return the processing order (sort by clause) from passed parameters or config.processing_order
	 *
	 * @param array|\ArrayAccess $request
	 *
	 * @return string
	 */
	protected function processingOrder( $request ) {
		return ( isset( $request[ self::ProcessingOrderParameter ] ) && is_numeric( $request[ self::ProcessingOrderParameter ] ) )
			? $request[ self::ProcessingOrderParameter ]
			: $this->config()->get( 'processing_order' );
	}

	/**
	 * Return the batch size (limit clause) from passed parameters or config.batch_size
	 *
	 * @param array|\ArrayAccess $request
	 *
	 * @return int
	 */
	protected function batchSize( $request ) {
		return ( isset( $request[ self::BatchSizeParameter ] ) && is_numeric( $request[ self::BatchSizeParameter ] ) )
			? (int) $request[ self::BatchSizeParameter ]
			: (int) $this->config()->get( 'batch_size' );

	}
}