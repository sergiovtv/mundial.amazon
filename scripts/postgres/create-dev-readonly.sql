\set ON_ERROR_STOP on

SELECT format('CREATE ROLE mundial_dev_ro LOGIN PASSWORD %L', :'dev_password')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'mundial_dev_ro') \gexec

SELECT format('ALTER ROLE mundial_dev_ro PASSWORD %L', :'dev_password') \gexec

GRANT CONNECT ON DATABASE imobi TO mundial_dev_ro;
GRANT USAGE ON SCHEMA public TO mundial_dev_ro;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO mundial_dev_ro;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO mundial_dev_ro;

-- A aplicação permanece somente-leitura, exceto pelo cadastro de contatos do site.
GRANT INSERT ON TABLE atendimento TO mundial_dev_ro;
GRANT USAGE, SELECT ON SEQUENCE gen_atendimento TO mundial_dev_ro;

ALTER DEFAULT PRIVILEGES FOR ROLE mundial_app IN SCHEMA public
GRANT SELECT ON TABLES TO mundial_dev_ro;
