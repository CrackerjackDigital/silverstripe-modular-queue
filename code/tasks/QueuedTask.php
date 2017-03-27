<?php
namespace Modular\Models;

use Modular\Fields\JSON;
use Modular\Fields\JSONData;
use Modular\Fields\MethodName;
use Modular\Fields\QueuedDate;
use Modular\Fields\QueueName;
use Modular\Interfaces\AsyncService;
use Modular\Interfaces\Service;
use Modular\Model;

/* abstract */

class QueuedTask extends Model implements AsyncService {
	const QueueName = '';

	// set_time_limit may be called at the start of execute, use this value if so
	// default is no time limit, if alot of tasks remain we may need to drop this down to another long time
	// that still allows the majority of tasks to complete.
	private static $execution_time_limit = 0;

	private $timeout = null;

	/**
	 * Write a new task instance based on params to the Queue for execution later
	 *
	 * @param null $params
	 *
	 * @return mixed
	 */
	public function dispatch( $params = null ) {
		$task = new static( $params );
		return $task->write();
	}

	/**
	 * Alternate dispatch to call a method on this task the 'service' way. In this case will update the QueuedTask object and write it to the queue.
	 * When the task is dequeued then the method saved will be called directly via the queue handler.
	 *
	 * @param $params
	 *
	 * @return mixed
	 */
	public function execute( $params = null ) {
		$this->{MethodName::Name} = $params;
		$this->setJSONData(func_get_args());
		$this->write();
	}

	public function onBeforeWrite() {
		parent::onBeforeWrite();
		if ( $queueName = static::QueueName ) {
			$this->{QueueName::Name} = $queueName;
		};
		if (!$this->isInDB()) {
			$this->{QueuedDate::Name} = QueuedDate::now();
			$this->{QueuedBy::field_name('ID')} = Member::currentUserID();
		}

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

}