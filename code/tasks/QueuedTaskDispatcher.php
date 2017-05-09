<?php

namespace Modular\Queue;

use Controller;
use Exception;
use Modular\Models\QueuedTask;
use Modular\Task;
use Modular\Traits\params;

/**
 * QueuedTaskDispatcher creates and enqueues a queued task to a queue, can be called from dev/tasks.
 * The task to create is passed as parameter 'tn', all other parameters will be passed through to the tasks constructor.
 *
 * @package Modular\Queue
 */
class QueuedTaskDispatcher extends Task {
	use params;

	// set in derived class to the the name of the Task to enqueue
	const TaskName = '';

	// can be set in derived class to make a QueueHandler which only
	// processes tasks created with a specific QueueName field
	const QueueName = '';

	// used to pass a queue name on url with this parameter name
	const QueueNameParameter = 'qn';

	// the name (class name) of a task to dispatch
	const TaskNameParameter = 'tn';

	public function getDescription() {
		return "Dispatches a task to the '" . $this->queueName() . "' queue";
	}

	// override self.QueueName, used if no queue name is passed when handler is run
	private static $queue_name = '';

	/**
	 * Executing the QueuedTaskDispatcher enqueues an instance of the task via dispatch method, it does not execute it.
	 * TaskName is taken from the self.TaskName const or from the 'tn' parameter (in that order).
	 *
	 * @param array|\ArrayAccess $params
	 * @param string             $resultMessage
	 *
	 * @return int ID of task created
	 * @throws null
	 */
	public function execute( $params = [], &$resultMessage = '' ) {
		if ( ! $taskName = $this->taskName( $params ) ) {
			throw new Exception( "No task name parameter or configured task on '" . get_class( $this ) . "'" );
		} else {
			if ( ! is_a( $taskName, QueuedTask::class, true ) ) {
				throw new Exception( "$taskName doesn't derive from QueuedTask, can't enqueue" );
			}
		}

		$resultMessage = "Queuing task '$taskName'";
		$this->trackable_start( __METHOD__, $resultMessage );

		$this->debugger()->set_error_exception( $resultMessage );
		try {
			$params = $this->mapParams( $params, $resultMessage );

			$task          = $this->dispatch( $params, $resultMessage );
			$resultMessage = "Dispatched MetaData task '$task->Title'";

		} catch ( Exception $e ) {
			$resultMessage = $e->getMessage();
			$this->debug_error( $e->getMessage() );
		}
		$this->trackable_end( $resultMessage );
	}

	/**
	 * Given initial data for the task create one and enqueue it. The task class to create is taken from QueueName
	 *
	 * @param array|\ArrayAccess $params
	 * @param string             $resultMessage
	 *
	 * @return mixed
	 * @throws \Exception
	 * @throws null
	 * @internal param array $taskData initial data (fields and values) to create the Task model with
	 */
	public function dispatch( $params = [], &$resultMessage = '' ) {
		$taskName = $this->taskName($params);

		$task = \Injector::inst()->create( $taskName );
		if ( ! $task->canExecute() ) {
			throw new Exception( "'$taskName' is not allowed to execute" );
		}
		$result = false;
		try {

			$result = $task->dispatch( $this->mapParams( $params ), $resultMessage );

		} catch ( Exception $e ) {
			$resultMessage = $e->getMessage();
			$this->debug_error( $resultMessage );
		}

		return $result;

	}

	/**
	 * Map params so they can be saved to the Queued Task for use later when the task is executed.
	 * When overridden an exception can be thrown in it and will be caught before the task is enqueued.
	 *
	 * @param array  $params
	 * @param string $resultMessage
	 *
	 * @return array - by default whatever was passed
	 */
	protected function mapParams( $params = [], &$resultMessage = '' ) {
		return $params;
	}

	/**
	 * Return name of the task to enqueue from self.TaskName (should be declared on derived classes) or the 'tn' parameter.
	 * A defined task name on a subclass will always override a task passed as a parameter.
	 * This could also be an Injector service name e.g. 'IndexTask' as that is used in dispatch method.
	 *
	 * @param array $params may contain the task name parameter.
	 *
	 * @return mixed
	 */
	protected function taskName( array $params ) {
		return static::TaskName
			?: $this->param(
				$params,
				self::TaskNameParameter,
				static::TaskName
			);
	}

	/**
	 * Return the processing order (sort by clause) from passed parameters or config.processing_order
	 *
	 * @param array|\ArrayAccess $params
	 *
	 * @return string
	 */
	protected function queueName( $params = [] ) {
		return $this->param(
			$params,
			self::QueueNameParameter,
			( $this->config()->get( 'queue_name' ) ?: static::QueueName )
		);
	}

}