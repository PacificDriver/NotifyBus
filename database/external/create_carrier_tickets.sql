-- External PostgreSQL schema for carrier ticket synchronization
-- This table mirrors the carrier export and is used by ExternalDatabaseService::getPassengersByRaceIds()

CREATE TABLE IF NOT EXISTS carrier_tickets (
    id                  BIGINT PRIMARY KEY,
    order_id            BIGINT,
    race_id             VARCHAR(64) NOT NULL,
    passenger_type      VARCHAR(32),
    status              VARCHAR(32),
    from_station_id     BIGINT,
    from_station_name   TEXT,
    to_station_id       BIGINT,
    to_station_name     TEXT,
    depart_at           TIMESTAMP WITHOUT TIME ZONE,
    arrive_at           TIMESTAMP WITHOUT TIME ZONE,
    route_number        VARCHAR(32),
    route_name          TEXT,
    vehicle_number      VARCHAR(32),
    vehicle_model       TEXT,
    carrier_name        TEXT,
    seat_number         VARCHAR(16),
    email               VARCHAR(255),
    phone               VARCHAR(64),
    birth_date          DATE,
    document_type       VARCHAR(64),
    document_series     VARCHAR(64),
    document_number     VARCHAR(64),
    document_issued_at  DATE,
    ticket_uid          VARCHAR(64),
    ticket_number       VARCHAR(64),
    ticket_purchase_date TIMESTAMP WITHOUT TIME ZONE,
    price               NUMERIC(10, 2),
    price_total         NUMERIC(10, 2),
    service_fee         NUMERIC(10, 2),
    discount            NUMERIC(10, 2),
    paid_amount         NUMERIC(10, 2),
    created_at          TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(),
    updated_at          TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(),
    raw_payload         JSONB
);

CREATE INDEX IF NOT EXISTS carrier_tickets_race_id_idx ON carrier_tickets (race_id);
CREATE INDEX IF NOT EXISTS carrier_tickets_status_idx ON carrier_tickets (status);
CREATE INDEX IF NOT EXISTS carrier_tickets_ticket_uid_idx ON carrier_tickets (ticket_uid);


