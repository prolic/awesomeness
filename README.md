# Awesomeness

Experimental new EventStore Implementation.

Might be merged into prooph one day.

Mono-Repository combining multiple prooph components into one (for easier development accross repos).

## Technical notes

- some data types are generated with prolic/fpp (see `fpp` directory)
- there is a customized fpp executable for custom output suitable for this project, see `fpp/fpp.php`

## EventStore

Base classes for usage with all event store implementations.

Note: some implementation details are skipped for now, some of those are:
- Connection instance
- Subscriptions
- Cluster settings

## Greg's EventStore

This is the first implementation being delivered

## Postgres EventStore

This will get targeted second, let's see if we can implement everything or only parts of it.

## MySQL / MariaDB EventStore

Later...

## InMemory EventStore

Not now...
