<?php

namespace Modular\Models;

use Modular\Exceptions\Exception;
use Modular\Fields\JSONData;
use Modular\Fields\MethodName;
use Modular\Fields\ServiceName;
use Modular\Interfaces\AsyncService;
use Modular\Service;

/**
 * Like a QueuedTask but has a Service on which the method is called instead of calling it on itself. The service method is called via the 'execute'
 * dispatch interface.
 *
 * @package Modular\Tasks
 */
/* abstract */

class QueuedServiceTask extends QueuedTask implements AsyncService {
	// if no ServiceName field or service_name config this will be used
	const DefaultServiceName = '';

	const DefaultMethodName = 'execute';

	private static $singular_name = 'Queued Service Task';
	private static $plural_name = 'Queued Service Tasks';

	// is no ServiceName field this will be used in preference to self.DefaultServiceName constant
	private static $service_name = '';

	private static $validation = [
		// NB method validation not implemented yet as of 2017-04-14
		// TODO implement method name based validation on model/field
		\Modular\Fields\ServiceName::Name => [ 'method' => 'service', 'min' => 5 ],
		\Modular\Fields\MethodName::Name  => [ 'method' => 'method', 'min' => 1 ],
	];

	/**
	 * Call the specified method on an instance of the specified service, passing through JSONData saved on this model, params and resultMessage
	 *
	 * @param array|\ArrayAccess $params
	 * @param string $resultMessage
	 *
	 * @return mixed result of method call on service
	 * @throws \Modular\Exceptions\Exception
	 */
	public function execute( $params = [], &$resultMessage = '' ) {
		/** @var Service $service */
		$service = $this->service();
		/** @var string $method */
		$method = $this->method();

		$params = array_merge(
			JSONData::decode( $this->{JSONData::Name}) ?: [],
			$params
		);

		return $service->$method( $params, $resultMessage );
	}

	/**
	 * Return either the ServiceName field on this model, the configured config.service_name or self.DefaultServiceName constant in that order
	 * of preference. May return empty/null if none are set.
	 *
	 * @return string
	 */
	public function definedServiceName() {
		return ( $this->{ServiceName::Name} ?: $this->config()->get( 'service_name' ) ) ?: static::DefaultServiceName;
	}

	/**
	 * Set the service name to either config.service_name or self.DefaultServiceName
	 */
	public function populateDefaults() {
		parent::populateDefaults();
		if ( ! $this->{ServiceName::Name} ) {
			$this->{ServiceName::Name} = $this->definedServiceName();
		}
		if ( ! $this->{MethodName::Name} ) {
			$this->{MethodName::Name} = static::DefaultMethodName;
		}
	}

	/**
	 * Try and return an instance of the service
	 *
	 * @throws \Modular\Exceptions\Exception
	 */
	public function service() {
		if ( $serviceName = $this->definedServiceName() ) {
			try {
				return \Injector::inst()->get( $serviceName );
			} catch ( \Exception $e ) {
				throw new Exception( "No such service '$serviceName'", 0, $e );
			}
		} else {
			throw new Exception( "No service defined" );
		}
	}

	/**
	 * Return the method name on the service if the service is valid and the method exists on it (via hasMethod).
	 *
	 * @return string
	 * @throws \Modular\Exceptions\Exception
	 */
	public function method() {
		if ( $method = $this->{MethodName::Name} ) {
			if ( $service = $this->service() ) {
				if ( ! $service->hasMethod( $method ) ) {
					throw new Exception( "Service doesn't have method '$method'" );
				}
			} else {
				throw new Exception( "No such service" );
			}
		} else {
			throw new Exception( "No method defined" );
		}

		return $method;
	}

}