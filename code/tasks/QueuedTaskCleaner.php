<?php

namespace Modular\Tasks;

use Modular\Fields\EndDate;
use Modular\Models\QueuedTask;

class QueuedTaskCleaner extends QueuedTaskHandler {
	// only clean tasks over 5 days old
	const GracePeriodField = EndDate::class;
	// clean tasks this time ago (strtotime compatible) from now
	// override with GracePeriodParameter on query string
	private static $grace_period = '-5 days';

	protected $description = "Scans a Queue or all Queues for tasks in a 'completed' state and archives them. Queue Name can be specified with 'qn' query string parameter, batch size with 'bs'";

	public function execute( $params = [], &$resultMessage = '' ) {
		$tasks = $this->tasks($params);

		$this->debug_info( "cleaning " . $tasks->count() . " tasks" );

		$sql = $tasks->sql();

		/** @var QueuedTask $task */
		foreach ( $tasks as $task ) {
			$this->debug_info( "removing task '$task->Title' with ID '$task->ID'" );

			// force an archive of task
			$task->archive([], true);
		}
	}
}