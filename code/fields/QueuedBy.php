<?php
namespace Modular\Fields;

use Member;

class QueuedBy extends RefOneField {
	const Name   = 'QueuedBy';
	const Schema = 'Member';

	public function onBeforeWrite() {
		parent::onBeforeWrite();
		if (!$this()->isInDB()) {
			$this()->{QueuedBy::field_name( 'ID' )} = Member::currentUserID();
		}

	}
}