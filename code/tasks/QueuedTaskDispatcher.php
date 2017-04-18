<?php

namespace Modular\Queue;

use Modular\Interfaces\Task as TaskInterface;
use Modular\Task;

/**
 * QueuedTaskDispatcher creates and enqueues a queued task to a queue, can be called from dev/tasks
 *
 * @package Modular\Queue
 */
abstract class QueuedTaskDispatcher extends Task {
	// set in dervided class to the the name of the Task to enqueue
	const TaskName = '';

	// can be set in derived class to make a QueueHandler which only
	// processes tasks created with a specific QueueName field
	const QueueName = '';

	// used to pass a queue name on url with this parameter name
	const QueueNameParameter = 'qn';

	public function getDescription() {
		return "Dispatches a task to the '" . $this->queueName() . "' queue";
	}

	// override self.QueueName, used if no queue name is passed when handler is run
	private static $queue_name = '';

	/**
	 * Return the processing order (sort by clause) from passed parameters or config.processing_order
	 *
	 * @param array|\ArrayAccess $request
	 *
	 * @return string
	 */
	protected function queueName( $request = []) {
		return ( isset( $request[ self::QueueNameParameter ] ) )
			? $request[ self::QueueNameParameter ]
			: ( $this->config()->get( 'queue_name' ) ?: static::QueueName );
	}

	/**
	 * Given initial data for the task create one and enqueue it. The task class to create is taken from QueueName
	 *
	 * @param array|\ArrayAccess $params
	 * @param string $resultMessage
	 *
	 * @return mixed
	 * @internal param array $taskData initial data (fields and values) to create the Task model with
	 */
	public function dispatch( $params = [], &$resultMessage = '' ) {
		return \Injector::inst()->create( static::TaskName )->dispatch( $params, $resultMessage );
	}
}