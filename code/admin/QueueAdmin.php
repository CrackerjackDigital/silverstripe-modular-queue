<?php
namespace Modular\Admin;

class QueueAdmin extends ModelAdmin {

	private static $menu_title = 'Queues';

	private static $url_segment = 'modular-queue';

	private static $managed_models = [
		\Modular\Models\QueuedTask::class,
		\Modular\Models\QueuedServiceTask::class
	];
}