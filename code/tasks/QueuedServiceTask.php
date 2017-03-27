<?php
namespace Modular\Tasks;

use Modular\Fields\JSON;
use Modular\Fields\ServiceName;
use Modular\Models\QueuedTask;
use Modular\Service;

/**
 * Like a QueuedTask but has a Service on which the method is called instead of calling it on itself. The service method is called via the 'execute'
 * dispatch interface.
 *
 * @package Modular\Tasks
 */
class QueuedServiceTask extends QueuedTask implements \Modular\Interfaces\Service {
	/**
	 * Pass the called method through to the service named in ServiceName field.
	 *
	 * @param string|null $methodName
	 *
	 * @return mixed
	 *
	 */
	public function dispatch( $methodName = null ) {
		$serviceName = $this->{ServiceName::Name};
		/** @var Service $service */
		$service = $serviceName::get( );

		return $service->dispatch( $methodName, JSON::typed_value($this->{JSON::Name}) );
	}
}