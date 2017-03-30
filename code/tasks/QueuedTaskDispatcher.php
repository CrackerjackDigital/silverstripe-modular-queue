<?php

namespace Modular\Queue;

use Modular\Fields\QueueName;
use Modular\Helpers\Reflection;
use Modular\Task;

/**
 * QueuedTaskDispatcher creates and enqueues a queued task to a queue
 *
 * @package Modular\Queue
 */
abstract class QueuedTaskDispatcher extends Task {
	// name of the queue and for brevity also the Inject service name e.g. 'FileInfoTask' set in Injector config to 'OpenSemanticSearch\FileInfoTask'
	// if not set then the terminal part of class name will be used with namespace stripped, ie OpenSemanticSearch\FileInfoTask will become 'FileInfoTask'
	const QueueName = '';

	/**
	 * Given initial data for the task create one and enqueue it. The task class to create is taken from QueueName
	 *
	 * @param array $taskData initial data (fields and values) to create the Task model with
	 *
	 * @return mixed
	 */
	public function dispatch( $taskData ) {
		$queueName = static::QueueName
			?: Reflection::derive_class_name( get_called_class(), true );

		$taskData[ QueueName::Name ] = $queueName;

		return \Injector::inst()->create( $queueName )->dispatch( $taskData );

	}
}