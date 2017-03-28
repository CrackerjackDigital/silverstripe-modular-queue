<?php
namespace Modular\Tasks;

use Modular\Fields\JSONData;
use Modular\Fields\ServiceName;
use Modular\Interfaces\AsyncService;
use Modular\Service;

/**
 * Like a QueuedTask but has a Service on which the method is called instead of calling it on itself. The service method is called via the 'execute'
 * dispatch interface.
 *
 * @package Modular\Tasks
 */
class QueuedServiceTask extends QueuedTask implements AsyncService {
	private static $singular_name = 'Queued Service Task';
	private static $plural_name = 'Queued Service Tasks';

	/**
	 * Pass the called method through to the service named in ServiceName field.
	 *
	 * @param string|null $params
	 *
	 * @return mixed
	 */
	public function execute( $params = null ) {
		$serviceName = $this->{ServiceName::Name};
		/** @var Service $service */
		$service = $serviceName::get();

		return $service->execute( $this->{JSONData::Name} );
	}
}