<?php
namespace Modular\Tasks;

use Modular\Fields\Message;
use Modular\Fields\Outcome;
use Modular\Fields\QueuedState;
use Modular\Fields\QueueName;
use Modular\Interfaces\AsyncService;
use Modular\Interfaces\Task;
use Modular\Model;
use Modular\Traits\trackable;

/* abstract */

class QueuedTask extends Model implements AsyncService, Task {
	use trackable;

	const QueueName = 'No Queue';

	// set_time_limit may be called at the start of execute, use this value if so
	// default is no time limit, if alot of tasks remain we may need to drop this down to another long time
	// that still allows the majority of tasks to complete.
	private static $execution_time_limit = 0;

	private $timeout = null;

	private static $singular_name = 'Queued Task';
	private static $plural_name = 'Queued Tasks';

	private static $summary_fields = [
		'QueueName'   => 'Queue',
		'Title'       => 'Title',
		'QueuedState' => 'Status',
		'QueuedDate'  => 'Queued Date',
		'Outcome'     => 'Outcome',
		'EventDate'   => 'To Run Date',
		'StartDate'   => 'Started',
		'EndDate'     => 'Ended',
	];

	/**
	 * Write a new task instance based on params to the Queue for execution later
	 *
	 * @param null $params
	 *
	 * @return \Modular\Tasks\QueuedTask
	 * @throws \ValidationException
	 */
	public static function dispatch( $params = null ) {
		$task = new static( array_merge(
			[
				QueueName::Name   => static::QueueName,
				QueuedState::Name => QueuedState::Queued,
			],
			$params
		) );
		$task->write();
		return $task;
	}

	/**
	 * Dummy service interface method required as we can't have abstract models.
	 *
	 * @param        $params
	 *
	 * @param string $resultMessage
	 *
	 * @return mixed
	 */
	public function execute( $params = null, &$resultMessage = '' ) {
		return false;
	}

	/**
	 * By default deletes the task.
	 * @throws \LogicException
	 */
	public function archive() {
		$this->delete();
	}

	/**
	 * Update Outcome to 'Success'
	 */
	protected function success($message) {
		$this()->update( [
			Outcome::Name => Outcome::Success,
		    Message::Name => $message
		] )->write();
	}

	/**
	 * Update Outcome to 'Failed'
	 */
	protected function fail($message) {
		$this()->update( [
			Outcome::Name => Outcome::Failed,
			Message::Name => $message
		] )->write();
	}

	/**
	 * Set QueueName if empty.
	 */
	public function onBeforeWrite() {
		if ( ! $this->{QueueName::Name} ) {
			$this->{QueueName::Name} = static::QueueName;
		}
		parent::onBeforeWrite();
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