CREATE TABLE streams (
  streamId UUID NOT NULL UNIQUE,
  streamName text NOT NULL UNIQUE,
  deleted BOOLEAN NOT NULL,
  markDeleted BOOLEAN NOT NULL,
  PRIMARY KEY (streamName)
);

CREATE INDEX ON streams (markDeleted);

CREATE TABLE events (
  eventId UUID,
  eventNumber BIGINT NOT NULL,
  eventType TEXT,
  data TEXT,
  metaData TEXT,
  streamId UUID NOT NULL,
  isJson BOOLEAN,
  isMetaData BOOLEAN,
  updated CHAR(27),
  linkTo UUID,
  PRIMARY KEY (streamId, eventNumber)
);

CREATE UNIQUE INDEX ON events (streamId, eventNumber);
CREATE INDEX ON events (eventId);

-- test-data
-- insert into streams VALUES ('e77e969a-25e8-4c7f-b3fe-663ac6ac4cc6', 'sasap');
-- insert into streams VALUES ('e77e969a-21e8-4c7f-b3fe-663ac6ac4cc0', 'projected');
-- insert into events VALUES ('e77e969d-25e8-4c7f-b3fe-663ac6ac4cc6', 0, 'test-event', 'data', 'metadata', 'e77e969a-25e8-4c7f-b3fe-663ac6ac4cc6', false, true, '2018-04-12T20:39:43.025366Z', null);
-- insert into events VALUES (null, 0, null, null, null, 'e77e969a-21e8-4c7f-b3fe-663ac6ac4cc0', null, null, null, 'e77e969d-25e8-4c7f-b3fe-663ac6ac4cc6');
