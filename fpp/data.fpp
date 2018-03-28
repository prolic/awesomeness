namespace Prooph\EventStore;

data EventId = EventId deriving(Uuid);
data UserCredentials = UserCredentials { string $username, string $password } where
    | empty($username) => 'Username cannot be empty'
    | empty($password) => 'Password cannot be empty';
data RawStreamMetadataResult = RawStreamMetadataResult { string $stream, bool $isStreamDeleted, int $metastreamVersion, string $streamMetadata } where
    | empty($stream) => 'Stream cannot be empty';