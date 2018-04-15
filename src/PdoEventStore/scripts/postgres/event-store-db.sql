CREATE TABLE streams (
  stream_id UUID NOT NULL UNIQUE,
  stream_name text NOT NULL UNIQUE,
  deleted BOOLEAN NOT NULL,
  mark_deleted BOOLEAN NOT NULL,
  PRIMARY KEY (stream_name)
);

CREATE INDEX ON streams (mark_deleted);

CREATE TABLE events (
  event_id UUID NOT NULL,
  event_number BIGINT NOT NULL,
  event_type TEXT,
  data TEXT,
  meta_data TEXT,
  stream_id UUID NOT NULL,
  is_json BOOLEAN,
  is_meta_data BOOLEAN,
  updated CHAR(27),
  link_to UUID,
  PRIMARY KEY (stream_id, event_number)
);

CREATE UNIQUE INDEX ON events (stream_id, event_number);
CREATE INDEX ON events (event_id);

CREATE TABLE users (
    username VARCHAR(50) NOT NULL,
    full_name TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    PRIMARY KEY (username)
);

CREATE TABLE roles (
    rolename VARCHAR(50) NOT NULL,
    PRIMARY KEY (rolename)
);

CREATE TABLE user_roles (
    rolename VARCHAR(50) NOT NULL,
    username VARCHAR(50) NOT NULL
);

CREATE INDEX ON user_roles (username);

CREATE TABLE stream_acl (
    stream_id UUID NOT NULL,
    operation smallint NOT NULL,
    role VARCHAR(50),
    PRIMARY KEY (stream_id, operation, role)
);

INSERT INTO users (username, full_name, password_hash) VALUES ('admin', 'Event Store Administrator', '$2y$10$z0a0JV9SByKIeeDy4lXjGuHPpCgXcd5WYS/Hps3.dow28SvqnfGAS');
INSERT INTO users (username, full_name, password_hash) VALUES ('ops', 'Event Store Operations', '$2y$10$eI5BgCvXMUdMoynwyF1PguKpzL9lWSK7GSDt71jOaRyOwrXG70N46');
INSERT INTO roles (rolename) VALUES ('$admins');
INSERT INTO roles (rolename) VALUES ('$ops');
INSERT INTO user_roles (rolename, username) VALUES ('$admins', 'admin');
INSERT INTO user_roles (rolename, username) VALUES ('$ops', 'ops');

-- @todo write user stream acl into event-store
-- @todo make this a script executed from php (setup_event_store.php)
