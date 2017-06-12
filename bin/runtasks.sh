#!/bin/bash
set -e
function Usage() {
	echo "Usage: $0 queue_name batch_size"
}
if [ "$1" != "" ]; then
	queue_name="qn=$1"
elif [ "$MODULAR_QUEUE_NAME" != "" ]; then
	queue_name="qn=$MODULAR_QUEUE_NAME"
else
	queue_name="qn=*"
fi

if [ "$2" != "" ]; then
	batch_size="bs=$2"
elif [ "$MODULAR_QUEUE_BATCH_SIZE" != "" ]; then
	batch_size="bs=$MODULAR_QUEUE_BATCH_SIZE"
fi

../../framework/sake "dev/tasks/Modular-Tasks-QueuedTaskRunner" "$queue_name&$batch_size"