namespace Prooph\EventStore\Messages;

data ConditionalWriteStatus = Succeeded | VersionMismatch | StreamDeleted deriving (Enum);
data ReadEventResult = Success | NotFound | NoStream | StreamDeleted | Error | AccessDenied deriving (Enum);
data ReadAllResult = Success | NotModified | Error | AccessDenied deriving (Enum);
data UpdatePersistentSubscriptionResult = Success | DoesNotExist | Fail | AccessDenied deriving (Enum);
data ReadStreamResult = Success | NoStream | StreamDeleted | NotModified | Error | AccessDenied deriving (Enum);
data CreatePersistentSubscriptionResult = Success | AlreadyExists | Fail | AccessDenied deriving (Enum);
data DeletePersistentSubscriptionResult = Success | DoesNotExist | Fail | AccessDenied deriving (Enum);
data NakAction = Unknown | Park | Retry | Skip | Stop deriving (Enum);
data NotHandledReason = NotReady | TooBusy | NotMaster deriving (Enum);
data ScavengeResult = Success | InProgress | Failed deriving (Enum);
data OperationResult = Success
    | PrepareTimeout
    | CommitTimeout
    | ForwardTimeout
    | WrongExpectedVersion
    | StreamDeleted
    | InvalidTransaction
    | AccessDenied deriving (Enum);
data VNodeState = Initializing
    | Unknown
    | PreReplica
    | CatchingUp
    | Cloned
    | Slave
    | PreMaster
    | Master
    | Manager
    | ShuttingDown
    | Shutdown deriving (Enum);

data SubscriptionDropReason = Unsubscribed | AccessDenied | NotFound | PersistentSubscriptionDeleted | SubscriberMaxCountReached deriving (Enum);
