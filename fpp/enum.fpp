namespace Prooph\EventStore;

data StreamPosition = Start | End deriving (Enum) with (Start:0, End:-1);
data ConditionalWriteStatus = Succeeded | VersionMismatch | StreamDeleted deriving (Enum);
data EventReadStatus = Success | NotFound | NoStream | StreamDeleted deriving (Enum);
data PersistentSubscriptionNakEventAction = Unknown | Park | Retry | Skip | Stop deriving (Enum);
data ReadDirection = Forward | Backward deriving (Enum);
data SliceReadStatus = Success | StreamNotFound | StreamDeleted deriving (Enum);
data SubscriptionDropReason = UserInitiated
    | NotAuthenticated
    | AccessDenied
    | SubscribingError
    | ServerError
    | ConnectionClosed
    | CatchUpError
    | ProcessingQueueOverflow
    | EventHandlerException
    | MaxSubscribersReached
    | PersistentSubscriptionDeleted
    | Unknown
    | NotFound deriving (Enum) with (
        UserInitiated:0,
        NotAuthenticated:1,
        AccessDenied:2,
        SubscribingError:3,
        ServerError:4,
        ConnectionClosed:5,
        CatchUpError:6,
        ProcessingQueueOverflow:7,
        EventHandlerException:8,
        MaxSubscribersReached:9,
        PersistentSubscriptionDeleted:10,
        Unknown:100,
        NotFound:11
    );
