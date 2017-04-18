<?php
namespace Modular\Interfaces;

use Modular\Fields\QueuedState;

interface QueuedTask extends Task {
	/**
	 * If task can be 'archived' then do so. (could just delete it or some other update).
	 *
	 * @return bool true archived it or false didn't as couldn't for some reason
	 */
	public function archive();

	/**
	 * Return if the task record can be archived (e.g. deleted or hidden from view).
	 *
	 * @return bool
	 */
	public function canArchive();

	/**
	 * Return true if the task is in a 'runnable' state e.g. by a QueuedTaskHandler.
	 * @return bool
	 */
	public function canRun();

	/**
	 * Update the task to a 'Running' state
	 *
	 * @param string $state
	 *
	 * @return mixed
	 */
	public function markRunning( $state = QueuedState::Running );

	/**
	 * Update the task to a 'Ready to run' status
	 * @return mixed
	 */
	public function markReady();

	/**
	 * @param string $outcome one of the Outcome::ABC constants
	 * @param string $state one of the QueuedState::ABC constants
	 *
	 * @return mixed
	 */
	public function markComplete( $outcome, $state = QueuedState::Completed );

	/**
	 * Return if the task is complete (in a 'halt state'), QueuedState field
	 *
	 * @return bool
	 */
	public function taskIsComplete();

	/**
	 * Return if the task is able to run (e.g. 'Queued' or 'Waiting')
	 *
	 * @return bool
	 */
	public function taskIsReady();

	/**
	 * Return if the task is in a 'running'
	 *
	 * @return bool
	 */
	public function taskIsRunning();

}