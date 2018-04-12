CREATE TABLE events (
  eventId UUID,
  eventNumber BIGINT NOT NULL,
  eventType TEXT,
  data TEXT,
  metaData TEXT,
  stream TEXT NOT NULL,
  isJson BOOLEAN,
  isMetaData BOOLEAN,
  updated CHAR(27),
  linkTo UUID,
  PRIMARY KEY (stream, eventNumber)
);

CREATE UNIQUE INDEX ON events (stream, eventNumber);
CREATE INDEX ON events (eventId);

-- test-data
-- insert into events VALUES ('e77e969d-25e8-4c7f-b3fe-663ac6ac4cc6', 0, 'test-event', 'data', 'metadata', 'sasap', false, true, '2018-04-12T20:39:43.025366Z', null);
-- insert into events VALUES (null, 0, null, null, null, 'projected', null, null, null, 'e77e969d-25e8-4c7f-b3fe-663ac6ac4cc6');
