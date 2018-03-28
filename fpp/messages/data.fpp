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
    ?int $created,
    ?int $createdEpoch };
