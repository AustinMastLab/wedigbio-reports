The {{ $source->name }} ingestion API for {{ $event->slug }} has been inaccessible since {{ $firstFailedAt->utc()->toDateTimeString() }} UTC.

The source checkpoint will continue retrying automatically. Review the source API and the application's source checkpoint status.
