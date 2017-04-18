<?php
namespace Modular\Tasks;

use Modular\Fields\Outcome;
use Modular\Fields\QueuedState;
use Modular\Fields\QueueName;
use Modular\Models\QueuedTask;

class QueuedTaskCleaner extends QueueHandler {
	protected $description = "Scans a Queue or all Queues for tasks in a 'completed' state and archives them. Queue Name can be specified with 'qn' query string parameter, batch size with 'bs'";

	public function execute( $params = [], &$resultMessage = '' ) {
		$queueName = isset( $params[ self::QueueNameParameter ] ) ? $params[ self::QueueNameParameter ] : static::QueueName;
		// get tasks which have a queued halt state and a halt outcome
		$tasks = QueuedTask::get()->filter( [
			QueuedState::Name => QueuedState::halt_states(),
			Outcome::Name     => Outcome::halt_states(),
		] )->filter(
			$queueName ? [ QueueName::Name => $queueName ] : []
		)->limit( $this->batchSize( $params )
		)->sort( $this->processingOrder( $params ) );

		/** @var QueuedTask $task */

		$this->debug_info( "cleaning " . $tasks->count() . " tasks" );
		foreach ($tasks as $task) {
			$this->debug_info("removing task '$task->Title' with ID '$task->ID'");

			$task->archive();
		}
	}
}