# Awesomeness

Experimental new EventStore Implementation.

Might be merged into prooph one day.

Mono-Repository combining multiple prooph components into one (for easier development accross repos).

## EventStore

[TODO]

## HttpEventStore

[TODO]

## PDO EventStore

Only a prototype!

requires the following php-extensions:

- pdo_mysql or pdo_pgsql

## InMemory EventStore

Not now...

## Todos

EventStore

- [x] EventStoreNodeConnection interface
- [x] EventStoreAsyncNodeConnection interface
- [x] EventStoreSubscriptionConnection interface
- [x] EventStoreAsyncSubscriptionConnection interface
- [x] EventStoreTransactionConnection interface
- [x] EventStoreAsyncTransactionConnection interface
- [x] ProjectionManagement interface
- [x] AsyncProjectionManagement interface
- [x] EventStoreStats interface
- [x] AsyncEventStoreStats interface
- [x] UserManagement interface
- [x] AsyncUserManagement interface
- [ ] Scavenging
- [ ] By Correlation ID Stream (https://github.com/EventStore/EventStore/pull/1622)
      in JS:
      fromAll().
        when({
            $any : function(s,e) {
                if(e.metadata.correlationId) {
                    linkTo("MyCorrelationId-" + e.metadata.correlationId, e);
                }
        })


HttpEventStore

- [x] HttpEventStoreConnection
- [x] HttpProjectionManagement
- [x] HttpEventStoreStats
- [x] HttpUserManagement
- [ ] HTTP-LongPoll-Header
- [ ] Scavenging

PostgresProjectionManager
- [ ] ACL Integration
- [ ] Missing functionality
