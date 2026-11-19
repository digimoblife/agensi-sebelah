#!/bin/bash
set -e

# 1. Create agensi_test database if not exists
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    SELECT 'CREATE DATABASE agensi_test'
    WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'agensi_test')\gexec

    DO \$\$
    BEGIN
        IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'agensi_tester') THEN
            CREATE ROLE agensi_tester WITH LOGIN PASSWORD '$POSTGRES_PASSWORD';
        ELSE
            ALTER ROLE agensi_tester WITH PASSWORD '$POSTGRES_PASSWORD';
        END IF;
    END
    \$\$;

    -- Lock down dev database: only $POSTGRES_USER can connect, agensi_tester is blocked
    REVOKE CONNECT ON DATABASE "$POSTGRES_DB" FROM PUBLIC;
    GRANT CONNECT ON DATABASE "$POSTGRES_DB" TO "$POSTGRES_USER";
    REVOKE ALL PRIVILEGES ON DATABASE "$POSTGRES_DB" FROM agensi_tester;

    -- Grant full privileges on test database to agensi_tester
    GRANT ALL PRIVILEGES ON DATABASE agensi_test TO agensi_tester;
    ALTER DATABASE agensi_test OWNER TO agensi_tester;
EOSQL

# 2. Configure schema & extensions inside agensi_test
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "agensi_test" <<-EOSQL
    CREATE EXTENSION IF NOT EXISTS pg_trgm;
    GRANT ALL ON SCHEMA public TO agensi_tester;
    GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO agensi_tester;
    GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO agensi_tester;
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO agensi_tester;
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO agensi_tester;
EOSQL
