-- Initial postgres bootstrap, runs once on first cluster init.
-- Creates the separate database used by phpunit/pest so tests don't
-- destroy dev data via RefreshDatabase.

CREATE DATABASE marketplace_testing;
GRANT ALL PRIVILEGES ON DATABASE marketplace_testing TO marketplace;

\connect marketplace_testing
GRANT ALL ON SCHEMA public TO marketplace;
