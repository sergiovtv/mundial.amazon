\set ON_ERROR_STOP on

CREATE TABLE IF NOT EXISTS homepage_photo_score (
    source varchar(10) NOT NULL CHECK (source IN ('imovel', 'edificio')),
    photo_id bigint NOT NULL CHECK (photo_id > 0),
    likes integer NOT NULL DEFAULT 0 CHECK (likes >= 0),
    dislikes integer NOT NULL DEFAULT 0 CHECK (dislikes >= 0),
    score integer GENERATED ALWAYS AS (likes - (dislikes * 2)) STORED,
    hidden boolean NOT NULL DEFAULT false,
    updated_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (source, photo_id)
);

CREATE TABLE IF NOT EXISTS homepage_photo_reaction (
    source varchar(10) NOT NULL CHECK (source IN ('imovel', 'edificio')),
    photo_id bigint NOT NULL CHECK (photo_id > 0),
    visitor_hash char(64) NOT NULL,
    reaction smallint NOT NULL CHECK (reaction IN (-1, 1)),
    created_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (source, photo_id, visitor_hash)
);

CREATE INDEX IF NOT EXISTS idx_homepage_photo_score_ranking
    ON homepage_photo_score (hidden, score DESC, likes DESC);

CREATE INDEX IF NOT EXISTS idx_homepage_photo_reaction_visitor
    ON homepage_photo_reaction (visitor_hash, reaction, source, photo_id);

CREATE OR REPLACE FUNCTION refresh_homepage_photo_score()
RETURNS trigger
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
DECLARE
    target_source varchar(10);
    target_photo_id bigint;
BEGIN
    target_source := COALESCE(NEW.source, OLD.source);
    target_photo_id := COALESCE(NEW.photo_id, OLD.photo_id);

    INSERT INTO homepage_photo_score (source, photo_id, likes, dislikes, updated_at)
    SELECT
        target_source,
        target_photo_id,
        COUNT(*) FILTER (WHERE reaction = 1),
        COUNT(*) FILTER (WHERE reaction = -1),
        CURRENT_TIMESTAMP
    FROM homepage_photo_reaction
    WHERE source = target_source AND photo_id = target_photo_id
    ON CONFLICT (source, photo_id)
    DO UPDATE SET
        likes = EXCLUDED.likes,
        dislikes = EXCLUDED.dislikes,
        updated_at = EXCLUDED.updated_at;

    RETURN COALESCE(NEW, OLD);
END;
$$;

DROP TRIGGER IF EXISTS trg_refresh_homepage_photo_score ON homepage_photo_reaction;
CREATE TRIGGER trg_refresh_homepage_photo_score
AFTER INSERT OR UPDATE OF reaction OR DELETE ON homepage_photo_reaction
FOR EACH ROW EXECUTE FUNCTION refresh_homepage_photo_score();

-- Execute como administrador com: --set app_user=mundial_app
\if :{?app_user}
SELECT format('GRANT SELECT, INSERT, UPDATE ON homepage_photo_reaction TO %I', :'app_user') \gexec
SELECT format('GRANT SELECT ON homepage_photo_score TO %I', :'app_user') \gexec
\endif
