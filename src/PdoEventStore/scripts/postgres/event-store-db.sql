CREATE TABLE events (
  eventId UUID NOT NULL,
  eventNumber BIGINT NOT NULL,
  eventType TEXT NOT NULL,
  data TEXT NOT NULL,
  metaData TEXT NOT NULL,
  streamId TEXT NOT NULL,
  isJson BOOLEAN NOT NULL,
  isMetaData BOOLEAN NOT NULL,
  updated CHAR(27) NOT NULL,
  linkTo UUID,
  PRIMARY KEY (eventId)
);

CREATE UNIQUE INDEX ON events (streamId, eventNumber);
