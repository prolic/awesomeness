namespace Prooph\EventStore\Messages;

data ReadAllEvents = ReadAllEvents { int $commitPosition, int $preparePosition, int $maxCount, bool $resolveLinkTos, bool $requireMaster };
data EventRecord = EventRecord {
    string $eventStreamId,
    int $eventNumber,
    string $eventId,
    string $eventType,
    int $dataContentType,
    int $metadataContentType,
    string $data,
    string $metadata,
    ?string $created,
    ?int $createdEpoch };
data NewEvent = NewEvent { string $eventId, string $eventType, int $dataContentType, int $metadataContentType, string $data, string $metadata };
data WriteEvents = WriteEvents { string $eventStreamId, int $expectedVersion, NewEvent[] $events, bool $requireMaster };
data WriteEventsCompleted = WriteEventsCompleted {
    OperationResult $result,
    string $message,
    int $firstEventNumber,
    int $lastEventNumber,
    ?int $preparePosition,
    ?int $commitPosition,
    ?int $currentVersion };
data DeleteStream = DeleteStream { string $eventStreamId, int $expectedVersion, bool $requireMaster, ?bool $hardDelete };
data DeleteStreamCompleted = DeleteStreamCompleted { OperationResult $result, string $message, ?int $preparePosition, ?int $commitPosition };
data TransactionStart = TransactionStart { string $eventStreamId, int $expectedVersion, bool $requireMaster };
data TransactionStartCompleted = TransactionStartCompleted { int $transactionId, OperationResult $result, string $message };
data TransactionWrite = TransactionWrite { int $transactionId, NewEvent[] $events, bool $requireMaster };
data TransactionWriteCompleted = TransactionWriteCompleted { int $transactionId, OperationResult $result, string $message };
data TransactionCommit = TransactionCommit { int $transactionId, bool $requireMaster };
data TransactionCommitCompleted = TransactionCommitCompleted {
    int $transactionId,
    OperationResult $result,
    string $message,
    int $firstEventNumber,
    int $lastEventNumber,
    ?int $preparePosition,
    ?int $commitPosition };