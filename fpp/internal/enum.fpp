namespace Prooph\EventStore\Internal;

data PersistentSubscriptionUpdateStatus = Success | NotFound | Failure | AccessDenied deriving (Enum);
