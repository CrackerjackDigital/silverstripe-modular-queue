<?php

namespace Modular\Models;

use Modular\Exceptions\Exception;
use Modular\Fields\ArchivedState;
use Modular\Fields\EndDate;
use Modular\Fields\Message;
use Modular\Fields\MethodName;
use Modular\Fields\Outcome;
use Modular\Fields\QueuedDate;
use Modular\Fields\QueuedState;
use Modular\Fields\QueueName;
use Modular\Fields\StartDate;
use Modular\Interfaces\AsyncService as AsyncServiceInterface;
use Modular\Interfaces\QueuedTask as QueuedTaskInterface;
use Modular\Model;
use Modular\Traits\timeout;
use Modular\Traits\trackable;

/**
 * QueuedTask is a model which represents a task and it's data which is written
 * to a Queue.
 *
 * @property string Title
 * @property string QueueName
 * @property string QueuedState
 * @property string QueuedDate
 * @property string Outcome
 * @property string EventDate
 * @property string StartDate
 * @property string EndDate
 * @property string ArchivedDate
 * @property int    ModelRef
 */
class QueuedTask extends Model implements AsyncServiceInterface, QueuedTaskInterface {
	use trackable;
	use timeout;

	const QueueName = '';

	const DefaultMethodName = 'execute';

	// set timeout in seconds, by default we set to 0 which for set_timeout
	private static $timeout = 0;

	private static $singular_name = 'Queued Task';
	private static $plural_name = 'Queued Tasks';

	// wether we should allow archiving of failed tasks
	private static $allow_archive_failed = true;

	private static $summary_fields = [
		'QueueName',
		'Title',
		'QueuedState',
		'QueuedDate',
		'Outcome',
		'EventDate',
		'StartDate',
		'EndDate',
	];

	private static $default_sort = '"QueuedDate" DESC';

	// strtotime compatible grace period between EndDate and canArchive returns true
	private static $archive_grace_period = '+1 days';

	// delete when archive is called if we canArchive, otherwise an update is performed
	private static $delete_on_archive = true;

	// add fields here to check to see if a task should be written as a 'Duplicate' instead of 'Queued' when it is
	// created if a task already exists with the field values in a non-completed state. All fields must match to
	// be decided a duplicate.
	private static $unique_fields = [
		\Modular\Fields\Title::Name      => true,
		\Modular\Fields\MethodName::Name => true,
	];

	public function uniqueFields() {
		if ($fields = array_keys(array_filter(static::config()->get('unique_fields') ?: []))) {
			foreach ($fields as $index => $fieldName) {
				if ($this->hasOneComponent( $fieldName)) {
					$fields[$index] = $fieldName . 'ID';
				} else {
					$fields[$index] = $fieldName;
				}
			}
		}
		return $fields;
	}

	/**
	 * Checks constraints to see if this task can be dispatched, e.g. that there are no other incomplete tasks
	 * which have the same config.unique_fields values.
	 */
	public function isDuplicate() {
		$duplicate = false;

		if ( $uniqueFields = $this->uniqueFields()) {
			// intersect on key so flip the array, value will come from map of model fields
			$fields = array_intersect_key(
				$this->toMap(),
				array_flip($uniqueFields)
			);
			if ( $fields ) {
				$haltStates = QueuedState::halt_states();

				$existing = static::get()
				                  ->filter( $fields )
				                  ->exclude( [
					                  QueuedState::field_name() => $haltStates,
				                  ] );

				$duplicate = $existing->count() > 0;
			}
		}

		return $duplicate;
	}

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
	 * Set the QueueName from self.QueueName
	 */
	public function onBeforeWrite() {
		parent::onBeforeWrite();
		if ( ! $this->isInDB() ) {
			$this->update( [
				QueueName::Name  => $this->{QueueName::Name} ?: $this->defaultQueueName(),
				MethodName::Name => $this->{MethodName::Name} ?: $this->defaultMethodName(),
				QueuedDate::Name => date( 'Y-m-d h:i:s' ),
			] );

			if ( $this->isDuplicate() ) {
				$this->update( [
					QueuedState::field_name() => QueuedState::Duplicate,
				] );
			}
		}
	}

	/**
	 * Write this task to a Queue in a 'ready to run' state for execution later.
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
	 * @param bool               $force skip canArchive check, e.g. if forcing from the QueuedTaskCleaner task
	 *
	 * @return bool true if archived, false otherwise (e.g. canArchive returned false)
	 * @throws \LogicException
	 * @throws \ValidationException
	 */
	public function archive( $params = [], $force = false ) {
		$canArchive = ( $force || $this->canArchive() );

		if ( $canArchive ) {
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
	public function markSuccessful( $message = '' ) {
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
	public function markFailed( $message = '' ) {
		if ( $message ) {
			$this->{Message::Name} = $message;
		}
		$this->markComplete( Outcome::Failed );
	}

	/**
	 * Return if the task is complete (in a 'halt state' such as Completed or Cancelled), and has an EndDate
	 *
	 * @return bool
	 */
	public function taskIsComplete() {
		return in_array( $this->{QueuedState::field_name()}, QueuedState::halt_states() );
	}

	/**
	 * Return if the task is able to run (e.g. 'Queued' or 'Waiting' and hasn't Ended)
	 *
	 * @return bool
	 */
	public function taskIsReady() {
		return in_array( $this->{QueuedState::field_name()}, QueuedState::ready_states() );
	}

	/**
	 * Return if the task is in a 'running' state and has a StartDate
	 *
	 * @return bool
	 */
	public function taskIsRunning() {
		return in_array( $this->{QueuedState::field_name()}, QueuedState::running_states() );

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
			QueuedDate::Name  => date( 'Y-m-d h:i:s' ),
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
			StartDate::Name   => date( 'Y-m-d h:i:s' ),
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
			EndDate::Name     => date( 'Y-m-d h:i:s' ),
		] )->write();

		return $this;
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

}