<?php
namespace Modular\Models;

use Modular\Fields\JSON;
use Modular\Fields\JSONData;
use Modular\Fields\MethodName;
use Modular\Fields\QueueName;
use Modular\Interfaces\Service;
use Modular\Model;

/* abstract */

class QueuedTask extends Model implements Service {
	const QueueName = '';

	// set_time_limit may be called at the start of execute, use this value if so
	// default is no time limit, if alot of tasks remain we may need to drop this down to another long time
	// that still allows the majority of tasks to complete.
	private static $execution_time_limit = 0;

	private $timeout = null;

	/**
	 * Alternate dispatch to call a method on this task the 'service' way. In this case will update the QueuedTask object and write it to the queue.
	 * When the task is dequeued then the method saved will be called directly via the queue handler.
	 *
	 * @param $methodName
	 *
	 * @return mixed
	 */
	public function dispatch( $methodName ) {
		$this->{MethodName::Name} = $methodName;
		$this->setJSONData(func_get_args());
		$this->write();
	}

	/**
	 * Set the timeout if passed, return the set timeout or config.execution_time_limit.
	 *
	 * @param null|int $timeout
	 *
	 * @return int
	 */
	public function timeout( $timeout = null ) {
		if ( func_num_args() ) {
			$this->timeout = $timeout;
		}

		return (int) ( is_null( $this->timeout )
			? $this->config()->get( 'execution_time_limit' )
			: $this->timeout );
	}

	public function onBeforeWrite() {
		parent::onBeforeWrite();
		if ( $queueName = static::QueueName ) {
			$this->{QueueName::Name} = $queueName;
		};
	}
}