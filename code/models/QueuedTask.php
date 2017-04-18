<?php

namespace Modular\Models;

use Modular\Exceptions\Exception;
use Modular\Fields\ArchivedState;
use Modular\Fields\Message;
use Modular\Fields\MethodName;
use Modular\Fields\Outcome;
use Modular\Fields\QueuedState;
use Modular\Fields\QueueName;
use Modular\Interfaces\AsyncService as AsyncServiceInterface;
use Modular\Interfaces\QueuedTask as QueuedTaskInterface;
use Modular\Model;
use Modular\Traits\trackable;

/* abstract */

/**
 * QueuedTask is a model which represents a task and it's data which is written
 * to a Queue.
 *
 * @property string QueueName
 * @property string Title
 * @property string QueuedState
 * @property string StartDate
 * @property string EndDate
 */
class QueuedTask extends Model implements AsyncServiceInterface, QueuedTaskInterface {
	use trackable;

	const QueueName = '';

	const DefaultMethodName = 'execute';

	// set_time_limit may be called at the start of execute, use this value if so
	// default is no time limit, if alot of tasks remain we may need to drop this down to another long time
	// that still allows the majority of tasks to complete.
	private static $execution_time_limit = 0;

	private $timeout = null;

	private static $singular_name = 'Queued Task';
	private static $plural_name = 'Queued Tasks';

	// wether we should allow archiving of failed tasks
	private static $allow_archive_failed = true;

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

	// strtotime compatible grace period between EndDate and canArchive returns true
	private static $archive_grace_period = '+1 days';

	// delete when archive is called if we canArchive, otherwise an update is performed
	private static $delete_on_archive = true;

	/**
	 * Returns whether task can be archived given state and outcome.
	 *
	 * @return bool
	 */
	public function canArchive() {
		$failed = $this->{Outcome::field_name()} == Outcome::Failed;

		return $this->taskIsComplete()
		       && ( ! $failed || ( $failed && $this->config()->get( 'allow_archive_failed' ) ) );
	}

	/**
	 * See if the task is in a runnable state by resolving state checks e.g. taskIsRunning, taskIsComplete
	 *
	 * @return bool
	 */
	public function canRun() {
		return $this->taskIsReady()
		       && ! $this->taskIsComplete()
		       && ! $this->taskIsRunning();
	}

	/**
	 * Write the task to a Queue in a 'ready to run' state for execution later.
	 *
	 * @param array|\ArrayAccess $params
	 *
	 * @return \Modular\Models\QueuedTask
	 * @throws \ValidationException
	 */
	public function dispatch( $params = [] ) {
		$this->update( array_merge(
			[   // some defaults, may be overridden by params
			    QueueName::Name => static::QueueName,
			],
			$params
		) );
		$this->markReady();

		return $this;
	}

	/**
	 * Runs the task. Dummy service interface method required as we can't have abstract models.
	 *
	 * @param array|\ArrayAccess $params
	 * @param string             $resultMessage
	 *
	 * @return mixed
	 * @throws \Modular\Exceptions\Exception
	 */
	public function execute( $params = [], &$resultMessage = '' ) {
		throw new Exception( "execute method not provided for Queued Task, it needs one" );
	}

	/**
	 * By default deletes the task.
	 *
	 * @param array|\ArrayAccess $params
	 *
	 * @return bool true if archived, false otherwise (e.g. canArchive returned false)
	 * @throws \LogicException
	 * @throws \ValidationException
	 */
	public function archive( $params = [] ) {
		if ( $canArchive = $this->canArchive() ) {
			if ( $this->config()->get( 'delete_on_archive' ) ) {
				$this->delete();
			} else {
				$this->update( array_merge(
					[
						ArchivedState::Name => ArchivedState::Archived,
					],
					$params
				) )->write();
			}
		}

		return $canArchive;
	}

	/**
	 * Update Outcome to 'Success' and state to 'Completed'
	 *
	 * @param string $message
	 *
	 * @throws \ValidationException
	 */
	protected function success( $message = '' ) {
		if ( $message ) {
			$this->{Message::Name} = $message;
		}
		$this->markComplete( Outcome::Success );
	}

	/**
	 * Update Outcome to 'Failed' and state to 'Completed'
	 *
	 * @param string $message
	 *
	 * @throws \ValidationException
	 */
	protected function fail( $message = '' ) {
		if ( $message ) {
			$this->{Message::Name} = $message;
		}
		$this->markComplete( Outcome::Failed );
	}

	/**
	 * Set the QueueName from self.QueueName
	 */
	public function onBeforeWrite() {
		parent::onBeforeWrite();
		if (!$this->isInDB()) {
			$this->{QueueName::Name}  = $this->{QueuedState::Name} ?: $this->defaultQueueName();
			$this->{MethodName::Name} = $this->{MethodName::Name} ?: $this->defaultMethodName();
		}
	}

	/**
	 * TODO update for configurability
	 *
	 * @return string
	 */
	protected function defaultQueueName() {
		return static::QueueName;
	}

	/**
	 * TODO update for configurability
	 *
	 * @return string
	 */
	protected function defaultMethodName() {
		return static::DefaultMethodName;
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

	/**
	 * Return if the task is complete (in a 'halt state' such as Completed or Cancelled), and has an EndDate
	 *
	 * @return bool
	 */
	public function taskIsComplete() {
		return $this->EndDate && in_array( $this->{QueuedState::field_name()}, QueuedState::halt_states() );
	}

	/**
	 * Return if the task is able to run (e.g. 'Queued' or 'Waiting' and hasn't Ended)
	 *
	 * @return bool
	 */
	public function taskIsReady() {
		return ! $this->EndDate && in_array( $this->{QueuedState::field_name()}, QueuedState::ready_states() );
	}

	/**
	 * Return if the task is in a 'running' state and has a StartDate
	 *
	 * @return bool
	 */
	public function taskIsRunning() {
		return $this->StartDate && in_array( $this->{QueuedState::field_name()}, QueuedState::running_states() );

	}

	/**
	 * Update state and outcome as ready to run and not determined.
	 *
	 * @return $this
	 * @throws \ValidationException
	 */
	public function markReady() {
		$this->update( [
			QueuedState::Name => QueuedState::Queued,
			Outcome::Name     => Outcome::NotDetermined,
		] )->write();

		return $this;
	}

	/**
	 * Update outcome and state to determining and running respectively.
	 *
	 * @param string $outcome
	 * @param string $state
	 *
	 * @return $this
	 * @throws \ValidationException
	 */
	public function markRunning( $outcome = Outcome::Determining, $state = QueuedState::Running ) {
		$this->update( [
			QueuedState::Name => $state,
			Outcome::Name     => $outcome,
		] )->write();

		return $this;
	}

	/**
	 * Update outcome as provided and state to 'Completed'
	 *
	 * @param string $outcome one of the Outcome::ABC constants
	 * @param string $state
	 *
	 * @return $this
	 * @throws \ValidationException
	 */
	public function markComplete( $outcome, $state = QueuedState::Completed ) {
		$this->update( [
			QueuedState::Name => $state,
			Outcome::Name     => $outcome,
		] )->write();

		return $this;
	}
}