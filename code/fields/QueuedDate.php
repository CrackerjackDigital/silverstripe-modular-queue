<?php
namespace Modular\Fields;

class QueuedDate extends DateTimeField {
	const Name = 'QueuedDate';
	const DateRequired = true;

	/**
	 * If no date set set it to now.
	 */
	public function XonBeforeWrite() {
		if (!$this()->{static::Name}) {
			$this()->{static::Name} = static::now();
		}
		parent::onBeforeWrite();
	}

}