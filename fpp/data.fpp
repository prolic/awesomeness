namespace Prooph\EventStore;

data EventId = EventId deriving(Uuid);
data UserCredentials = UserCredentials { string $username, string $password } where
    | empty($username) => 'Username cannot be empty'
    | empty($password) => 'Password cannot be empty';
data RawStreamMetadataResult = RawStreamMetadataResult { string $stream, bool $isStreamDeleted, int $metastreamVersion, string $streamMetadata } where
    | empty($stream) => 'Stream cannot be empty';
data ResolvedIndexedEvent = ResolvedIndexedEvent { Messages\EventRecord $event, ?Messages\EventRecord $link };
data ConnectionSettings = ConnectionSettings {
    //ILogger $logger,
    bool $verboseLogging,
    int $maxQueueSize,
    int $axConcurrentItems,
    int $maxRetries,
    int $maxReconnections,
    bool $requireMaster,
    //TimeSpan $reconnectionDelay,
    //TimeSpan $operationTimeout,
    //TimeSpan $operationTimeoutCheckPeriod,
    UserCredentials $defaultUserCredentials,
    bool $useSslConnection,
    string $targetHost,
    bool $validateServer,
    bool $failOnNoServerResponse,
    //TimeSpan $heartbeatInterval,
    //TimeSpan $heartbeatTimeout,
    string $clusterDns,
    int $maxDiscoverAttempts,
    int $externalGossipPort,
    //GossipSeed[] $gossipSeeds,
    //TimeSpan $gossipTimeout,
    bool $preferRandomNode,
    //TimeSpan $clientConnectionTimeout
    } where
    | if ($maxQueueSize) < 1 => 'maxQueueSize must be a positive integer'
    | if ($mmaxConcurrentItems) < 1 => 'maxConcurrentItems must be a positive integer'
    | if ($maxRetries < -1) => 'maxRetries value is out of range: Allowed range: [-1, infinity]'
    | if ($maxReconnections < -1) => 'maxReconnections value is out of range: Allowed range: [-1, infinity]'
    | if ($useSslConnection && empty($targetHost)) => 'targetHost cannot be empty using SSL connection';