--
-- PostgreSQL database dump
--

\restrict RunJdSqv6rp813mEatcRhDYH0D0TqKNyLBXS3rI9PpNaAABoWDoT9bzk9ZX3GgS

-- Dumped from database version 16.15
-- Dumped by pg_dump version 16.15

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: pgcrypto; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public;


--
-- Name: EXTENSION pgcrypto; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION pgcrypto IS 'cryptographic functions';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: accounts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.accounts (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint,
    code character varying(50) NOT NULL,
    name character varying(200) NOT NULL,
    type character varying(50) NOT NULL,
    description text,
    is_active boolean DEFAULT true NOT NULL,
    parent_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.accounts OWNER TO postgres;

--
-- Name: accounts_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.accounts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.accounts_id_seq OWNER TO postgres;

--
-- Name: accounts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.accounts_id_seq OWNED BY public.accounts.id;


--
-- Name: audit_logs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.audit_logs (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint,
    branch_id bigint,
    user_id bigint,
    user_name character varying(255),
    action character varying(100) NOT NULL,
    entity_type character varying(255) NOT NULL,
    entity_id bigint NOT NULL,
    entity_uuid uuid,
    payload json,
    changes json,
    reason character varying(500),
    ip_address character varying(45),
    user_agent character varying(500),
    occurred_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.audit_logs OWNER TO postgres;

--
-- Name: audit_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.audit_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.audit_logs_id_seq OWNER TO postgres;

--
-- Name: audit_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.audit_logs_id_seq OWNED BY public.audit_logs.id;


--
-- Name: bills; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.bills (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    order_id bigint NOT NULL,
    bill_number character varying(50) NOT NULL,
    type character varying(30) NOT NULL,
    subtotal integer DEFAULT 0 NOT NULL,
    tax_amount integer DEFAULT 0 NOT NULL,
    discount_amount integer DEFAULT 0 NOT NULL,
    tip_amount integer DEFAULT 0 NOT NULL,
    total integer DEFAULT 0 NOT NULL,
    paid_amount integer DEFAULT 0 NOT NULL,
    remaining_amount integer DEFAULT 0 NOT NULL,
    status character varying(30) DEFAULT 'open'::character varying NOT NULL,
    guest_count integer DEFAULT 1 NOT NULL,
    item_ids jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    sync_status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    version integer DEFAULT 1 NOT NULL,
    last_synced_at timestamp(0) without time zone,
    offline_id character varying(64),
    idempotency_key character varying(36),
    payload_hash character varying(64)
);


ALTER TABLE public.bills OWNER TO postgres;

--
-- Name: bills_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.bills_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.bills_id_seq OWNER TO postgres;

--
-- Name: bills_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.bills_id_seq OWNED BY public.bills.id;


--
-- Name: branches; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.branches (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    code character varying(50) NOT NULL,
    name character varying(255) NOT NULL,
    address text,
    phone character varying(50),
    default_locale character varying(10) DEFAULT 'es-CL'::character varying NOT NULL,
    tip_percentage_suggested numeric(5,2) DEFAULT '10'::numeric NOT NULL,
    allow_negative_stock boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    timezone character varying(64) DEFAULT 'America/Santiago'::character varying NOT NULL
);


ALTER TABLE public.branches OWNER TO postgres;

--
-- Name: COLUMN branches.timezone; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.branches.timezone IS 'Timezone de la sucursal. Ej: America/Santiago, America/Bogota';


--
-- Name: branches_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.branches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.branches_id_seq OWNER TO postgres;

--
-- Name: branches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.branches_id_seq OWNED BY public.branches.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache OWNER TO postgres;

--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache_locks OWNER TO postgres;

--
-- Name: cash_counts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cash_counts (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    cash_session_id bigint NOT NULL,
    user_id bigint NOT NULL,
    type character varying(30) NOT NULL,
    reason character varying(200),
    expected_amount integer DEFAULT 0 NOT NULL,
    counted_amount integer DEFAULT 0 NOT NULL,
    difference integer DEFAULT 0 NOT NULL,
    denominations jsonb,
    cash_amount integer DEFAULT 0 NOT NULL,
    card_amount integer DEFAULT 0 NOT NULL,
    transfer_amount integer DEFAULT 0 NOT NULL,
    other_amount integer DEFAULT 0 NOT NULL,
    notes text,
    has_discrepancy boolean DEFAULT false NOT NULL,
    discrepancy_explanation text,
    supervised_by bigint,
    supervised_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.cash_counts OWNER TO postgres;

--
-- Name: cash_counts_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.cash_counts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.cash_counts_id_seq OWNER TO postgres;

--
-- Name: cash_counts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.cash_counts_id_seq OWNED BY public.cash_counts.id;


--
-- Name: cash_movements; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cash_movements (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    cash_session_id bigint NOT NULL,
    user_id bigint NOT NULL,
    type character varying(30) NOT NULL,
    amount integer DEFAULT 0 NOT NULL,
    reason character varying(200) NOT NULL,
    notes text,
    reference_type character varying(50),
    reference_id character varying(100),
    authorized_by bigint,
    authorized_at timestamp(0) without time zone,
    balance_after integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.cash_movements OWNER TO postgres;

--
-- Name: cash_movements_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.cash_movements_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.cash_movements_id_seq OWNER TO postgres;

--
-- Name: cash_movements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.cash_movements_id_seq OWNED BY public.cash_movements.id;


--
-- Name: cash_registers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cash_registers (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    name character varying(100) NOT NULL,
    code character varying(50) NOT NULL,
    description text,
    opening_amount_default numeric(14,2) DEFAULT '50000'::numeric NOT NULL,
    max_amount integer DEFAULT 0 NOT NULL,
    requires_dual_control boolean DEFAULT false NOT NULL,
    printer_id character varying(100),
    drawer_serial character varying(100),
    is_active boolean DEFAULT true NOT NULL,
    last_used_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.cash_registers OWNER TO postgres;

--
-- Name: cash_registers_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.cash_registers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.cash_registers_id_seq OWNER TO postgres;

--
-- Name: cash_registers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.cash_registers_id_seq OWNED BY public.cash_registers.id;


--
-- Name: cash_sessions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cash_sessions (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    user_id bigint NOT NULL,
    session_number character varying(50) NOT NULL,
    status character varying(30) DEFAULT 'open'::character varying NOT NULL,
    opening_amount integer DEFAULT 0 NOT NULL,
    closing_amount integer,
    expected_amount integer,
    difference integer,
    opening_notes text,
    closing_notes text,
    opened_at timestamp(0) without time zone NOT NULL,
    closed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    register_id bigint
);


ALTER TABLE public.cash_sessions OWNER TO postgres;

--
-- Name: cash_sessions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.cash_sessions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.cash_sessions_id_seq OWNER TO postgres;

--
-- Name: cash_sessions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.cash_sessions_id_seq OWNED BY public.cash_sessions.id;


--
-- Name: categories; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.categories (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint,
    name_translations jsonb NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    tax_id bigint,
    parent_id bigint,
    depth smallint DEFAULT '0'::smallint NOT NULL
);


ALTER TABLE public.categories OWNER TO postgres;

--
-- Name: COLUMN categories.depth; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.categories.depth IS '0 = raíz, 1 = subcategoría (máximo 2 niveles)';


--
-- Name: categories_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.categories_id_seq OWNER TO postgres;

--
-- Name: categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.categories_id_seq OWNED BY public.categories.id;


--
-- Name: companies; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.companies (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    tax_id character varying(30) NOT NULL,
    legal_name character varying(255) NOT NULL,
    trade_name character varying(255) NOT NULL,
    default_locale character varying(10) DEFAULT 'es-CL'::character varying NOT NULL,
    fallback_locale character varying(10) DEFAULT 'es-CL'::character varying NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    settings jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.companies OWNER TO postgres;

--
-- Name: COLUMN companies.tax_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.companies.tax_id IS 'RUT/NIT/CUIT';


--
-- Name: companies_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.companies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.companies_id_seq OWNER TO postgres;

--
-- Name: companies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.companies_id_seq OWNED BY public.companies.id;


--
-- Name: company_capabilities; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.company_capabilities (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    capability_key character varying(50) NOT NULL,
    is_enabled boolean DEFAULT true NOT NULL,
    settings jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.company_capabilities OWNER TO postgres;

--
-- Name: company_capabilities_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.company_capabilities_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.company_capabilities_id_seq OWNER TO postgres;

--
-- Name: company_capabilities_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.company_capabilities_id_seq OWNED BY public.company_capabilities.id;


--
-- Name: default_notes; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.default_notes (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint,
    text_translations json NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.default_notes OWNER TO postgres;

--
-- Name: default_notes_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.default_notes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.default_notes_id_seq OWNER TO postgres;

--
-- Name: default_notes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.default_notes_id_seq OWNED BY public.default_notes.id;


--
-- Name: dining_zones; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.dining_zones (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    code character varying(50) NOT NULL,
    name_translations jsonb NOT NULL,
    color character varying(10) DEFAULT '#64748b'::character varying NOT NULL,
    floor_level integer DEFAULT 0 NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    sync_status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    version integer DEFAULT 1 NOT NULL,
    last_synced_at timestamp(0) without time zone,
    offline_id character varying(64)
);


ALTER TABLE public.dining_zones OWNER TO postgres;

--
-- Name: dining_zones_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.dining_zones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.dining_zones_id_seq OWNER TO postgres;

--
-- Name: dining_zones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.dining_zones_id_seq OWNED BY public.dining_zones.id;


--
-- Name: dte_certificates; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.dte_certificates (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    name character varying(200) NOT NULL,
    serial_number character varying(100),
    issuer character varying(200),
    certificate_content bytea NOT NULL,
    holder_rut character varying(20) NOT NULL,
    holder_name character varying(200) NOT NULL,
    valid_from date NOT NULL,
    valid_until date NOT NULL,
    environment character varying(20) DEFAULT 'certification'::character varying NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    last_used_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.dte_certificates OWNER TO postgres;

--
-- Name: dte_certificates_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.dte_certificates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.dte_certificates_id_seq OWNER TO postgres;

--
-- Name: dte_certificates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.dte_certificates_id_seq OWNED BY public.dte_certificates.id;


--
-- Name: dte_documents; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.dte_documents (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    dte_type integer NOT NULL,
    folio integer NOT NULL,
    order_id bigint,
    receiver_rut character varying(20),
    receiver_business_name character varying(200),
    net_amount numeric(12,2) NOT NULL,
    tax_amount numeric(12,2) NOT NULL,
    exempt_amount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    total_amount numeric(12,2) NOT NULL,
    sent_xml text,
    timbre_xml text,
    track_id bigint,
    sii_status character varying(30) DEFAULT 'pending'::character varying NOT NULL,
    sii_status_description character varying(500),
    issue_date date NOT NULL,
    sent_at timestamp(0) without time zone,
    accepted_at timestamp(0) without time zone,
    referenced_dte_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.dte_documents OWNER TO postgres;

--
-- Name: dte_documents_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.dte_documents_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.dte_documents_id_seq OWNER TO postgres;

--
-- Name: dte_documents_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.dte_documents_id_seq OWNED BY public.dte_documents.id;


--
-- Name: dte_folio_ranges; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.dte_folio_ranges (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    dte_type integer NOT NULL,
    folio_initial integer NOT NULL,
    folio_final integer NOT NULL,
    folio_current integer NOT NULL,
    caf_xml text NOT NULL,
    authorization_date date NOT NULL,
    authorized_rut character varying(20),
    is_active boolean DEFAULT true NOT NULL,
    closed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.dte_folio_ranges OWNER TO postgres;

--
-- Name: dte_folio_ranges_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.dte_folio_ranges_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.dte_folio_ranges_id_seq OWNER TO postgres;

--
-- Name: dte_folio_ranges_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.dte_folio_ranges_id_seq OWNED BY public.dte_folio_ranges.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.failed_jobs OWNER TO postgres;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.failed_jobs_id_seq OWNER TO postgres;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: idempotency_keys; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.idempotency_keys (
    id bigint NOT NULL,
    key uuid NOT NULL,
    request_hash character varying(64) NOT NULL,
    response_body json,
    response_code integer,
    user_id bigint,
    endpoint character varying(255),
    expires_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    company_id bigint,
    branch_id bigint,
    processing_until timestamp(0) without time zone,
    lock_token uuid
);


ALTER TABLE public.idempotency_keys OWNER TO postgres;

--
-- Name: idempotency_keys_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.idempotency_keys_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.idempotency_keys_id_seq OWNER TO postgres;

--
-- Name: idempotency_keys_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.idempotency_keys_id_seq OWNED BY public.idempotency_keys.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


ALTER TABLE public.job_batches OWNER TO postgres;

--
-- Name: jobs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


ALTER TABLE public.jobs OWNER TO postgres;

--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.jobs_id_seq OWNER TO postgres;

--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: journal_entries; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.journal_entries (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    journal_entry_number character varying(50) NOT NULL,
    entry_date timestamp(0) without time zone NOT NULL,
    reference_type character varying(50) NOT NULL,
    reference_id bigint NOT NULL,
    description text,
    user_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.journal_entries OWNER TO postgres;

--
-- Name: journal_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.journal_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.journal_entries_id_seq OWNER TO postgres;

--
-- Name: journal_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.journal_entries_id_seq OWNED BY public.journal_entries.id;


--
-- Name: ledger_entries; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.ledger_entries (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    journal_entry_id bigint NOT NULL,
    account_id bigint NOT NULL,
    debit_amount integer DEFAULT 0 NOT NULL,
    credit_amount integer DEFAULT 0 NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.ledger_entries OWNER TO postgres;

--
-- Name: ledger_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.ledger_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ledger_entries_id_seq OWNER TO postgres;

--
-- Name: ledger_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.ledger_entries_id_seq OWNED BY public.ledger_entries.id;


--
-- Name: menu_activations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.menu_activations (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    menu_id bigint NOT NULL,
    channel_type character varying(30) DEFAULT 'all'::character varying NOT NULL,
    days_of_week jsonb,
    time_from time(0) without time zone,
    time_to time(0) without time zone,
    priority integer DEFAULT 1 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.menu_activations OWNER TO postgres;

--
-- Name: COLUMN menu_activations.channel_type; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.menu_activations.channel_type IS 'dine_in, delivery, uber_eats, rappi, all';


--
-- Name: COLUMN menu_activations.days_of_week; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.menu_activations.days_of_week IS '[1..7] ISO: 1=lunes, 7=domingo. null=todos';


--
-- Name: COLUMN menu_activations.time_from; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.menu_activations.time_from IS 'null = sin límite inferior';


--
-- Name: COLUMN menu_activations.time_to; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.menu_activations.time_to IS 'null = sin límite superior';


--
-- Name: menu_activations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.menu_activations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.menu_activations_id_seq OWNER TO postgres;

--
-- Name: menu_activations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.menu_activations_id_seq OWNED BY public.menu_activations.id;


--
-- Name: menu_item_products; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.menu_item_products (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    menu_item_id bigint NOT NULL,
    product_id bigint NOT NULL,
    quantity integer DEFAULT 1 NOT NULL,
    is_substitutable boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.menu_item_products OWNER TO postgres;

--
-- Name: menu_item_products_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.menu_item_products_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.menu_item_products_id_seq OWNER TO postgres;

--
-- Name: menu_item_products_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.menu_item_products_id_seq OWNED BY public.menu_item_products.id;


--
-- Name: menu_item_replacement_rules; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.menu_item_replacement_rules (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint,
    menu_item_id bigint NOT NULL,
    target_product_id bigint,
    rule_type character varying(50) NOT NULL,
    allowed_product_id bigint,
    allowed_category_id bigint,
    max_price_delta integer,
    requires_authorization boolean DEFAULT false NOT NULL,
    priority integer DEFAULT 1 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    description_translations jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT chk_replacement_rule_type CHECK (((rule_type)::text = ANY ((ARRAY['any_product'::character varying, 'allowed_product'::character varying, 'allowed_category'::character varying])::text[])))
);


ALTER TABLE public.menu_item_replacement_rules OWNER TO postgres;

--
-- Name: menu_item_replacement_rules_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.menu_item_replacement_rules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.menu_item_replacement_rules_id_seq OWNER TO postgres;

--
-- Name: menu_item_replacement_rules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.menu_item_replacement_rules_id_seq OWNED BY public.menu_item_replacement_rules.id;


--
-- Name: menu_items; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.menu_items (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint,
    product_id bigint NOT NULL,
    base_price integer DEFAULT 0 NOT NULL,
    discount_amount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.menu_items OWNER TO postgres;

--
-- Name: menu_items_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.menu_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.menu_items_id_seq OWNER TO postgres;

--
-- Name: menu_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.menu_items_id_seq OWNED BY public.menu_items.id;


--
-- Name: menu_products; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.menu_products (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    menu_id bigint NOT NULL,
    product_id bigint NOT NULL,
    "position" integer DEFAULT 0 NOT NULL,
    is_available boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.menu_products OWNER TO postgres;

--
-- Name: menu_products_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.menu_products_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.menu_products_id_seq OWNER TO postgres;

--
-- Name: menu_products_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.menu_products_id_seq OWNED BY public.menu_products.id;


--
-- Name: menus; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.menus (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    price_list_id bigint NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.menus OWNER TO postgres;

--
-- Name: menus_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.menus_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.menus_id_seq OWNER TO postgres;

--
-- Name: menus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.menus_id_seq OWNED BY public.menus.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO postgres;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.migrations_id_seq OWNER TO postgres;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.model_has_permissions (
    permission_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


ALTER TABLE public.model_has_permissions OWNER TO postgres;

--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.model_has_roles (
    role_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


ALTER TABLE public.model_has_roles OWNER TO postgres;

--
-- Name: order_item_modifiers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.order_item_modifiers (
    id bigint NOT NULL,
    order_item_id bigint NOT NULL,
    original_product_id bigint,
    substitute_product_id bigint,
    added_product_id bigint,
    price_adjustment integer DEFAULT 0 NOT NULL,
    reason text,
    requires_authorization boolean DEFAULT false NOT NULL,
    authorized_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    sync_status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    version integer DEFAULT 1 NOT NULL,
    last_synced_at timestamp(0) without time zone,
    offline_id character varying(64)
);


ALTER TABLE public.order_item_modifiers OWNER TO postgres;

--
-- Name: order_item_modifiers_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.order_item_modifiers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.order_item_modifiers_id_seq OWNER TO postgres;

--
-- Name: order_item_modifiers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.order_item_modifiers_id_seq OWNED BY public.order_item_modifiers.id;


--
-- Name: order_items; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.order_items (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    order_id bigint NOT NULL,
    menu_item_id bigint,
    name_snapshot character varying(255) NOT NULL,
    unit_price_snapshot integer DEFAULT 0 NOT NULL,
    quantity integer DEFAULT 1 NOT NULL,
    notes text,
    subtotal integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    product_id bigint,
    tax_amount integer DEFAULT 0 NOT NULL,
    tax_rate_snapshot numeric(10,4),
    tax_name_snapshot character varying(100),
    sync_status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    version integer DEFAULT 1 NOT NULL,
    last_synced_at timestamp(0) without time zone,
    offline_id character varying(64)
);


ALTER TABLE public.order_items OWNER TO postgres;

--
-- Name: order_items_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.order_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.order_items_id_seq OWNER TO postgres;

--
-- Name: order_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.order_items_id_seq OWNED BY public.order_items.id;


--
-- Name: orders; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.orders (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    order_number character varying(255) NOT NULL,
    type character varying(255) DEFAULT 'dine_in'::character varying NOT NULL,
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    table_id bigint,
    waiter_id bigint,
    cashier_id bigint,
    subtotal integer DEFAULT 0 NOT NULL,
    tax_amount integer DEFAULT 0 NOT NULL,
    discount_amount integer DEFAULT 0 NOT NULL,
    total integer DEFAULT 0 NOT NULL,
    notes text,
    confirmed_at timestamp(0) without time zone,
    served_at timestamp(0) without time zone,
    paid_at timestamp(0) without time zone,
    closed_at timestamp(0) without time zone,
    cancelled_at timestamp(0) without time zone,
    cancellation_reason text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    assigned_cook_id bigint,
    priority character varying(20) DEFAULT 'normal'::character varying NOT NULL,
    sync_status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    version integer DEFAULT 1 NOT NULL,
    last_synced_at timestamp(0) without time zone,
    offline_id character varying(64),
    customer_name character varying(255),
    customer_phone character varying(30),
    pickup_at timestamp(0) without time zone,
    delivery_address text,
    delivery_notes text,
    fulfillment_channel character varying(255) DEFAULT 'onsite'::character varying NOT NULL,
    picked_up_at timestamp(0) without time zone,
    dispatched_at timestamp(0) without time zone,
    delivered_at timestamp(0) without time zone,
    subtotal_gross integer DEFAULT 0 NOT NULL,
    net_amount integer DEFAULT 0 NOT NULL,
    tip_amount integer DEFAULT 0 NOT NULL,
    amount_due integer DEFAULT 0 NOT NULL,
    CONSTRAINT orders_status_check CHECK (((status)::text = ANY ((ARRAY['draft'::character varying, 'confirmed'::character varying, 'preparing'::character varying, 'ready'::character varying, 'ready_for_pickup'::character varying, 'picked_up'::character varying, 'dispatched'::character varying, 'delivered'::character varying, 'served'::character varying, 'paid'::character varying, 'closed'::character varying, 'cancelled'::character varying])::text[]))),
    CONSTRAINT orders_type_check CHECK (((type)::text = ANY ((ARRAY['dine_in'::character varying, 'takeout'::character varying, 'delivery'::character varying])::text[])))
);


ALTER TABLE public.orders OWNER TO postgres;

--
-- Name: orders_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.orders_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.orders_id_seq OWNER TO postgres;

--
-- Name: orders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.orders_id_seq OWNED BY public.orders.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


ALTER TABLE public.password_reset_tokens OWNER TO postgres;

--
-- Name: payment_methods; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.payment_methods (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint,
    code character varying(50) NOT NULL,
    name_translations jsonb NOT NULL,
    type character varying(30) NOT NULL,
    icon character varying(255),
    max_amount integer,
    requires_reference boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.payment_methods OWNER TO postgres;

--
-- Name: payment_methods_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.payment_methods_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.payment_methods_id_seq OWNER TO postgres;

--
-- Name: payment_methods_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.payment_methods_id_seq OWNED BY public.payment_methods.id;


--
-- Name: payments; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.payments (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    order_id bigint NOT NULL,
    bill_id bigint,
    cash_session_id bigint,
    payment_method_id bigint NOT NULL,
    user_id bigint NOT NULL,
    payment_number character varying(50) NOT NULL,
    method_code character varying(50) NOT NULL,
    amount integer DEFAULT 0 NOT NULL,
    tip_amount integer DEFAULT 0 NOT NULL,
    total_amount integer DEFAULT 0 NOT NULL,
    reference_code character varying(100),
    status character varying(30) DEFAULT 'completed'::character varying NOT NULL,
    idempotency_key character varying(100) NOT NULL,
    notes text,
    paid_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    sync_status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    version integer DEFAULT 1 NOT NULL,
    last_synced_at timestamp(0) without time zone,
    offline_id character varying(64),
    payload_hash character varying(64),
    CONSTRAINT payments_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'completed'::character varying, 'refunded'::character varying, 'failed'::character varying])::text[])))
);


ALTER TABLE public.payments OWNER TO postgres;

--
-- Name: payments_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.payments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.payments_id_seq OWNER TO postgres;

--
-- Name: payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.payments_id_seq OWNED BY public.payments.id;


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.permissions OWNER TO postgres;

--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.permissions_id_seq OWNER TO postgres;

--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.personal_access_tokens OWNER TO postgres;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.personal_access_tokens_id_seq OWNER TO postgres;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: price_lists; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.price_lists (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint,
    name character varying(100) NOT NULL,
    display_name character varying(100),
    channel_type character varying(50),
    currency character varying(3) DEFAULT 'CLP'::character varying NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.price_lists OWNER TO postgres;

--
-- Name: COLUMN price_lists.name; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.price_lists.name IS 'Identificador: precio_comedor, precio_delivery...';


--
-- Name: COLUMN price_lists.display_name; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.price_lists.display_name IS 'Nombre visible: Precio Comedor';


--
-- Name: COLUMN price_lists.channel_type; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.price_lists.channel_type IS 'Hint opcional: dine_in, delivery, uber_eats...';


--
-- Name: price_lists_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.price_lists_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.price_lists_id_seq OWNER TO postgres;

--
-- Name: price_lists_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.price_lists_id_seq OWNED BY public.price_lists.id;


--
-- Name: print_jobs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.print_jobs (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    printer_id bigint NOT NULL,
    job_type character varying(50) NOT NULL,
    order_id bigint,
    escpos_bytes bytea NOT NULL,
    status character varying(30) DEFAULT 'pending'::character varying NOT NULL,
    attempts integer DEFAULT 0 NOT NULL,
    max_attempts integer DEFAULT 3 NOT NULL,
    error_message text,
    printed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    claimed_by character varying(100),
    claimed_at timestamp(0) without time zone
);


ALTER TABLE public.print_jobs OWNER TO postgres;

--
-- Name: print_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.print_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.print_jobs_id_seq OWNER TO postgres;

--
-- Name: print_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.print_jobs_id_seq OWNED BY public.print_jobs.id;


--
-- Name: printer_station_mappings; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.printer_station_mappings (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    printer_id bigint NOT NULL,
    category_id bigint,
    product_keywords jsonb,
    priority integer DEFAULT 1 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.printer_station_mappings OWNER TO postgres;

--
-- Name: printer_station_mappings_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.printer_station_mappings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.printer_station_mappings_id_seq OWNER TO postgres;

--
-- Name: printer_station_mappings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.printer_station_mappings_id_seq OWNED BY public.printer_station_mappings.id;


--
-- Name: printers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.printers (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    name character varying(100) NOT NULL,
    type character varying(30) NOT NULL,
    connection_type character varying(30) NOT NULL,
    host character varying(100),
    port integer DEFAULT 9100 NOT NULL,
    device_path character varying(255),
    paper_width integer DEFAULT 80 NOT NULL,
    auto_cut boolean DEFAULT true NOT NULL,
    open_drawer_on_print boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    last_printed_at timestamp(0) without time zone,
    print_count integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.printers OWNER TO postgres;

--
-- Name: printers_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.printers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.printers_id_seq OWNER TO postgres;

--
-- Name: printers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.printers_id_seq OWNED BY public.printers.id;


--
-- Name: product_prices; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.product_prices (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    product_id bigint NOT NULL,
    price_list_id bigint NOT NULL,
    price integer DEFAULT 0 NOT NULL,
    currency character varying(3) DEFAULT 'CLP'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.product_prices OWNER TO postgres;

--
-- Name: product_prices_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.product_prices_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.product_prices_id_seq OWNER TO postgres;

--
-- Name: product_prices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.product_prices_id_seq OWNED BY public.product_prices.id;


--
-- Name: product_recipes; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.product_recipes (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    product_id bigint NOT NULL,
    description text,
    yield_servings integer DEFAULT 1 NOT NULL,
    total_recipe_cost numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    version integer DEFAULT 1 NOT NULL
);


ALTER TABLE public.product_recipes OWNER TO postgres;

--
-- Name: product_recipes_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.product_recipes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.product_recipes_id_seq OWNER TO postgres;

--
-- Name: product_recipes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.product_recipes_id_seq OWNED BY public.product_recipes.id;


--
-- Name: products; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.products (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint,
    category_id bigint,
    sku character varying(100),
    name_translations jsonb NOT NULL,
    description_translations jsonb DEFAULT '{}'::jsonb NOT NULL,
    base_price integer DEFAULT 0 NOT NULL,
    tax_rate numeric(5,2) DEFAULT '19'::numeric NOT NULL,
    is_combo boolean DEFAULT false NOT NULL,
    kitchen_zone_id uuid,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    tax_id bigint
);


ALTER TABLE public.products OWNER TO postgres;

--
-- Name: products_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.products_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.products_id_seq OWNER TO postgres;

--
-- Name: products_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.products_id_seq OWNED BY public.products.id;


--
-- Name: raw_ingredient_movements; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.raw_ingredient_movements (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    raw_ingredient_id bigint NOT NULL,
    type character varying(30) NOT NULL,
    quantity_base numeric(14,4) NOT NULL,
    balance_after numeric(14,4) NOT NULL,
    reference_type character varying(50),
    reference_id bigint,
    user_id bigint,
    reason text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    idempotency_key character varying(100),
    order_item_uuid uuid,
    recipe_version integer,
    original_quantity numeric(14,4),
    original_unit character varying(20),
    cost_per_base_unit_at_transaction numeric(14,4),
    total_cost_at_transaction numeric(14,2)
);


ALTER TABLE public.raw_ingredient_movements OWNER TO postgres;

--
-- Name: raw_ingredient_movements_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.raw_ingredient_movements_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.raw_ingredient_movements_id_seq OWNER TO postgres;

--
-- Name: raw_ingredient_movements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.raw_ingredient_movements_id_seq OWNED BY public.raw_ingredient_movements.id;


--
-- Name: raw_ingredient_purchases; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.raw_ingredient_purchases (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    raw_ingredient_id bigint NOT NULL,
    user_id bigint NOT NULL,
    purchase_unit_name character varying(50) NOT NULL,
    purchase_quantity numeric(12,2) NOT NULL,
    conversion_factor_to_base numeric(14,4) NOT NULL,
    total_base_quantity_added numeric(14,4) NOT NULL,
    total_purchase_cost numeric(12,2) NOT NULL,
    calculated_cost_per_base_unit numeric(14,6) NOT NULL,
    purchase_date timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    document_type character varying(30),
    document_number character varying(50),
    supplier_name character varying(150),
    supplier_rut character varying(20)
);


ALTER TABLE public.raw_ingredient_purchases OWNER TO postgres;

--
-- Name: raw_ingredient_purchases_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.raw_ingredient_purchases_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.raw_ingredient_purchases_id_seq OWNER TO postgres;

--
-- Name: raw_ingredient_purchases_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.raw_ingredient_purchases_id_seq OWNED BY public.raw_ingredient_purchases.id;


--
-- Name: raw_ingredients; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.raw_ingredients (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    sku character varying(100) NOT NULL,
    name_translations jsonb NOT NULL,
    dimension_type character varying(20) NOT NULL,
    base_unit character varying(20) NOT NULL,
    current_stock_base numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    minimum_stock_base numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    cost_per_base_unit numeric(14,6) DEFAULT '0'::numeric NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    committed_stock_base numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    pending_incoming_base numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    reorder_level_base numeric(14,4)
);


ALTER TABLE public.raw_ingredients OWNER TO postgres;

--
-- Name: raw_ingredients_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.raw_ingredients_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.raw_ingredients_id_seq OWNER TO postgres;

--
-- Name: raw_ingredients_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.raw_ingredients_id_seq OWNED BY public.raw_ingredients.id;


--
-- Name: recipe_items; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.recipe_items (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    recipe_id bigint NOT NULL,
    raw_ingredient_id bigint NOT NULL,
    quantity_base_unit numeric(14,4) NOT NULL,
    waste_percentage numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    effective_discount_base_quantity numeric(14,4) NOT NULL,
    calculated_item_cost numeric(12,2) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    original_quantity numeric(14,4),
    original_unit character varying(20)
);


ALTER TABLE public.recipe_items OWNER TO postgres;

--
-- Name: recipe_items_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.recipe_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.recipe_items_id_seq OWNER TO postgres;

--
-- Name: recipe_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.recipe_items_id_seq OWNED BY public.recipe_items.id;


--
-- Name: refunds; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.refunds (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    payment_id bigint NOT NULL,
    refund_number character varying(50) NOT NULL,
    amount integer DEFAULT 0 NOT NULL,
    status character varying(30) NOT NULL,
    reason character varying(500),
    processed_at timestamp(0) without time zone,
    processed_by bigint,
    journal_entry_id bigint,
    notes text,
    idempotency_key uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.refunds OWNER TO postgres;

--
-- Name: refunds_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.refunds_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.refunds_id_seq OWNER TO postgres;

--
-- Name: refunds_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.refunds_id_seq OWNED BY public.refunds.id;


--
-- Name: restaurant_tables; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.restaurant_tables (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    area_code character varying(50) NOT NULL,
    area_name_translations jsonb NOT NULL,
    table_number character varying(20) NOT NULL,
    capacity integer DEFAULT 4 NOT NULL,
    status character varying(30) DEFAULT 'available'::character varying NOT NULL,
    current_order_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    sync_status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    version integer DEFAULT 1 NOT NULL,
    last_synced_at timestamp(0) without time zone,
    offline_id character varying(64),
    zone_id bigint,
    position_x integer DEFAULT 0 NOT NULL,
    position_y integer DEFAULT 0 NOT NULL,
    rotation integer DEFAULT 0 NOT NULL,
    shape character varying(20) DEFAULT 'square'::character varying NOT NULL,
    width integer,
    height integer,
    CONSTRAINT chk_table_rotation CHECK ((rotation = ANY (ARRAY[0, 90, 180, 270]))),
    CONSTRAINT chk_table_shape CHECK (((shape)::text = ANY ((ARRAY['square'::character varying, 'round'::character varying, 'rectangle'::character varying])::text[]))),
    CONSTRAINT chk_table_status CHECK (((status)::text = ANY ((ARRAY['available'::character varying, 'occupied'::character varying, 'billing'::character varying, 'maintenance'::character varying])::text[])))
);


ALTER TABLE public.restaurant_tables OWNER TO postgres;

--
-- Name: COLUMN restaurant_tables.status; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.restaurant_tables.status IS 'available, occupied, billing, maintenance';


--
-- Name: restaurant_tables_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.restaurant_tables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.restaurant_tables_id_seq OWNER TO postgres;

--
-- Name: restaurant_tables_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.restaurant_tables_id_seq OWNED BY public.restaurant_tables.id;


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.role_has_permissions (
    permission_id bigint NOT NULL,
    role_id bigint NOT NULL
);


ALTER TABLE public.role_has_permissions OWNER TO postgres;

--
-- Name: roles; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.roles OWNER TO postgres;

--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.roles_id_seq OWNER TO postgres;

--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


ALTER TABLE public.sessions OWNER TO postgres;

--
-- Name: sync_log; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sync_log (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint,
    branch_id bigint,
    sync_session_id character varying(64) NOT NULL,
    direction character varying(20) NOT NULL,
    entity_type character varying(150) NOT NULL,
    entity_id bigint NOT NULL,
    entity_uuid uuid,
    action character varying(20) NOT NULL,
    result character varying(20) NOT NULL,
    conflict_data json,
    error_message text,
    duration_ms integer,
    metadata json,
    synced_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.sync_log OWNER TO postgres;

--
-- Name: sync_log_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sync_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sync_log_id_seq OWNER TO postgres;

--
-- Name: sync_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sync_log_id_seq OWNED BY public.sync_log.id;


--
-- Name: sync_queue; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sync_queue (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint,
    branch_id bigint,
    entity_type character varying(150) NOT NULL,
    entity_id bigint NOT NULL,
    entity_uuid uuid,
    action character varying(20) NOT NULL,
    payload json NOT NULL,
    version integer DEFAULT 1 NOT NULL,
    attempts integer DEFAULT 0 NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    error_message text,
    last_attempt_at timestamp(0) without time zone,
    next_attempt_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    conflict_data json,
    processing_started_at timestamp(0) without time zone,
    lease_expires_at timestamp(0) without time zone,
    last_error_code character varying(50)
);


ALTER TABLE public.sync_queue OWNER TO postgres;

--
-- Name: sync_queue_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sync_queue_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sync_queue_id_seq OWNER TO postgres;

--
-- Name: sync_queue_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sync_queue_id_seq OWNED BY public.sync_queue.id;


--
-- Name: taxes; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.taxes (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    name character varying(100) NOT NULL,
    code character varying(20),
    type character varying(20) NOT NULL,
    rate numeric(10,4) DEFAULT '0'::numeric NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    description text,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.taxes OWNER TO postgres;

--
-- Name: taxes_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.taxes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.taxes_id_seq OWNER TO postgres;

--
-- Name: taxes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.taxes_id_seq OWNED BY public.taxes.id;


--
-- Name: terminals; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.terminals (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    code character varying(50) NOT NULL,
    name character varying(100) NOT NULL,
    locale character varying(10) DEFAULT 'es-CL'::character varying NOT NULL,
    mac_address character varying(100),
    is_kds boolean DEFAULT false NOT NULL,
    is_pos boolean DEFAULT true NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.terminals OWNER TO postgres;

--
-- Name: terminals_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.terminals_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.terminals_id_seq OWNER TO postgres;

--
-- Name: terminals_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.terminals_id_seq OWNED BY public.terminals.id;


--
-- Name: tip_payouts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tip_payouts (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    cash_session_id bigint NOT NULL,
    processed_by bigint NOT NULL,
    waiter_id bigint NOT NULL,
    amount integer DEFAULT 0 NOT NULL,
    payment_method character varying(50) NOT NULL,
    policy_type character varying(50) NOT NULL,
    notes text,
    is_voided boolean DEFAULT false NOT NULL,
    voided_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.tip_payouts OWNER TO postgres;

--
-- Name: tip_payouts_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tip_payouts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tip_payouts_id_seq OWNER TO postgres;

--
-- Name: tip_payouts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tip_payouts_id_seq OWNED BY public.tip_payouts.id;


--
-- Name: tip_policies; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tip_policies (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint,
    policy_type character varying(50) DEFAULT 'waiter_keeps'::character varying NOT NULL,
    card_tip_handling character varying(50) DEFAULT 'cash_payout'::character varying NOT NULL,
    pool_split_method character varying(50) DEFAULT 'equal'::character varying NOT NULL,
    waiter_percentage numeric(5,2) DEFAULT '100'::numeric NOT NULL,
    pool_percentage numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    effective_from timestamp(0) without time zone,
    effective_to timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.tip_policies OWNER TO postgres;

--
-- Name: tip_policies_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tip_policies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tip_policies_id_seq OWNER TO postgres;

--
-- Name: tip_policies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tip_policies_id_seq OWNED BY public.tip_policies.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    uuid uuid NOT NULL,
    company_id bigint,
    branch_id bigint,
    pos_pin_hash character varying(255),
    role character varying(50) DEFAULT 'waiter'::character varying NOT NULL,
    locale character varying(10) DEFAULT 'es-CL'::character varying NOT NULL,
    is_active boolean DEFAULT true NOT NULL
);


ALTER TABLE public.users OWNER TO postgres;

--
-- Name: COLUMN users.role; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.users.role IS 'admin, manager, cashier, waiter, kitchen';


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO postgres;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: accounts id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.accounts ALTER COLUMN id SET DEFAULT nextval('public.accounts_id_seq'::regclass);


--
-- Name: audit_logs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.audit_logs ALTER COLUMN id SET DEFAULT nextval('public.audit_logs_id_seq'::regclass);


--
-- Name: bills id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.bills ALTER COLUMN id SET DEFAULT nextval('public.bills_id_seq'::regclass);


--
-- Name: branches id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.branches ALTER COLUMN id SET DEFAULT nextval('public.branches_id_seq'::regclass);


--
-- Name: cash_counts id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_counts ALTER COLUMN id SET DEFAULT nextval('public.cash_counts_id_seq'::regclass);


--
-- Name: cash_movements id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_movements ALTER COLUMN id SET DEFAULT nextval('public.cash_movements_id_seq'::regclass);


--
-- Name: cash_registers id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_registers ALTER COLUMN id SET DEFAULT nextval('public.cash_registers_id_seq'::regclass);


--
-- Name: cash_sessions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_sessions ALTER COLUMN id SET DEFAULT nextval('public.cash_sessions_id_seq'::regclass);


--
-- Name: categories id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.categories ALTER COLUMN id SET DEFAULT nextval('public.categories_id_seq'::regclass);


--
-- Name: companies id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.companies ALTER COLUMN id SET DEFAULT nextval('public.companies_id_seq'::regclass);


--
-- Name: company_capabilities id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.company_capabilities ALTER COLUMN id SET DEFAULT nextval('public.company_capabilities_id_seq'::regclass);


--
-- Name: default_notes id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.default_notes ALTER COLUMN id SET DEFAULT nextval('public.default_notes_id_seq'::regclass);


--
-- Name: dining_zones id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dining_zones ALTER COLUMN id SET DEFAULT nextval('public.dining_zones_id_seq'::regclass);


--
-- Name: dte_certificates id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dte_certificates ALTER COLUMN id SET DEFAULT nextval('public.dte_certificates_id_seq'::regclass);


--
-- Name: dte_documents id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dte_documents ALTER COLUMN id SET DEFAULT nextval('public.dte_documents_id_seq'::regclass);


--
-- Name: dte_folio_ranges id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dte_folio_ranges ALTER COLUMN id SET DEFAULT nextval('public.dte_folio_ranges_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: idempotency_keys id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.idempotency_keys ALTER COLUMN id SET DEFAULT nextval('public.idempotency_keys_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: journal_entries id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.journal_entries ALTER COLUMN id SET DEFAULT nextval('public.journal_entries_id_seq'::regclass);


--
-- Name: ledger_entries id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ledger_entries ALTER COLUMN id SET DEFAULT nextval('public.ledger_entries_id_seq'::regclass);


--
-- Name: menu_activations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_activations ALTER COLUMN id SET DEFAULT nextval('public.menu_activations_id_seq'::regclass);


--
-- Name: menu_item_products id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_item_products ALTER COLUMN id SET DEFAULT nextval('public.menu_item_products_id_seq'::regclass);


--
-- Name: menu_item_replacement_rules id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_item_replacement_rules ALTER COLUMN id SET DEFAULT nextval('public.menu_item_replacement_rules_id_seq'::regclass);


--
-- Name: menu_items id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_items ALTER COLUMN id SET DEFAULT nextval('public.menu_items_id_seq'::regclass);


--
-- Name: menu_products id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_products ALTER COLUMN id SET DEFAULT nextval('public.menu_products_id_seq'::regclass);


--
-- Name: menus id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menus ALTER COLUMN id SET DEFAULT nextval('public.menus_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: order_item_modifiers id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.order_item_modifiers ALTER COLUMN id SET DEFAULT nextval('public.order_item_modifiers_id_seq'::regclass);


--
-- Name: order_items id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.order_items ALTER COLUMN id SET DEFAULT nextval('public.order_items_id_seq'::regclass);


--
-- Name: orders id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orders ALTER COLUMN id SET DEFAULT nextval('public.orders_id_seq'::regclass);


--
-- Name: payment_methods id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payment_methods ALTER COLUMN id SET DEFAULT nextval('public.payment_methods_id_seq'::regclass);


--
-- Name: payments id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payments ALTER COLUMN id SET DEFAULT nextval('public.payments_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: price_lists id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.price_lists ALTER COLUMN id SET DEFAULT nextval('public.price_lists_id_seq'::regclass);


--
-- Name: print_jobs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.print_jobs ALTER COLUMN id SET DEFAULT nextval('public.print_jobs_id_seq'::regclass);


--
-- Name: printer_station_mappings id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.printer_station_mappings ALTER COLUMN id SET DEFAULT nextval('public.printer_station_mappings_id_seq'::regclass);


--
-- Name: printers id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.printers ALTER COLUMN id SET DEFAULT nextval('public.printers_id_seq'::regclass);


--
-- Name: product_prices id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.product_prices ALTER COLUMN id SET DEFAULT nextval('public.product_prices_id_seq'::regclass);


--
-- Name: product_recipes id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.product_recipes ALTER COLUMN id SET DEFAULT nextval('public.product_recipes_id_seq'::regclass);


--
-- Name: products id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products ALTER COLUMN id SET DEFAULT nextval('public.products_id_seq'::regclass);


--
-- Name: raw_ingredient_movements id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.raw_ingredient_movements ALTER COLUMN id SET DEFAULT nextval('public.raw_ingredient_movements_id_seq'::regclass);


--
-- Name: raw_ingredient_purchases id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.raw_ingredient_purchases ALTER COLUMN id SET DEFAULT nextval('public.raw_ingredient_purchases_id_seq'::regclass);


--
-- Name: raw_ingredients id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.raw_ingredients ALTER COLUMN id SET DEFAULT nextval('public.raw_ingredients_id_seq'::regclass);


--
-- Name: recipe_items id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.recipe_items ALTER COLUMN id SET DEFAULT nextval('public.recipe_items_id_seq'::regclass);


--
-- Name: refunds id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.refunds ALTER COLUMN id SET DEFAULT nextval('public.refunds_id_seq'::regclass);


--
-- Name: restaurant_tables id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.restaurant_tables ALTER COLUMN id SET DEFAULT nextval('public.restaurant_tables_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: sync_log id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sync_log ALTER COLUMN id SET DEFAULT nextval('public.sync_log_id_seq'::regclass);


--
-- Name: sync_queue id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sync_queue ALTER COLUMN id SET DEFAULT nextval('public.sync_queue_id_seq'::regclass);


--
-- Name: taxes id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.taxes ALTER COLUMN id SET DEFAULT nextval('public.taxes_id_seq'::regclass);


--
-- Name: terminals id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.terminals ALTER COLUMN id SET DEFAULT nextval('public.terminals_id_seq'::regclass);


--
-- Name: tip_payouts id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tip_payouts ALTER COLUMN id SET DEFAULT nextval('public.tip_payouts_id_seq'::regclass);


--
-- Name: tip_policies id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tip_policies ALTER COLUMN id SET DEFAULT nextval('public.tip_policies_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: accounts; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.accounts (id, uuid, company_id, branch_id, code, name, type, description, is_active, parent_id, created_at, updated_at) FROM stdin;
1	12733ba8-29b7-48a8-a7d9-575259553fab	1	1	1100	Efectivo en Caja	asset	Efectivo físico en caja	t	\N	2026-10-19 18:29:34	2026-10-19 18:29:34
2	63e4084a-f42f-4bdb-99d2-0cc478adda23	1	1	1200	Bancos	asset	Cuentas bancarias	t	\N	2026-10-19 18:29:34	2026-10-19 18:29:34
3	b1fa9d80-caf5-41d4-81f1-10cce8ef749b	1	1	1300	Por Cobrar Tarjetas	asset	Pagos con tarjeta por liquidar	t	\N	2026-10-19 18:29:34	2026-10-19 18:29:34
4	54414adc-b405-45bd-87fc-6f42f8281c0e	1	1	1400	Gift Cards por Canjear	asset	Gift cards emitidas pendientes de canje	t	\N	2026-10-19 18:29:34	2026-10-19 18:29:34
5	aa100d59-4ac3-4169-a653-cb3da634e685	1	1	2100	IVA por Pagar	liability	IVA colectado por pagar al SII	t	\N	2026-10-19 18:29:34	2026-10-19 18:29:34
6	5bbcb2a8-f820-4959-93b1-f817cb4b1522	1	1	2200	Propinas por Pagar	liability	Propinas colectadas por pagar a garzones	t	\N	2026-10-19 18:29:34	2026-10-19 18:29:34
7	58bde7f5-ce09-4f67-9d2b-5b6d1d2d57ca	1	1	4100	Ingresos por Ventas	revenue	Ingresos por ventas de productos/servicios	t	\N	2026-10-19 18:29:34	2026-10-19 18:29:34
8	7a86082e-e39e-464d-ab7d-2aa4597c94ee	1	1	4200	Descuentos sobre Ventas	revenue	Descuentos aplicados (contra-ingreso)	t	\N	2026-10-19 18:29:34	2026-10-19 18:29:34
9	5351c15a-b201-4b1d-996c-028cc628ae8a	1	1	5100	Costo de Ventas	expense	Costo de los productos vendidos	t	\N	2026-10-19 18:29:34	2026-10-19 18:29:34
10	05b77e74-d34d-484d-a23f-d41b338c9508	1	1	5200	Gastos Operativos	expense	Gastos generales del negocio	t	\N	2026-10-19 18:29:34	2026-10-19 18:29:34
\.


--
-- Data for Name: audit_logs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.audit_logs (id, uuid, company_id, branch_id, user_id, user_name, action, entity_type, entity_id, entity_uuid, payload, changes, reason, ip_address, user_agent, occurred_at, created_at, updated_at) FROM stdin;
1	af74142d-350e-42a7-9e4b-8c22f0fd9600	1	1	1	Admin Principal	drawer_opened	Modules\\Payments\\Domain\\Entities\\CashSession	1	c293b196-d412-4123-8185-55bca2a3866e	{"session_number":"CS-20261017-2AED9D","opening_amount":12000}	\N	Apertura de sesión de caja / 打开收银会话	172.21.0.1	Mozilla/5.0 (X11; Ubuntu; Linux x86_64) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/60.5 Safari/605.1.15	2026-10-17 14:00:02	\N	\N
\.


--
-- Data for Name: bills; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.bills (id, uuid, company_id, branch_id, order_id, bill_number, type, subtotal, tax_amount, discount_amount, tip_amount, total, paid_amount, remaining_amount, status, guest_count, item_ids, created_at, updated_at, deleted_at, sync_status, version, last_synced_at, offline_id, idempotency_key, payload_hash) FROM stdin;
\.


--
-- Data for Name: branches; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.branches (id, uuid, company_id, code, name, address, phone, default_locale, tip_percentage_suggested, allow_negative_stock, is_active, created_at, updated_at, deleted_at, timezone) FROM stdin;
1	5f00c6d5-ac20-4440-a400-8ac7870676c8	1	SCL-001	Sucursal Centro	Av. Providencia 1234, Santiago	+56 2 2345 6789	es-CL	10.00	f	t	2026-10-12 00:16:42	2026-10-12 00:16:42	\N	America/Santiago
2	2cf88311-174a-4434-886a-76681eda41a9	1	SCL-002	Sucursal Providencia	Av. Providencia 5678, Santiago	+56 2 2987 6543	zh-CN	10.00	f	t	2026-10-12 00:16:42	2026-10-12 00:16:42	\N	America/Santiago
\.


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cache (key, value, expiration) FROM stdin;
pos-restaurant-cache-DWyvUKfVl4vWIjjI	a:1:{s:11:"valid_until";i:1791771868;}	1792974568
pos-restaurant-cache-zRQsGe7C7VxZ9LZY	a:1:{s:11:"valid_until";i:1791764885;}	1792974425
pos-restaurant-cache-cxFrz3w7q79aNRbM	a:1:{s:11:"valid_until";i:1792416411;}	1793622591
pos-restaurant-cache-TVC6MOZyBqgVO3xJ	a:1:{s:11:"valid_until";i:1792433838;}	1793622558
pos-restaurant-cache-eEtKHITExzH0USs1	a:1:{s:11:"valid_until";i:1792302981;}	1793453421
pos-restaurant-cache-idempotency:1:1:1d2bf061-5b7e-4a0f-83e9-d40d755b0130	a:3:{s:12:"request_hash";s:64:"31e0eb9f5db3a19662113e36b2e8c3946ca7e02bff31cbaf52707cd4356f73c8";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:34:{s:4:"uuid";s:36:"2b188715-9d56-4293-894b-e1c13904fcb4";s:12:"order_number";s:21:"ORD-001-20261016-0001";s:4:"type";s:7:"dine_in";s:19:"fulfillment_channel";s:6:"onsite";s:25:"fulfillment_channel_label";s:11:"En el local";s:10:"type_label";s:19:"Para servir en mesa";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:5:"table";a:3:{s:4:"uuid";s:36:"f8c4272e-f51c-485f-9c1f-2fc14dd7744d";s:12:"table_number";s:4:"B-01";s:9:"area_code";s:3:"BAR";}s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T14:28:36+00:00";s:10:"updated_at";s:25:"2026-10-16T14:28:36+00:00";}}}	1792247316
pos-restaurant-cache-X4oIU6qWjVqnwjXv	a:1:{s:11:"valid_until";i:1792166740;}	1793365900
pos-restaurant-cache-UTP44fltr6zu88Nv	a:1:{s:11:"valid_until";i:1791768388;}	1792974568
pos-restaurant-cache-421762700a2c10da200dfdb9fe1032c83dd620c2:timer	i:1792412973;	1792412973
pos-restaurant-cache-421762700a2c10da200dfdb9fe1032c83dd620c2	i:1;	1792412973
pos-restaurant-cache-idempotency:1:1:bf516d54-15e4-44ce-9d04-5c745fb386b7	a:3:{s:12:"request_hash";s:64:"7df1a8913c72e49954e1504b5e0d7e35fb9ea100848e0cce5d66c37a867347af";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:12:{s:4:"uuid";s:36:"f70b2e8b-851d-429b-a0d9-ad7c6a11d589";s:14:"payment_number";s:27:"PAY-SCL-001-20261019-C9188A";s:11:"method_code";s:4:"CASH";s:6:"amount";i:3500;s:10:"tip_amount";i:0;s:12:"total_amount";i:3500;s:14:"reference_code";N;s:6:"status";s:9:"completed";s:15:"idempotency_key";s:36:"bf516d54-15e4-44ce-9d04-5c745fb386b7";s:5:"notes";N;s:7:"paid_at";s:25:"2026-10-19T19:59:08+00:00";s:10:"created_at";s:25:"2026-10-19T19:59:08+00:00";}}}	1792526348
pos-restaurant-cache-idempotency:1:1:017ccd70-3fb0-440d-b58c-294513eddcaa	a:3:{s:12:"request_hash";s:64:"7e5b67c20ea335111d55f634660a4aae6c915921aa26627e0fa42121b3ccc708";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:34:{s:4:"uuid";s:36:"2b188715-9d56-4293-894b-e1c13904fcb4";s:12:"order_number";s:21:"ORD-001-20261016-0001";s:4:"type";s:7:"dine_in";s:19:"fulfillment_channel";s:6:"onsite";s:25:"fulfillment_channel_label";s:11:"En el local";s:10:"type_label";s:19:"Para servir en mesa";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:5:"table";a:3:{s:4:"uuid";s:36:"f8c4272e-f51c-485f-9c1f-2fc14dd7744d";s:12:"table_number";s:4:"B-01";s:9:"area_code";s:3:"BAR";}s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:9:{s:4:"uuid";s:36:"b6501f18-cf75-43a7-b230-080a053daf1e";s:14:"menu_item_uuid";N;s:4:"name";s:19:"Caldillo de Congrio";s:10:"unit_price";i:8900;s:8:"quantity";i:1;s:8:"subtotal";i:8900;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-16T14:28:36+00:00";}}s:8:"subtotal";i:8900;s:10:"tax_amount";i:1421;s:15:"discount_amount";i:0;s:5:"total";i:8900;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T14:28:36+00:00";s:10:"updated_at";s:25:"2026-10-16T14:28:36+00:00";}}}	1792247316
pos-restaurant-cache-XGvqUH17vIHskRaF	a:1:{s:11:"valid_until";i:1792121182;}	1793327362
pos-restaurant-cache-kghid2gcDm3WsgeP	a:1:{s:11:"valid_until";i:1792121182;}	1793327362
pos-restaurant-cache-356a192b7913b04c54574d18c28d46e6395428ab:timer	i:1792442971;	1792442971
pos-restaurant-cache-356a192b7913b04c54574d18c28d46e6395428ab	i:1;	1792442971
pos-restaurant-cache-syGkdrNv7qzwPdXT	a:1:{s:11:"valid_until";i:1792159760;}	1793365940
pos-restaurant-cache-idempotency:1:1:81154c6f-208f-402b-8309-b98f8c0741c5	a:3:{s:12:"request_hash";s:64:"bf127eea2a5a4f4a8cd2d26a07235101698eb89f1ee34082bcb7071a7e41e9d6";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:34:{s:4:"uuid";s:36:"2b188715-9d56-4293-894b-e1c13904fcb4";s:12:"order_number";s:21:"ORD-001-20261016-0001";s:4:"type";s:7:"dine_in";s:19:"fulfillment_channel";s:6:"onsite";s:25:"fulfillment_channel_label";s:11:"En el local";s:10:"type_label";s:19:"Para servir en mesa";s:6:"status";s:9:"confirmed";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:5:"table";a:3:{s:4:"uuid";s:36:"f8c4272e-f51c-485f-9c1f-2fc14dd7744d";s:12:"table_number";s:4:"B-01";s:9:"area_code";s:3:"BAR";}s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:8:{s:4:"uuid";s:36:"b6501f18-cf75-43a7-b230-080a053daf1e";s:14:"menu_item_uuid";N;s:4:"name";s:19:"Caldillo de Congrio";s:10:"unit_price";i:8900;s:8:"quantity";i:1;s:8:"subtotal";i:8900;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-16T14:28:36+00:00";}}s:8:"subtotal";i:8900;s:10:"tax_amount";i:1421;s:15:"discount_amount";i:0;s:5:"total";i:8900;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-16T14:28:37+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T14:28:36+00:00";s:10:"updated_at";s:25:"2026-10-16T14:28:37+00:00";}}}	1792247317
pos-restaurant-cache-EVja3raL5BudDx7a	a:1:{s:11:"valid_until";i:1792163255;}	1793365955
pos-restaurant-cache-idempotency:1:1:d7f29f22-fd3e-43e6-adb7-d60186369683	a:3:{s:12:"request_hash";s:64:"d2c0c056a267c4b26e1b1d34eed6b34085de73fff7eb0407bf19ab00aa9e4624";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:34:{s:4:"uuid";s:36:"2b188715-9d56-4293-894b-e1c13904fcb4";s:12:"order_number";s:21:"ORD-001-20261016-0001";s:4:"type";s:7:"dine_in";s:19:"fulfillment_channel";s:6:"onsite";s:25:"fulfillment_channel_label";s:11:"En el local";s:10:"type_label";s:19:"Para servir en mesa";s:6:"status";s:9:"preparing";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:5:"table";a:3:{s:4:"uuid";s:36:"f8c4272e-f51c-485f-9c1f-2fc14dd7744d";s:12:"table_number";s:4:"B-01";s:9:"area_code";s:3:"BAR";}s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:8:{s:4:"uuid";s:36:"b6501f18-cf75-43a7-b230-080a053daf1e";s:14:"menu_item_uuid";N;s:4:"name";s:19:"Caldillo de Congrio";s:10:"unit_price";i:8900;s:8:"quantity";i:1;s:8:"subtotal";i:8900;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-16T14:28:36+00:00";}}s:8:"subtotal";i:8900;s:10:"tax_amount";i:1421;s:15:"discount_amount";i:0;s:5:"total";i:8900;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-16T14:28:37+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T14:28:36+00:00";s:10:"updated_at";s:25:"2026-10-16T15:17:35+00:00";}}}	1792250255
pos-restaurant-cache-idempotency:1:1:8d34b41c-7ec2-4827-b431-9025a95152c5	a:3:{s:12:"request_hash";s:64:"04c98f0c6d43fe439a702df403795c7df37029d08952b0d94759a244830c1a31";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:34:{s:4:"uuid";s:36:"2b188715-9d56-4293-894b-e1c13904fcb4";s:12:"order_number";s:21:"ORD-001-20261016-0001";s:4:"type";s:7:"dine_in";s:19:"fulfillment_channel";s:6:"onsite";s:25:"fulfillment_channel_label";s:11:"En el local";s:10:"type_label";s:19:"Para servir en mesa";s:6:"status";s:5:"ready";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:5:"table";a:3:{s:4:"uuid";s:36:"f8c4272e-f51c-485f-9c1f-2fc14dd7744d";s:12:"table_number";s:4:"B-01";s:9:"area_code";s:3:"BAR";}s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:8:{s:4:"uuid";s:36:"b6501f18-cf75-43a7-b230-080a053daf1e";s:14:"menu_item_uuid";N;s:4:"name";s:19:"Caldillo de Congrio";s:10:"unit_price";i:8900;s:8:"quantity";i:1;s:8:"subtotal";i:8900;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-16T14:28:36+00:00";}}s:8:"subtotal";i:8900;s:10:"tax_amount";i:1421;s:15:"discount_amount";i:0;s:5:"total";i:8900;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-16T14:28:37+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T14:28:36+00:00";s:10:"updated_at";s:25:"2026-10-16T15:17:38+00:00";}}}	1792250258
pos-restaurant-cache-idempotency:1:1:d5f04cfa-f6a6-46bb-9655-ccf058f0035b	a:3:{s:12:"request_hash";s:64:"8d9ccd4958fd52a828cb5391400d283f7b8045a805dd921ed7d2915f5f0694ef";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:34:{s:4:"uuid";s:36:"2b188715-9d56-4293-894b-e1c13904fcb4";s:12:"order_number";s:21:"ORD-001-20261016-0001";s:4:"type";s:7:"dine_in";s:19:"fulfillment_channel";s:6:"onsite";s:25:"fulfillment_channel_label";s:11:"En el local";s:10:"type_label";s:19:"Para servir en mesa";s:6:"status";s:6:"served";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:5:"table";a:3:{s:4:"uuid";s:36:"f8c4272e-f51c-485f-9c1f-2fc14dd7744d";s:12:"table_number";s:4:"B-01";s:9:"area_code";s:3:"BAR";}s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:8:{s:4:"uuid";s:36:"b6501f18-cf75-43a7-b230-080a053daf1e";s:14:"menu_item_uuid";N;s:4:"name";s:19:"Caldillo de Congrio";s:10:"unit_price";i:8900;s:8:"quantity";i:1;s:8:"subtotal";i:8900;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-16T14:28:36+00:00";}}s:8:"subtotal";i:8900;s:10:"tax_amount";i:1421;s:15:"discount_amount";i:0;s:5:"total";i:8900;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-16T14:28:37+00:00";s:9:"served_at";s:25:"2026-10-16T15:17:41+00:00";s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T14:28:36+00:00";s:10:"updated_at";s:25:"2026-10-16T15:17:41+00:00";}}}	1792250261
pos-restaurant-cache-ZA4ZOTDgdqqt55M4	a:1:{s:11:"valid_until";i:1792250747;}	1793453447
pos-restaurant-cache-idempotency:1:1:53c44f1a-167a-4158-98e9-d7b7804ae51d	a:3:{s:12:"request_hash";s:64:"31036a138a07f4b53e80aa4128d937156d5590aea05c2169e11bb9fcb4883374";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"953a0899-7047-41a4-a386-9032159c99a8";s:12:"order_number";s:21:"ORD-001-20261017-0006";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:50:36+00:00";s:10:"updated_at";s:25:"2026-10-17T15:50:36+00:00";}}}	1792338636
pos-restaurant-cache-mFu6AiyHkvQqVzyG	a:1:{s:11:"valid_until";i:1792264698;}	1793453418
pos-restaurant-cache-qFm4fRJn8R8yLQIH	a:1:{s:11:"valid_until";i:1792271658;}	1793453418
pos-restaurant-cache-idempotency:1:1:436eb5bb-0784-4284-8eb3-6719ee89716f	a:3:{s:12:"request_hash";s:64:"f9fe02acae380e23c04b549e4552f9584a08285149368b56a0914765b1c5935a";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"d975b860-a5e5-46ce-be54-793a8a592152";s:12:"order_number";s:21:"ORD-001-20261016-0002";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T15:20:12+00:00";s:10:"updated_at";s:25:"2026-10-16T15:20:12+00:00";}}}	1792250412
pos-restaurant-cache-idempotency:1:1:428cf852-da30-4577-83e9-dfbb6acda2ad	a:3:{s:12:"request_hash";s:64:"971eed7ba31f4666dc8dbdc19459d4b099cc458c6677876700c610e7f673dd3c";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"d975b860-a5e5-46ce-be54-793a8a592152";s:12:"order_number";s:21:"ORD-001-20261016-0002";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:9:{s:4:"uuid";s:36:"42cd2483-adb2-443c-b551-dbad6c85524d";s:14:"menu_item_uuid";N;s:4:"name";s:23:"Empanadas de Pino (3un)";s:10:"unit_price";i:3500;s:8:"quantity";i:1;s:8:"subtotal";i:3500;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-16T15:20:13+00:00";}}s:8:"subtotal";i:3500;s:10:"tax_amount";i:559;s:15:"discount_amount";i:0;s:5:"total";i:3500;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T15:20:12+00:00";s:10:"updated_at";s:25:"2026-10-16T15:20:13+00:00";}}}	1792250413
pos-restaurant-cache-idempotency:1:1:95be9535-9c4d-43f2-bb9c-ed87fc0ae88f	a:3:{s:12:"request_hash";s:64:"e3b2d6edacf8e2ade3880fcc2fdaf91346e4d015fcf27bc73a4d83e3d484f8fa";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"d975b860-a5e5-46ce-be54-793a8a592152";s:12:"order_number";s:21:"ORD-001-20261016-0002";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:9:"confirmed";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:8:{s:4:"uuid";s:36:"42cd2483-adb2-443c-b551-dbad6c85524d";s:14:"menu_item_uuid";N;s:4:"name";s:23:"Empanadas de Pino (3un)";s:10:"unit_price";i:3500;s:8:"quantity";i:1;s:8:"subtotal";i:3500;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-16T15:20:13+00:00";}}s:8:"subtotal";i:3500;s:10:"tax_amount";i:559;s:15:"discount_amount";i:0;s:5:"total";i:3500;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-16T15:20:13+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T15:20:12+00:00";s:10:"updated_at";s:25:"2026-10-16T15:20:13+00:00";}}}	1792250413
pos-restaurant-cache-zX0toFp7eWl3w9E4	a:1:{s:11:"valid_until";i:1792250747;}	1793453447
pos-restaurant-cache-a8EIr1f66UCEePBB	a:1:{s:11:"valid_until";i:1792289060;}	1793453420
pos-restaurant-cache-idempotency:1:1:0e2de178-3c31-45fc-a508-7cc895652cfe	a:3:{s:12:"request_hash";s:64:"974ef9e0506b3c1919391aa9b4c07fbacaed0c2e20a7f83f5ad3f6f3b6b24919";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"6235e9fa-a1c6-4485-94f4-a2f9c1f3e097";s:12:"order_number";s:21:"ORD-001-20261016-0006";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T15:52:08+00:00";s:10:"updated_at";s:25:"2026-10-16T15:52:08+00:00";}}}	1792252328
pos-restaurant-cache-xsPluxXbvdspIEpW	a:1:{s:11:"valid_until";i:1792247252;}	1793453432
pos-restaurant-cache-kPSPTsJohomYUgE9	a:1:{s:11:"valid_until";i:1792285579;}	1793453419
pos-restaurant-cache-VEAQArLHUdmowHTT	a:1:{s:11:"valid_until";i:1792327342;}	1793453422
pos-restaurant-cache-idempotency:1:1:954570bd-4f65-49db-b7f6-d100b4bbfa34	a:3:{s:12:"request_hash";s:64:"b3fe84cb00b310a0f309ffc069020fefd8486e09d31c65281d27508822c35097";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"6235e9fa-a1c6-4485-94f4-a2f9c1f3e097";s:12:"order_number";s:21:"ORD-001-20261016-0006";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:9:{s:4:"uuid";s:36:"56c569b0-8c9f-4a4b-a98a-a6978e2f2ce6";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-16T15:52:08+00:00";}}s:8:"subtotal";i:1000;s:10:"tax_amount";i:160;s:15:"discount_amount";i:0;s:5:"total";i:1000;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T15:52:08+00:00";s:10:"updated_at";s:25:"2026-10-16T15:52:08+00:00";}}}	1792252328
pos-restaurant-cache-idempotency:1:1:09d726e8-6937-4cd9-8293-a5dc049f731f	a:3:{s:12:"request_hash";s:64:"d98b1b4cfc50f9eab43d25f14fa5ca28a4dd42a60c9e111f5d60bf5ef2f8bbd6";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"6235e9fa-a1c6-4485-94f4-a2f9c1f3e097";s:12:"order_number";s:21:"ORD-001-20261016-0006";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:2:{i:0;a:9:{s:4:"uuid";s:36:"9cd3d388-04b1-44a3-aee7-c81f9d898c5e";s:14:"menu_item_uuid";N;s:4:"name";s:15:"Coca-Cola 500ml";s:10:"unit_price";i:1500;s:8:"quantity";i:1;s:8:"subtotal";i:1500;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-16T15:52:08+00:00";}i:1;a:9:{s:4:"uuid";s:36:"56c569b0-8c9f-4a4b-a98a-a6978e2f2ce6";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-16T15:52:08+00:00";}}s:8:"subtotal";i:2500;s:10:"tax_amount";i:399;s:15:"discount_amount";i:0;s:5:"total";i:2500;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T15:52:08+00:00";s:10:"updated_at";s:25:"2026-10-16T15:52:08+00:00";}}}	1792252328
pos-restaurant-cache-idempotency:1:1:28aeff34-15fe-40aa-afeb-ca40a86d168d	a:3:{s:12:"request_hash";s:64:"bafacc815512d96957149b8cbd161313ff76a2513087f74aee6930231dfa4a00";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"6235e9fa-a1c6-4485-94f4-a2f9c1f3e097";s:12:"order_number";s:21:"ORD-001-20261016-0006";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:9:"confirmed";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:2:{i:0;a:8:{s:4:"uuid";s:36:"9cd3d388-04b1-44a3-aee7-c81f9d898c5e";s:14:"menu_item_uuid";N;s:4:"name";s:15:"Coca-Cola 500ml";s:10:"unit_price";i:1500;s:8:"quantity";i:1;s:8:"subtotal";i:1500;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-16T15:52:08+00:00";}i:1;a:8:{s:4:"uuid";s:36:"56c569b0-8c9f-4a4b-a98a-a6978e2f2ce6";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-16T15:52:08+00:00";}}s:8:"subtotal";i:2500;s:10:"tax_amount";i:399;s:15:"discount_amount";i:0;s:5:"total";i:2500;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-16T15:52:08+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T15:52:08+00:00";s:10:"updated_at";s:25:"2026-10-16T15:52:08+00:00";}}}	1792252328
pos-restaurant-cache-idempotency:1:1:3af5d544-ae1e-4cb8-9e87-2c80e684ead4	a:3:{s:12:"request_hash";s:64:"3b82827fb9b9e433a84d4cb9eb37c2c9e41b31b290f6df67fb77d4bf04ebbb28";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"ab321bf9-19aa-4689-9dbb-52706407d4a7";s:12:"order_number";s:21:"ORD-001-20261016-0007";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T15:55:31+00:00";s:10:"updated_at";s:25:"2026-10-16T15:55:31+00:00";}}}	1792252532
pos-restaurant-cache-idempotency:1:1:2935527f-17bd-433f-9590-b0faeb058cdc	a:3:{s:12:"request_hash";s:64:"11854912141ab4cb41737338aae95b9e8f9a3a133a3239f369789f94709c595b";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"ab321bf9-19aa-4689-9dbb-52706407d4a7";s:12:"order_number";s:21:"ORD-001-20261016-0007";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:9:{s:4:"uuid";s:36:"a90f7639-303b-4eca-b1e1-bd729ec4ea90";s:14:"menu_item_uuid";N;s:4:"name";s:19:"Caldillo de Congrio";s:10:"unit_price";i:8900;s:8:"quantity";i:1;s:8:"subtotal";i:8900;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-16T15:55:32+00:00";}}s:8:"subtotal";i:8900;s:10:"tax_amount";i:1421;s:15:"discount_amount";i:0;s:5:"total";i:8900;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T15:55:31+00:00";s:10:"updated_at";s:25:"2026-10-16T15:55:32+00:00";}}}	1792252532
pos-restaurant-cache-idempotency:1:1:a96634e8-4991-4622-bb0e-3ee527b7e093	a:3:{s:12:"request_hash";s:64:"1a35689811da48d85f39a5f60c0e36a087b80baf13ff2e7c50060c1825500087";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"ab321bf9-19aa-4689-9dbb-52706407d4a7";s:12:"order_number";s:21:"ORD-001-20261016-0007";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:9:"confirmed";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:8:{s:4:"uuid";s:36:"a90f7639-303b-4eca-b1e1-bd729ec4ea90";s:14:"menu_item_uuid";N;s:4:"name";s:19:"Caldillo de Congrio";s:10:"unit_price";i:8900;s:8:"quantity";i:1;s:8:"subtotal";i:8900;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-16T15:55:32+00:00";}}s:8:"subtotal";i:8900;s:10:"tax_amount";i:1421;s:15:"discount_amount";i:0;s:5:"total";i:8900;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-16T15:55:32+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T15:55:31+00:00";s:10:"updated_at";s:25:"2026-10-16T15:55:32+00:00";}}}	1792252532
pos-restaurant-cache-idempotency:1:1:34feb1c1-bf8c-467b-95c0-035c03f0d074	a:3:{s:12:"request_hash";s:64:"79e0b70ef4998b8aed3aca5adf2262c196366adefa0e1000e537e5cae654a48c";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"f9fb9996-b05e-458d-a737-c53a196f67c8";s:12:"order_number";s:21:"ORD-001-20261016-0008";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T16:21:26+00:00";s:10:"updated_at";s:25:"2026-10-16T16:21:26+00:00";}}}	1792254086
pos-restaurant-cache-idempotency:1:1:45608b7b-dfcd-4ecd-9e72-9f29ef152b50	a:3:{s:12:"request_hash";s:64:"d67e6b4ef756bf551a000f477299483ba9de9bc8ebc5a1832dad20c886e378b5";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"f9fb9996-b05e-458d-a737-c53a196f67c8";s:12:"order_number";s:21:"ORD-001-20261016-0008";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:9:{s:4:"uuid";s:36:"945fefa8-a514-43ef-9251-2e7fd2a74170";s:14:"menu_item_uuid";N;s:4:"name";s:14:"Ensalada Mixta";s:10:"unit_price";i:3200;s:8:"quantity";i:1;s:8:"subtotal";i:3200;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-16T16:21:27+00:00";}}s:8:"subtotal";i:3200;s:10:"tax_amount";i:511;s:15:"discount_amount";i:0;s:5:"total";i:3200;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T16:21:26+00:00";s:10:"updated_at";s:25:"2026-10-16T16:21:27+00:00";}}}	1792254087
pos-restaurant-cache-idempotency:1:1:4e47af0a-0e2d-48e3-98bd-3ba043100297	a:3:{s:12:"request_hash";s:64:"b016f0a6aef479317532922224a9f3cf26505ed6cc8c9e468800d4fa359d0c91";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"f9fb9996-b05e-458d-a737-c53a196f67c8";s:12:"order_number";s:21:"ORD-001-20261016-0008";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:9:"confirmed";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:8:{s:4:"uuid";s:36:"945fefa8-a514-43ef-9251-2e7fd2a74170";s:14:"menu_item_uuid";N;s:4:"name";s:14:"Ensalada Mixta";s:10:"unit_price";i:3200;s:8:"quantity";i:1;s:8:"subtotal";i:3200;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-16T16:21:27+00:00";}}s:8:"subtotal";i:3200;s:10:"tax_amount";i:511;s:15:"discount_amount";i:0;s:5:"total";i:3200;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-16T16:21:27+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T16:21:26+00:00";s:10:"updated_at";s:25:"2026-10-16T16:21:27+00:00";}}}	1792254087
pos-restaurant-cache-idempotency:1:1:03eb24c8-7d75-4fe0-beec-e5e2fb5bb567	a:3:{s:12:"request_hash";s:64:"b5e9194e09c0469a9c3bb906ddb371104e30f055a7b1fc563fc837ebe59c9398";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"76e29d27-9058-4622-a734-bfc3a044423b";s:12:"order_number";s:21:"ORD-001-20261016-0009";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T16:30:18+00:00";s:10:"updated_at";s:25:"2026-10-16T16:30:18+00:00";}}}	1792254618
pos-restaurant-cache-0DNakYHJdrgqkECo	a:1:{s:11:"valid_until";i:1792250747;}	1793453447
pos-restaurant-cache-sE90Gf4fupV8bmFS	a:1:{s:11:"valid_until";i:1792309941;}	1793453421
pos-restaurant-cache-HNBja2PnhBPZL7gd	a:1:{s:11:"valid_until";i:1792170222;}	1793365902
pos-restaurant-cache-tnmp5LYowZa3unZA	a:1:{s:11:"valid_until";i:1792306461;}	1793453421
pos-restaurant-cache-uEBM8wICdgRqVlN3	a:1:{s:11:"valid_until";i:1792426867;}	1793622547
pos-restaurant-cache-idempotency:1:1:b086aa93-06e2-4ac3-a6be-fe057dcf0bb7	a:3:{s:12:"request_hash";s:64:"4b6f5aed305ce06fcacee3469fac1ec06a585865aa8d0a388b12aa00774d2246";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"76e29d27-9058-4622-a734-bfc3a044423b";s:12:"order_number";s:21:"ORD-001-20261016-0009";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:9:{s:4:"uuid";s:36:"163e19cb-1bf3-408d-8d6d-0c98c360551c";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:1;s:8:"subtotal";i:7200;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-16T16:30:18+00:00";}}s:8:"subtotal";i:7200;s:10:"tax_amount";i:1150;s:15:"discount_amount";i:0;s:5:"total";i:7200;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T16:30:18+00:00";s:10:"updated_at";s:25:"2026-10-16T16:30:18+00:00";}}}	1792254618
pos-restaurant-cache-idempotency:1:1:fa5fd222-8dae-4417-b67f-994d5e38a054	a:3:{s:12:"request_hash";s:64:"82a65888e52f76ad28f874714b3899a6e58de0a7dea6ca89865e2b4c9bc8c21f";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"76e29d27-9058-4622-a734-bfc3a044423b";s:12:"order_number";s:21:"ORD-001-20261016-0009";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:2:{i:0;a:9:{s:4:"uuid";s:36:"dff551c5-f3b6-4f8a-afa7-3135757df903";s:14:"menu_item_uuid";N;s:4:"name";s:20:"Hamburguesa Clásica";s:10:"unit_price";i:5500;s:8:"quantity";i:1;s:8:"subtotal";i:5500;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-16T16:30:18+00:00";}i:1;a:9:{s:4:"uuid";s:36:"163e19cb-1bf3-408d-8d6d-0c98c360551c";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:1;s:8:"subtotal";i:7200;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-16T16:30:18+00:00";}}s:8:"subtotal";i:12700;s:10:"tax_amount";i:2028;s:15:"discount_amount";i:0;s:5:"total";i:12700;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T16:30:18+00:00";s:10:"updated_at";s:25:"2026-10-16T16:30:18+00:00";}}}	1792254618
pos-restaurant-cache-idempotency:1:1:faa93f9b-8294-4750-b94d-efb27f4c70f7	a:3:{s:12:"request_hash";s:64:"2a600af2c3e09cc8b492ac3e202533a927f1badb9d2c81763a47eb3dd7e72485";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"76e29d27-9058-4622-a734-bfc3a044423b";s:12:"order_number";s:21:"ORD-001-20261016-0009";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:9:"confirmed";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:2:{i:0;a:8:{s:4:"uuid";s:36:"dff551c5-f3b6-4f8a-afa7-3135757df903";s:14:"menu_item_uuid";N;s:4:"name";s:20:"Hamburguesa Clásica";s:10:"unit_price";i:5500;s:8:"quantity";i:1;s:8:"subtotal";i:5500;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-16T16:30:18+00:00";}i:1;a:8:{s:4:"uuid";s:36:"163e19cb-1bf3-408d-8d6d-0c98c360551c";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:1;s:8:"subtotal";i:7200;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-16T16:30:18+00:00";}}s:8:"subtotal";i:12700;s:10:"tax_amount";i:2028;s:15:"discount_amount";i:0;s:5:"total";i:12700;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-16T16:30:18+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-16T16:30:18+00:00";s:10:"updated_at";s:25:"2026-10-16T16:30:18+00:00";}}}	1792254618
pos-restaurant-cache-idempotency:1:1:ef07d0ea-d658-43da-ae54-9d8e91395c48	a:3:{s:12:"request_hash";s:64:"141f9dbb0f6cc1393fe556517b62c7366ab0e388fa5b022c57c5a93c79e2a234";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"953a0899-7047-41a4-a386-9032159c99a8";s:12:"order_number";s:21:"ORD-001-20261017-0006";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:9:{s:4:"uuid";s:36:"f3e1dfaf-9a9f-4ee0-9aee-acc73b9afe59";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:2;s:8:"subtotal";i:14400;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T15:50:37+00:00";}}s:8:"subtotal";i:14400;s:10:"tax_amount";i:2299;s:15:"discount_amount";i:0;s:5:"total";i:14400;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:50:36+00:00";s:10:"updated_at";s:25:"2026-10-17T15:50:37+00:00";}}}	1792338637
pos-restaurant-cache-05qZi8EJAfnve06U	a:1:{s:11:"valid_until";i:1792292540;}	1793453420
pos-restaurant-cache-idempotency:1:1:879cd9a4-cb4d-44e4-b071-37d31877facf	a:3:{s:12:"request_hash";s:64:"08b797d9020c696561afe1e4fbf140c93500308a110e6320d5a5895ab0c809b2";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:13:{s:4:"uuid";s:36:"c293b196-d412-4123-8185-55bca2a3866e";s:14:"session_number";s:18:"CS-20261017-2AED9D";s:6:"status";s:4:"open";s:14:"opening_amount";i:12000;s:14:"closing_amount";N;s:15:"expected_amount";N;s:10:"difference";N;s:13:"opening_notes";N;s:13:"closing_notes";N;s:9:"opened_at";s:25:"2026-10-17T14:00:02+00:00";s:9:"closed_at";N;s:4:"user";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:9:"breakdown";a:8:{s:10:"cash_sales";i:0;s:10:"card_sales";i:0;s:14:"transfer_sales";i:0;s:10:"total_tips";i:0;s:18:"cash_tips_received";i:0;s:12:"movements_in";i:0;s:13:"movements_out";i:0;s:21:"pending_sync_payments";i:0;}}}}	1792332002
pos-restaurant-cache-idempotency:1:1:3353d227-7b0b-450d-943f-69c6edffa5f3	a:3:{s:12:"request_hash";s:64:"fbf9508ddc90a4c4741fb9b1a57e0ed8beab02758e589acea416eb36f3f407c3";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:34:{s:4:"uuid";s:36:"8025f6bb-3add-42c7-a80f-20145b50f23f";s:12:"order_number";s:21:"ORD-001-20261017-0001";s:4:"type";s:7:"dine_in";s:19:"fulfillment_channel";s:6:"onsite";s:25:"fulfillment_channel_label";s:11:"En el local";s:10:"type_label";s:19:"Para servir en mesa";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:5:"table";a:3:{s:4:"uuid";s:36:"36c74c84-bb86-49a9-a2f1-fe8ab7d736b0";s:12:"table_number";s:4:"B-02";s:9:"area_code";s:3:"BAR";}s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T13:59:27+00:00";s:10:"updated_at";s:25:"2026-10-17T13:59:27+00:00";}}}	1792331967
pos-restaurant-cache-idempotency:1:1:03795567-261f-4790-b34a-566e38fddfb4	a:3:{s:12:"request_hash";s:64:"4d1a13e967a26fea9df10b83d9700dba7e867a539f0dca69d77f2e03d441c1f9";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:34:{s:4:"uuid";s:36:"8025f6bb-3add-42c7-a80f-20145b50f23f";s:12:"order_number";s:21:"ORD-001-20261017-0001";s:4:"type";s:7:"dine_in";s:19:"fulfillment_channel";s:6:"onsite";s:25:"fulfillment_channel_label";s:11:"En el local";s:10:"type_label";s:19:"Para servir en mesa";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:5:"table";a:3:{s:4:"uuid";s:36:"36c74c84-bb86-49a9-a2f1-fe8ab7d736b0";s:12:"table_number";s:4:"B-02";s:9:"area_code";s:3:"BAR";}s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:9:{s:4:"uuid";s:36:"5e1e8a83-632f-42e7-a96a-e39ac39cfe8f";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:1;s:8:"subtotal";i:7200;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T13:59:27+00:00";}}s:8:"subtotal";i:7200;s:10:"tax_amount";i:1150;s:15:"discount_amount";i:0;s:5:"total";i:7200;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T13:59:27+00:00";s:10:"updated_at";s:25:"2026-10-17T13:59:27+00:00";}}}	1792331967
pos-restaurant-cache-idempotency:1:1:3b7a277f-c158-44a3-8c11-d36b76096538	a:3:{s:12:"request_hash";s:64:"181410bf27cd7fe1e65e833cc7903e15ca7ffd8d8b4315ae3c65c5447ca002dd";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:34:{s:4:"uuid";s:36:"8025f6bb-3add-42c7-a80f-20145b50f23f";s:12:"order_number";s:21:"ORD-001-20261017-0001";s:4:"type";s:7:"dine_in";s:19:"fulfillment_channel";s:6:"onsite";s:25:"fulfillment_channel_label";s:11:"En el local";s:10:"type_label";s:19:"Para servir en mesa";s:6:"status";s:9:"confirmed";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:5:"table";a:3:{s:4:"uuid";s:36:"36c74c84-bb86-49a9-a2f1-fe8ab7d736b0";s:12:"table_number";s:4:"B-02";s:9:"area_code";s:3:"BAR";}s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:8:{s:4:"uuid";s:36:"5e1e8a83-632f-42e7-a96a-e39ac39cfe8f";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:1;s:8:"subtotal";i:7200;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-17T13:59:27+00:00";}}s:8:"subtotal";i:7200;s:10:"tax_amount";i:1150;s:15:"discount_amount";i:0;s:5:"total";i:7200;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-17T13:59:28+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T13:59:27+00:00";s:10:"updated_at";s:25:"2026-10-17T13:59:28+00:00";}}}	1792331968
pos-restaurant-cache-idempotency:1:1:79b90f15-a790-4b0a-9af3-d5e95f86639a	a:3:{s:12:"request_hash";s:64:"0f0bc08fa9aea51de853011d093161bb1e24e69f30ba86a44441d6e5c26368da";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"dcf06f08-aa7b-41ba-9f47-b55e6e940c17";s:12:"order_number";s:21:"ORD-001-20261017-0002";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:13:59+00:00";s:10:"updated_at";s:25:"2026-10-17T15:13:59+00:00";}}}	1792336439
pos-restaurant-cache-idempotency:1:1:d00acd46-ec4e-4f38-bc38-0c4518fbfa99	a:3:{s:12:"request_hash";s:64:"f78edf65efe5614f33683245143f4ee4f1205eb60b2f5d1423b7e7ea36d881cf";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"dcf06f08-aa7b-41ba-9f47-b55e6e940c17";s:12:"order_number";s:21:"ORD-001-20261017-0002";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:9:{s:4:"uuid";s:36:"ebc3a133-dc9b-4cb1-a132-94d008144f56";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:1;s:8:"subtotal";i:7200;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T15:13:59+00:00";}}s:8:"subtotal";i:7200;s:10:"tax_amount";i:1150;s:15:"discount_amount";i:0;s:5:"total";i:7200;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:13:59+00:00";s:10:"updated_at";s:25:"2026-10-17T15:13:59+00:00";}}}	1792336439
pos-restaurant-cache-UfWqEF7Tp24EluPO	a:1:{s:11:"valid_until";i:1792257737;}	1793453417
pos-restaurant-cache-1LHb5ZI1NQ2mJg5Z	a:1:{s:11:"valid_until";i:1792268178;}	1793453418
pos-restaurant-cache-idempotency:1:1:4a54d990-3979-47b0-9095-de3cc017671d	a:3:{s:12:"request_hash";s:64:"09b518cb33982a519de35bb4d1793a003ab0329604f58a1c77d088d1730c5c7e";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"dcf06f08-aa7b-41ba-9f47-b55e6e940c17";s:12:"order_number";s:21:"ORD-001-20261017-0002";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:2:{i:0;a:9:{s:4:"uuid";s:36:"bd9fabef-a976-4d0d-b726-98930f19eb71";s:14:"menu_item_uuid";N;s:4:"name";s:20:"Hamburguesa Clásica";s:10:"unit_price";i:5500;s:8:"quantity";i:1;s:8:"subtotal";i:5500;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T15:13:59+00:00";}i:1;a:9:{s:4:"uuid";s:36:"ebc3a133-dc9b-4cb1-a132-94d008144f56";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:1;s:8:"subtotal";i:7200;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T15:13:59+00:00";}}s:8:"subtotal";i:12700;s:10:"tax_amount";i:2028;s:15:"discount_amount";i:0;s:5:"total";i:12700;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:13:59+00:00";s:10:"updated_at";s:25:"2026-10-17T15:13:59+00:00";}}}	1792336439
pos-restaurant-cache-idempotency:1:1:e5337cd5-c90f-41b5-943f-060648b88977	a:3:{s:12:"request_hash";s:64:"b00d3b1d9a9d3f7d43c9cd7c4996ba05861b30748523f3539edcdf4ee3189708";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"dcf06f08-aa7b-41ba-9f47-b55e6e940c17";s:12:"order_number";s:21:"ORD-001-20261017-0002";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:3:{i:0;a:9:{s:4:"uuid";s:36:"bd9fabef-a976-4d0d-b726-98930f19eb71";s:14:"menu_item_uuid";N;s:4:"name";s:20:"Hamburguesa Clásica";s:10:"unit_price";i:5500;s:8:"quantity";i:1;s:8:"subtotal";i:5500;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T15:13:59+00:00";}i:1;a:9:{s:4:"uuid";s:36:"ebc3a133-dc9b-4cb1-a132-94d008144f56";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:1;s:8:"subtotal";i:7200;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T15:13:59+00:00";}i:2;a:9:{s:4:"uuid";s:36:"9bd6e96b-6b51-40cc-9b90-51f271d45e7f";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T15:13:59+00:00";}}s:8:"subtotal";i:13700;s:10:"tax_amount";i:2187;s:15:"discount_amount";i:0;s:5:"total";i:13700;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:13:59+00:00";s:10:"updated_at";s:25:"2026-10-17T15:13:59+00:00";}}}	1792336439
pos-restaurant-cache-idempotency:1:1:ed9530b5-825b-436e-bc25-a0525fa2e28f	a:3:{s:12:"request_hash";s:64:"641f73123f09ff6fcf11252f165ebd2a7726eb66c091f2ad35b0e7588f23b257";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"dcf06f08-aa7b-41ba-9f47-b55e6e940c17";s:12:"order_number";s:21:"ORD-001-20261017-0002";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:9:"confirmed";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:3:{i:0;a:8:{s:4:"uuid";s:36:"bd9fabef-a976-4d0d-b726-98930f19eb71";s:14:"menu_item_uuid";N;s:4:"name";s:20:"Hamburguesa Clásica";s:10:"unit_price";i:5500;s:8:"quantity";i:1;s:8:"subtotal";i:5500;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-17T15:13:59+00:00";}i:1;a:8:{s:4:"uuid";s:36:"ebc3a133-dc9b-4cb1-a132-94d008144f56";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:1;s:8:"subtotal";i:7200;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-17T15:13:59+00:00";}i:2;a:8:{s:4:"uuid";s:36:"9bd6e96b-6b51-40cc-9b90-51f271d45e7f";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-17T15:13:59+00:00";}}s:8:"subtotal";i:13700;s:10:"tax_amount";i:2187;s:15:"discount_amount";i:0;s:5:"total";i:13700;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-17T15:13:59+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:13:59+00:00";s:10:"updated_at";s:25:"2026-10-17T15:13:59+00:00";}}}	1792336440
pos-restaurant-cache-idempotency:1:1:aa26441e-cbc5-4db8-a852-b741155d394b	a:3:{s:12:"request_hash";s:64:"5dc64d05683abb8b178abccb31fa2e6466155059b095ec173f9a8ce6d8643933";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"6cfc9534-bba3-4171-a473-4c4bbb1682c2";s:12:"order_number";s:21:"ORD-001-20261017-0003";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:16:29+00:00";s:10:"updated_at";s:25:"2026-10-17T15:16:29+00:00";}}}	1792336589
pos-restaurant-cache-idempotency:1:1:7f26482d-cec7-4065-bdc6-ab011d128897	a:3:{s:12:"request_hash";s:64:"d8c635680437c83732af2aec6592a91be00918502063ae4106c60221a5661c53";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"6cfc9534-bba3-4171-a473-4c4bbb1682c2";s:12:"order_number";s:21:"ORD-001-20261017-0003";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:9:{s:4:"uuid";s:36:"7a6d63f5-dd9e-4c5a-bc9d-723096ee379f";s:14:"menu_item_uuid";N;s:4:"name";s:20:"Hamburguesa Clásica";s:10:"unit_price";i:5500;s:8:"quantity";i:1;s:8:"subtotal";i:5500;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T15:16:29+00:00";}}s:8:"subtotal";i:5500;s:10:"tax_amount";i:878;s:15:"discount_amount";i:0;s:5:"total";i:5500;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:16:29+00:00";s:10:"updated_at";s:25:"2026-10-17T15:16:29+00:00";}}}	1792336589
pos-restaurant-cache-idempotency:1:1:a9a9333a-9c51-4f0f-9083-8072683653aa	a:3:{s:12:"request_hash";s:64:"2db63702266d75d6f4c65be3dca7fe04497f3e30d494039326caffd421c59392";s:13:"response_code";i:201;s:13:"response_body";a:12:{s:4:"uuid";s:36:"46b4085a-7fab-458c-8e18-52adaf7023de";s:2:"id";i:3;s:11:"bill_number";s:23:"ORD-001-20261016-0002-1";s:4:"type";s:6:"single";s:8:"subtotal";i:3500;s:10:"tax_amount";i:559;s:5:"total";i:3500;s:11:"paid_amount";i:0;s:16:"remaining_amount";i:3500;s:6:"status";s:4:"open";s:10:"idempotent";b:0;s:7:"message";s:49:"Bill creada con montos calculados por el servidor";}}	1792521665
pos-restaurant-cache-vNW21uI69i5DACSl	a:1:{s:11:"valid_until";i:1792275139;}	1793453419
pos-restaurant-cache-idempotency:1:1:527753e7-6323-4037-bb52-e99bd168e121	a:3:{s:12:"request_hash";s:64:"c08f90ebd0068bce01ae0057f15e4f841c9000bafc1dd9df8eed472c4fa0d723";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"6cfc9534-bba3-4171-a473-4c4bbb1682c2";s:12:"order_number";s:21:"ORD-001-20261017-0003";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:2:{i:0;a:9:{s:4:"uuid";s:36:"7a6d63f5-dd9e-4c5a-bc9d-723096ee379f";s:14:"menu_item_uuid";N;s:4:"name";s:20:"Hamburguesa Clásica";s:10:"unit_price";i:5500;s:8:"quantity";i:1;s:8:"subtotal";i:5500;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T15:16:29+00:00";}i:1;a:9:{s:4:"uuid";s:36:"86e26acc-ff1d-4a47-b8dd-423396206784";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T15:16:29+00:00";}}s:8:"subtotal";i:6500;s:10:"tax_amount";i:1038;s:15:"discount_amount";i:0;s:5:"total";i:6500;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:16:29+00:00";s:10:"updated_at";s:25:"2026-10-17T15:16:29+00:00";}}}	1792336589
pos-restaurant-cache-idempotency:1:1:6da9102e-ce1d-4931-b4c1-b7f33b49d6a4	a:3:{s:12:"request_hash";s:64:"e59c7229adcdf8b54a7988d1b4ee51a28860cefb7de4669db62395b4bd6be936";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"6cfc9534-bba3-4171-a473-4c4bbb1682c2";s:12:"order_number";s:21:"ORD-001-20261017-0003";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:9:"confirmed";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:2:{i:0;a:8:{s:4:"uuid";s:36:"7a6d63f5-dd9e-4c5a-bc9d-723096ee379f";s:14:"menu_item_uuid";N;s:4:"name";s:20:"Hamburguesa Clásica";s:10:"unit_price";i:5500;s:8:"quantity";i:1;s:8:"subtotal";i:5500;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-17T15:16:29+00:00";}i:1;a:8:{s:4:"uuid";s:36:"86e26acc-ff1d-4a47-b8dd-423396206784";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-17T15:16:29+00:00";}}s:8:"subtotal";i:6500;s:10:"tax_amount";i:1038;s:15:"discount_amount";i:0;s:5:"total";i:6500;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-17T15:16:29+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:16:29+00:00";s:10:"updated_at";s:25:"2026-10-17T15:16:29+00:00";}}}	1792336589
pos-restaurant-cache-idempotency:1:1:f10bbb38-db02-4153-b607-674757c3aa5d	a:3:{s:12:"request_hash";s:64:"6803a468926d3a72ef9ebb0cd45713afb1851a38f244efef87858c2308120f6d";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"953a0899-7047-41a4-a386-9032159c99a8";s:12:"order_number";s:21:"ORD-001-20261017-0006";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:9:"confirmed";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:8:{s:4:"uuid";s:36:"f3e1dfaf-9a9f-4ee0-9aee-acc73b9afe59";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:2;s:8:"subtotal";i:14400;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-17T15:50:37+00:00";}}s:8:"subtotal";i:14400;s:10:"tax_amount";i:2299;s:15:"discount_amount";i:0;s:5:"total";i:14400;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-17T15:50:37+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:50:36+00:00";s:10:"updated_at";s:25:"2026-10-17T15:50:37+00:00";}}}	1792338637
pos-restaurant-cache-idempotency:1:1:0dbe1fe4-2b40-42c3-a39b-07f36d2c6705	a:3:{s:12:"request_hash";s:64:"bbbb0999192af02a941910df6e60acb08d9d00707f51d210d8610f2a72e71f71";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"6c66e010-b93b-4c1d-8f21-b3d0f5e9babe";s:12:"order_number";s:21:"ORD-001-20261017-0007";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:54:51+00:00";s:10:"updated_at";s:25:"2026-10-17T15:54:51+00:00";}}}	1792338891
pos-restaurant-cache-XsKJovZykUsEpJiR	a:1:{s:11:"valid_until";i:1792254257;}	1793453417
pos-restaurant-cache-IpqhQWSIdl5gQbmn	a:1:{s:11:"valid_until";i:1792296020;}	1793453420
pos-restaurant-cache-G8SxBN6L2ZRqwItL	a:1:{s:11:"valid_until";i:1792419891;}	1793622591
pos-restaurant-cache-qKlxF4QTztiV2O4S	a:1:{s:11:"valid_until";i:1792320382;}	1793453422
pos-restaurant-cache-idempotency:1:1:32e1b173-4f22-4bde-b538-2045c55cb1c6	a:3:{s:12:"request_hash";s:64:"9214b499c1df41bd2e81c3b4fad6758ee5875e05ca1e411dc7247329167b7ef8";s:13:"response_code";i:201;s:13:"response_body";a:12:{s:4:"uuid";s:36:"522b5b55-93c9-49cd-adda-98846aa007ec";s:2:"id";i:1;s:11:"bill_number";s:23:"ORD-001-20261017-0001-1";s:4:"type";s:6:"single";s:8:"subtotal";i:7200;s:10:"tax_amount";i:1150;s:5:"total";i:7200;s:11:"paid_amount";i:0;s:16:"remaining_amount";i:7200;s:6:"status";s:4:"open";s:10:"idempotent";b:0;s:7:"message";s:49:"Bill creada con montos calculados por el servidor";}}	1792519669
pos-restaurant-cache-idempotency:1:1:e196c245-fb61-4d46-bc23-d7b06ce96dc7	a:3:{s:12:"request_hash";s:64:"3d129a6d59e225788ce5e6101fa163e5eb1ae7129db9327bcc06163c449fd68d";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"84e19ad2-e4f6-4bd9-86a4-76d8188cf7ab";s:12:"order_number";s:21:"ORD-001-20261017-0004";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:17:29+00:00";s:10:"updated_at";s:25:"2026-10-17T15:17:29+00:00";}}}	1792336649
pos-restaurant-cache-idempotency:1:1:d6558c61-e1c5-4fc7-b100-eecc1de43049	a:3:{s:12:"request_hash";s:64:"2641514945c2d3cd2553c807df1f7ad53cb040280fcac563040a43249f3e29a2";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"84e19ad2-e4f6-4bd9-86a4-76d8188cf7ab";s:12:"order_number";s:21:"ORD-001-20261017-0004";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:9:{s:4:"uuid";s:36:"9e4d8f75-19ce-4711-bdb0-6dead292d400";s:14:"menu_item_uuid";N;s:4:"name";s:20:"Hamburguesa Clásica";s:10:"unit_price";i:5500;s:8:"quantity";i:1;s:8:"subtotal";i:5500;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T15:17:29+00:00";}}s:8:"subtotal";i:5500;s:10:"tax_amount";i:878;s:15:"discount_amount";i:0;s:5:"total";i:5500;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:17:29+00:00";s:10:"updated_at";s:25:"2026-10-17T15:17:29+00:00";}}}	1792336649
pos-restaurant-cache-idempotency:1:1:4d65f9b2-7e87-40f5-849d-4696166380fa	a:3:{s:12:"request_hash";s:64:"92d453ec7ac30663ca7b2f9f4c8f072924df1a4851427fecc41efa542e3bba0f";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"84e19ad2-e4f6-4bd9-86a4-76d8188cf7ab";s:12:"order_number";s:21:"ORD-001-20261017-0004";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:9:"confirmed";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:8:{s:4:"uuid";s:36:"9e4d8f75-19ce-4711-bdb0-6dead292d400";s:14:"menu_item_uuid";N;s:4:"name";s:20:"Hamburguesa Clásica";s:10:"unit_price";i:5500;s:8:"quantity";i:1;s:8:"subtotal";i:5500;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-17T15:17:29+00:00";}}s:8:"subtotal";i:5500;s:10:"tax_amount";i:878;s:15:"discount_amount";i:0;s:5:"total";i:5500;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-17T15:17:29+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:17:29+00:00";s:10:"updated_at";s:25:"2026-10-17T15:17:29+00:00";}}}	1792336649
pos-restaurant-cache-0DRqs1HLfDtsqPif	a:1:{s:11:"valid_until";i:1792313421;}	1793453421
pos-restaurant-cache-idempotency:1:1:84590da1-893a-4135-ac4d-fa6ced136230	a:3:{s:12:"request_hash";s:64:"778d53190da28084e5d119877a4510970817259ba018f2d15bf5fdf3692ba196";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"6c66e010-b93b-4c1d-8f21-b3d0f5e9babe";s:12:"order_number";s:21:"ORD-001-20261017-0007";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:9:{s:4:"uuid";s:36:"b5bd218a-4b78-4bab-84ea-f50208f8370e";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:1;s:8:"subtotal";i:7200;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T15:54:51+00:00";}}s:8:"subtotal";i:7200;s:10:"tax_amount";i:1150;s:15:"discount_amount";i:0;s:5:"total";i:7200;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:54:51+00:00";s:10:"updated_at";s:25:"2026-10-17T15:54:51+00:00";}}}	1792338891
pos-restaurant-cache-vx8diGcxYPAFNzAY	a:1:{s:11:"valid_until";i:1792278619;}	1793453419
pos-restaurant-cache-rAKHMvT0OxLsf2Ck	a:1:{s:11:"valid_until";i:1792261217;}	1793453417
pos-restaurant-cache-idempotency:1:1:48e792b4-5891-4379-ac80-525db60e79d3	a:3:{s:12:"request_hash";s:64:"e5d80a3f42013d78c577e296a19c4fe023fc51406f831ac2d7101e5bce40d9eb";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"246376f8-6d91-4963-90ee-d7c6c15603cf";s:12:"order_number";s:21:"ORD-001-20261019-0001";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";s:10:"updated_at";s:25:"2026-10-19T12:35:05+00:00";}}}	1792499705
pos-restaurant-cache-idempotency:1:1:ffefa7aa-bf5d-4d85-8ef7-5c1b9010a000	a:3:{s:12:"request_hash";s:64:"4d72d20b0ca9d76a4dec2fe93ca99bcaf2b64f98119bccbdef28c710745c7c2d";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"15129255-33d6-4d66-b7fd-a266bbe1f860";s:12:"order_number";s:21:"ORD-001-20261017-0005";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:19:14+00:00";s:10:"updated_at";s:25:"2026-10-17T15:19:14+00:00";}}}	1792336754
pos-restaurant-cache-idempotency:1:1:01fcf3cc-94c9-49bd-9611-00525f768f3e	a:3:{s:12:"request_hash";s:64:"0b92121758780fee3bad3d7285c07cc28e281deaae58a374ca744d294704c37b";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"15129255-33d6-4d66-b7fd-a266bbe1f860";s:12:"order_number";s:21:"ORD-001-20261017-0005";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:9:{s:4:"uuid";s:36:"32e67eae-8807-45e6-a857-ca70347e325b";s:14:"menu_item_uuid";N;s:4:"name";s:20:"Hamburguesa Clásica";s:10:"unit_price";i:5500;s:8:"quantity";i:1;s:8:"subtotal";i:5500;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T15:19:14+00:00";}}s:8:"subtotal";i:5500;s:10:"tax_amount";i:878;s:15:"discount_amount";i:0;s:5:"total";i:5500;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:19:14+00:00";s:10:"updated_at";s:25:"2026-10-17T15:19:14+00:00";}}}	1792336754
pos-restaurant-cache-idempotency:1:1:8fc57df6-1d1a-4080-877d-aa633e6409b1	a:3:{s:12:"request_hash";s:64:"4d5924cbdc9df345dbcfdc6412c0859026d8fbdd7eab985b6559bce14d238c9e";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"15129255-33d6-4d66-b7fd-a266bbe1f860";s:12:"order_number";s:21:"ORD-001-20261017-0005";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:2:{i:0;a:9:{s:4:"uuid";s:36:"32e67eae-8807-45e6-a857-ca70347e325b";s:14:"menu_item_uuid";N;s:4:"name";s:20:"Hamburguesa Clásica";s:10:"unit_price";i:5500;s:8:"quantity";i:1;s:8:"subtotal";i:5500;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T15:19:14+00:00";}i:1;a:9:{s:4:"uuid";s:36:"e876192a-2dac-402b-85e2-1458b7fe1cde";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:1;s:8:"subtotal";i:7200;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T15:19:14+00:00";}}s:8:"subtotal";i:12700;s:10:"tax_amount";i:2028;s:15:"discount_amount";i:0;s:5:"total";i:12700;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:19:14+00:00";s:10:"updated_at";s:25:"2026-10-17T15:19:14+00:00";}}}	1792336754
pos-restaurant-cache-idempotency:1:1:16b8f2d6-6bfe-4805-9f56-937998143e17	a:3:{s:12:"request_hash";s:64:"0921e3f1a91be675068859b8d8ce009115a5784b1c7e08e99d9c950dbb5d8424";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"15129255-33d6-4d66-b7fd-a266bbe1f860";s:12:"order_number";s:21:"ORD-001-20261017-0005";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:9:"confirmed";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:2:{i:0;a:8:{s:4:"uuid";s:36:"32e67eae-8807-45e6-a857-ca70347e325b";s:14:"menu_item_uuid";N;s:4:"name";s:20:"Hamburguesa Clásica";s:10:"unit_price";i:5500;s:8:"quantity";i:1;s:8:"subtotal";i:5500;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-17T15:19:14+00:00";}i:1;a:8:{s:4:"uuid";s:36:"e876192a-2dac-402b-85e2-1458b7fe1cde";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:1;s:8:"subtotal";i:7200;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-17T15:19:14+00:00";}}s:8:"subtotal";i:12700;s:10:"tax_amount";i:2028;s:15:"discount_amount";i:0;s:5:"total";i:12700;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-17T15:19:14+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:19:14+00:00";s:10:"updated_at";s:25:"2026-10-17T15:19:14+00:00";}}}	1792336755
pos-restaurant-cache-idempotency:1:1:53474767-6aa0-4950-9b3f-76d232bfca63	a:3:{s:12:"request_hash";s:64:"fcf1a17c9e794fb30c479063d72692c32261559d4a32af8220cd40f0aea427ce";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:34:{s:4:"uuid";s:36:"c08fab23-31b1-433a-993c-aa8348c6fe63";s:12:"order_number";s:21:"ORD-001-20261019-0002";s:4:"type";s:7:"dine_in";s:19:"fulfillment_channel";s:6:"onsite";s:25:"fulfillment_channel_label";s:11:"En el local";s:10:"type_label";s:19:"Para servir en mesa";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:5:"table";a:3:{s:4:"uuid";s:36:"4ce6978a-4c19-4f52-bce0-d2e45b92d0c1";s:12:"table_number";s:4:"B-03";s:9:"area_code";s:3:"BAR";}s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T12:36:05+00:00";s:10:"updated_at";s:25:"2026-10-19T12:36:05+00:00";}}}	1792499765
pos-restaurant-cache-NYolCzk14KysgwYk	a:1:{s:11:"valid_until";i:1792430358;}	1793622558
pos-restaurant-cache-idempotency:1:1:8e71aab3-4b14-42cb-b967-04c1580cba5a	a:3:{s:12:"request_hash";s:64:"2c5194078af0e416e9b0fee44ebdec94df2ded96323331136deb802350176173";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"6c66e010-b93b-4c1d-8f21-b3d0f5e9babe";s:12:"order_number";s:21:"ORD-001-20261017-0007";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:9:"confirmed";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:8:{s:4:"uuid";s:36:"b5bd218a-4b78-4bab-84ea-f50208f8370e";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:1;s:8:"subtotal";i:7200;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-17T15:54:51+00:00";}}s:8:"subtotal";i:7200;s:10:"tax_amount";i:1150;s:15:"discount_amount";i:0;s:5:"total";i:7200;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-17T15:54:52+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T15:54:51+00:00";s:10:"updated_at";s:25:"2026-10-17T15:54:52+00:00";}}}	1792338892
pos-restaurant-cache-idempotency:1:1:e20c71bc-3536-4fa2-ae62-76861663b962	a:3:{s:12:"request_hash";s:64:"3369fdc0965eb223f17f7094ee8ad28ee9df89e97dac3a5fdb1def178d2eba0d";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:34:{s:4:"uuid";s:36:"c08fab23-31b1-433a-993c-aa8348c6fe63";s:12:"order_number";s:21:"ORD-001-20261019-0002";s:4:"type";s:7:"dine_in";s:19:"fulfillment_channel";s:6:"onsite";s:25:"fulfillment_channel_label";s:11:"En el local";s:10:"type_label";s:19:"Para servir en mesa";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:5:"table";a:3:{s:4:"uuid";s:36:"4ce6978a-4c19-4f52-bce0-d2e45b92d0c1";s:12:"table_number";s:4:"B-03";s:9:"area_code";s:3:"BAR";}s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:9:{s:4:"uuid";s:36:"34384e04-f4dc-4442-ad98-32a927689982";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:36:05+00:00";}}s:8:"subtotal";i:1000;s:10:"tax_amount";i:160;s:15:"discount_amount";i:0;s:5:"total";i:1000;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T12:36:05+00:00";s:10:"updated_at";s:25:"2026-10-19T12:36:05+00:00";}}}	1792499765
pos-restaurant-cache-pz3eZRWPoiBMTPug	a:1:{s:11:"valid_until";i:1792299500;}	1793453420
pos-restaurant-cache-CjDytPe72lBjj5cT	a:1:{s:11:"valid_until";i:1792323862;}	1793453422
pos-restaurant-cache-idempotency:1:1:c4bfbe58-500a-4b23-9bab-f5124e1af5e9	a:3:{s:12:"request_hash";s:64:"78eee85726ff05d38ac5df42a745c346a4a687a44c1808a080e671284b5e17e1";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"246376f8-6d91-4963-90ee-d7c6c15603cf";s:12:"order_number";s:21:"ORD-001-20261019-0001";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:9:{s:4:"uuid";s:36:"8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";}}s:8:"subtotal";i:1000;s:10:"tax_amount";i:160;s:15:"discount_amount";i:0;s:5:"total";i:1000;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";s:10:"updated_at";s:25:"2026-10-19T12:35:05+00:00";}}}	1792499705
pos-restaurant-cache-idempotency:1:1:cab9a27c-6d8e-4454-a823-9997704c9914	a:3:{s:12:"request_hash";s:64:"bfbdb3b579765f2fcde6fd9fa3bfa06a63f15f0b364d17ede39ccac01583b91a";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"246376f8-6d91-4963-90ee-d7c6c15603cf";s:12:"order_number";s:21:"ORD-001-20261019-0001";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:3:{i:0;a:9:{s:4:"uuid";s:36:"57940fcf-0400-4ddb-a1ec-8dce90a98bb2";s:14:"menu_item_uuid";N;s:4:"name";s:19:"Caldillo de Congrio";s:10:"unit_price";i:8900;s:8:"quantity";i:1;s:8:"subtotal";i:8900;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}i:1;a:9:{s:4:"uuid";s:36:"b52f7738-a75c-40a2-b6c4-598eaa6947cf";s:14:"menu_item_uuid";N;s:4:"name";s:15:"Aros de Cebolla";s:10:"unit_price";i:2800;s:8:"quantity";i:1;s:8:"subtotal";i:2800;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";}i:2;a:9:{s:4:"uuid";s:36:"8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";}}s:8:"subtotal";i:12700;s:10:"tax_amount";i:2028;s:15:"discount_amount";i:0;s:5:"total";i:12700;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";s:10:"updated_at";s:25:"2026-10-19T12:35:06+00:00";}}}	1792499706
pos-restaurant-cache-idempotency:1:1:5866360a-bfd1-475f-976e-e5f1285620bb	a:3:{s:12:"request_hash";s:64:"86d7d1478232810fd9b10390aced8bc1e80a7eeb502be17ba67330c22ef00d48";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"246376f8-6d91-4963-90ee-d7c6c15603cf";s:12:"order_number";s:21:"ORD-001-20261019-0001";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:5:{i:0;a:9:{s:4:"uuid";s:36:"57940fcf-0400-4ddb-a1ec-8dce90a98bb2";s:14:"menu_item_uuid";N;s:4:"name";s:19:"Caldillo de Congrio";s:10:"unit_price";i:8900;s:8:"quantity";i:1;s:8:"subtotal";i:8900;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}i:1;a:9:{s:4:"uuid";s:36:"3768949b-51a3-4fab-a948-7d55c10be99b";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:1;s:8:"subtotal";i:7200;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}i:2;a:9:{s:4:"uuid";s:36:"b52f7738-a75c-40a2-b6c4-598eaa6947cf";s:14:"menu_item_uuid";N;s:4:"name";s:15:"Aros de Cebolla";s:10:"unit_price";i:2800;s:8:"quantity";i:1;s:8:"subtotal";i:2800;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";}i:3;a:9:{s:4:"uuid";s:36:"8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";}i:4;a:9:{s:4:"uuid";s:36:"7b8fabec-b9c4-408f-9b24-651c178e8f9d";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Cerveza Artesanal";s:10:"unit_price";i:3800;s:8:"quantity";i:1;s:8:"subtotal";i:3800;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}}s:8:"subtotal";i:23700;s:10:"tax_amount";i:3784;s:15:"discount_amount";i:0;s:5:"total";i:23700;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";s:10:"updated_at";s:25:"2026-10-19T12:35:06+00:00";}}}	1792499706
pos-restaurant-cache-DPIr3UW6tbzJkuk7	a:1:{s:11:"valid_until";i:1792430358;}	1793622558
pos-restaurant-cache-idempotency:1:1:5f7a236e-df0d-4d04-ba79-b8761569c060	a:3:{s:12:"request_hash";s:64:"3f01ae0f5ee2dda53d168070685be08fdfdb0d69d1aa80bbe428916612ef926b";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"5326a415-d42f-4d41-81cf-f4d6ca584a89";s:12:"order_number";s:21:"ORD-001-20261017-0008";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";s:10:"updated_at";s:25:"2026-10-17T16:51:33+00:00";}}}	1792342293
pos-restaurant-cache-idempotency:1:1:8e269dec-072a-4d02-a847-b4426dbec829	a:3:{s:12:"request_hash";s:64:"2d122b14040e2dd299e7f7de7b6f03a5f6baa5a6466b10f9d65ab91a8a85df20";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"5326a415-d42f-4d41-81cf-f4d6ca584a89";s:12:"order_number";s:21:"ORD-001-20261017-0008";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:9:{s:4:"uuid";s:36:"02143e39-cb2e-4737-8b60-8ad852ec5d2f";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";}}s:8:"subtotal";i:1000;s:10:"tax_amount";i:160;s:15:"discount_amount";i:0;s:5:"total";i:1000;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";s:10:"updated_at";s:25:"2026-10-17T16:51:33+00:00";}}}	1792342293
pos-restaurant-cache-idempotency:1:1:3b3a910b-9fa0-4b71-bc66-88a8049df045	a:3:{s:12:"request_hash";s:64:"de0198b75f702f81ecba049abcffa3100d357bd23c8082ceb94bc4ddcd266eb6";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"5326a415-d42f-4d41-81cf-f4d6ca584a89";s:12:"order_number";s:21:"ORD-001-20261017-0008";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:2:{i:0;a:9:{s:4:"uuid";s:36:"df963b27-9061-4dbc-b6bf-d4e2740a1703";s:14:"menu_item_uuid";N;s:4:"name";s:15:"Aros de Cebolla";s:10:"unit_price";i:2800;s:8:"quantity";i:1;s:8:"subtotal";i:2800;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";}i:1;a:9:{s:4:"uuid";s:36:"02143e39-cb2e-4737-8b60-8ad852ec5d2f";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";}}s:8:"subtotal";i:3800;s:10:"tax_amount";i:607;s:15:"discount_amount";i:0;s:5:"total";i:3800;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";s:10:"updated_at";s:25:"2026-10-17T16:51:33+00:00";}}}	1792342293
pos-restaurant-cache-idempotency:1:1:705d1e71-4f4f-4dda-8b58-3f4919ffe4af	a:3:{s:12:"request_hash";s:64:"d6a4ef6ac6c7c9ab9cf1565d55d2f7d61a09ec37a5c84ec5115eb6e70dca93e0";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"5326a415-d42f-4d41-81cf-f4d6ca584a89";s:12:"order_number";s:21:"ORD-001-20261017-0008";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:3:{i:0;a:9:{s:4:"uuid";s:36:"5b8c8563-6150-4831-a82f-8ca4bfe9deeb";s:14:"menu_item_uuid";N;s:4:"name";s:19:"Caldillo de Congrio";s:10:"unit_price";i:8900;s:8:"quantity";i:1;s:8:"subtotal";i:8900;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";}i:1;a:9:{s:4:"uuid";s:36:"df963b27-9061-4dbc-b6bf-d4e2740a1703";s:14:"menu_item_uuid";N;s:4:"name";s:15:"Aros de Cebolla";s:10:"unit_price";i:2800;s:8:"quantity";i:1;s:8:"subtotal";i:2800;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";}i:2;a:9:{s:4:"uuid";s:36:"02143e39-cb2e-4737-8b60-8ad852ec5d2f";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";}}s:8:"subtotal";i:12700;s:10:"tax_amount";i:2028;s:15:"discount_amount";i:0;s:5:"total";i:12700;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";s:10:"updated_at";s:25:"2026-10-17T16:51:33+00:00";}}}	1792342293
pos-restaurant-cache-idempotency:1:1:e66d1ffd-db6e-439b-91c5-4cf825acbe81	a:3:{s:12:"request_hash";s:64:"a33f71d05ae467d4eea902f56959cb5e7d27f450fd865983b56e2d2c07c3369f";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"5326a415-d42f-4d41-81cf-f4d6ca584a89";s:12:"order_number";s:21:"ORD-001-20261017-0008";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:4:{i:0;a:9:{s:4:"uuid";s:36:"5b8c8563-6150-4831-a82f-8ca4bfe9deeb";s:14:"menu_item_uuid";N;s:4:"name";s:19:"Caldillo de Congrio";s:10:"unit_price";i:8900;s:8:"quantity";i:1;s:8:"subtotal";i:8900;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";}i:1;a:9:{s:4:"uuid";s:36:"df963b27-9061-4dbc-b6bf-d4e2740a1703";s:14:"menu_item_uuid";N;s:4:"name";s:15:"Aros de Cebolla";s:10:"unit_price";i:2800;s:8:"quantity";i:1;s:8:"subtotal";i:2800;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";}i:2;a:9:{s:4:"uuid";s:36:"02143e39-cb2e-4737-8b60-8ad852ec5d2f";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";}i:3;a:9:{s:4:"uuid";s:36:"85ad35be-5de0-4dba-b0e6-1defbe673164";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Cerveza Artesanal";s:10:"unit_price";i:3800;s:8:"quantity";i:1;s:8:"subtotal";i:3800;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";}}s:8:"subtotal";i:16500;s:10:"tax_amount";i:2634;s:15:"discount_amount";i:0;s:5:"total";i:16500;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";s:10:"updated_at";s:25:"2026-10-17T16:51:33+00:00";}}}	1792342293
pos-restaurant-cache-kzsluddXlbXVzeEG	a:1:{s:11:"valid_until";i:1792423378;}	1793622598
pos-restaurant-cache-IpmzXidnnIiwQXl9	a:1:{s:11:"valid_until";i:1792282099;}	1793453419
pos-restaurant-cache-idempotency:1:1:bd532edf-67e1-4354-8702-6063f2a941c5	a:3:{s:12:"request_hash";s:64:"9cc99712be9a8be57ba05d34bdf28a77b2149a55afa9e51883bf5dd97c18a548";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"5326a415-d42f-4d41-81cf-f4d6ca584a89";s:12:"order_number";s:21:"ORD-001-20261017-0008";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:9:"confirmed";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:4:{i:0;a:8:{s:4:"uuid";s:36:"5b8c8563-6150-4831-a82f-8ca4bfe9deeb";s:14:"menu_item_uuid";N;s:4:"name";s:19:"Caldillo de Congrio";s:10:"unit_price";i:8900;s:8:"quantity";i:1;s:8:"subtotal";i:8900;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";}i:1;a:8:{s:4:"uuid";s:36:"df963b27-9061-4dbc-b6bf-d4e2740a1703";s:14:"menu_item_uuid";N;s:4:"name";s:15:"Aros de Cebolla";s:10:"unit_price";i:2800;s:8:"quantity";i:1;s:8:"subtotal";i:2800;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";}i:2;a:8:{s:4:"uuid";s:36:"02143e39-cb2e-4737-8b60-8ad852ec5d2f";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";}i:3;a:8:{s:4:"uuid";s:36:"85ad35be-5de0-4dba-b0e6-1defbe673164";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Cerveza Artesanal";s:10:"unit_price";i:3800;s:8:"quantity";i:1;s:8:"subtotal";i:3800;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";}}s:8:"subtotal";i:16500;s:10:"tax_amount";i:2634;s:15:"discount_amount";i:0;s:5:"total";i:16500;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-17T16:51:33+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-17T16:51:33+00:00";s:10:"updated_at";s:25:"2026-10-17T16:51:33+00:00";}}}	1792342293
pos-restaurant-cache-idempotency:1:1:2df5616e-bc01-421c-bfce-349085862511	a:3:{s:12:"request_hash";s:64:"9f112eb88ecb2cd2e50295945e0e48febeb0de56032843ee12548c056da11ba8";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:34:{s:4:"uuid";s:36:"c08fab23-31b1-433a-993c-aa8348c6fe63";s:12:"order_number";s:21:"ORD-001-20261019-0002";s:4:"type";s:7:"dine_in";s:19:"fulfillment_channel";s:6:"onsite";s:25:"fulfillment_channel_label";s:11:"En el local";s:10:"type_label";s:19:"Para servir en mesa";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:5:"table";a:3:{s:4:"uuid";s:36:"4ce6978a-4c19-4f52-bce0-d2e45b92d0c1";s:12:"table_number";s:4:"B-03";s:9:"area_code";s:3:"BAR";}s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:2:{i:0;a:9:{s:4:"uuid";s:36:"0b685d54-bcda-4848-84a4-343086387c1f";s:14:"menu_item_uuid";N;s:4:"name";s:15:"Coca-Cola 500ml";s:10:"unit_price";i:1500;s:8:"quantity";i:1;s:8:"subtotal";i:1500;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:36:05+00:00";}i:1;a:9:{s:4:"uuid";s:36:"34384e04-f4dc-4442-ad98-32a927689982";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:36:05+00:00";}}s:8:"subtotal";i:2500;s:10:"tax_amount";i:399;s:15:"discount_amount";i:0;s:5:"total";i:2500;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T12:36:05+00:00";s:10:"updated_at";s:25:"2026-10-19T12:36:05+00:00";}}}	1792499766
pos-restaurant-cache-jZScifOdYJ5yNrkS	a:1:{s:11:"valid_until";i:1792316902;}	1793453422
pos-restaurant-cache-idempotency:1:1:a04022a4-4348-4781-a135-608ce0a04f6f	a:3:{s:12:"request_hash";s:64:"e828d58b98ca5123922f1de57799924bf7271f3c4519c0ee3bd9903837ddef84";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"246376f8-6d91-4963-90ee-d7c6c15603cf";s:12:"order_number";s:21:"ORD-001-20261019-0001";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:2:{i:0;a:9:{s:4:"uuid";s:36:"b52f7738-a75c-40a2-b6c4-598eaa6947cf";s:14:"menu_item_uuid";N;s:4:"name";s:15:"Aros de Cebolla";s:10:"unit_price";i:2800;s:8:"quantity";i:1;s:8:"subtotal";i:2800;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";}i:1;a:9:{s:4:"uuid";s:36:"8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";}}s:8:"subtotal";i:3800;s:10:"tax_amount";i:607;s:15:"discount_amount";i:0;s:5:"total";i:3800;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";s:10:"updated_at";s:25:"2026-10-19T12:35:05+00:00";}}}	1792499705
pos-restaurant-cache-idempotency:1:1:ef1c3a7f-76a6-43c3-82d8-6ab68f718497	a:3:{s:12:"request_hash";s:64:"e7c17b87ee23f50791ec41eb8b488848eab4670db431e0c85e45689fde015e3a";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"246376f8-6d91-4963-90ee-d7c6c15603cf";s:12:"order_number";s:21:"ORD-001-20261019-0001";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:4:{i:0;a:9:{s:4:"uuid";s:36:"57940fcf-0400-4ddb-a1ec-8dce90a98bb2";s:14:"menu_item_uuid";N;s:4:"name";s:19:"Caldillo de Congrio";s:10:"unit_price";i:8900;s:8:"quantity";i:1;s:8:"subtotal";i:8900;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}i:1;a:9:{s:4:"uuid";s:36:"b52f7738-a75c-40a2-b6c4-598eaa6947cf";s:14:"menu_item_uuid";N;s:4:"name";s:15:"Aros de Cebolla";s:10:"unit_price";i:2800;s:8:"quantity";i:1;s:8:"subtotal";i:2800;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";}i:2;a:9:{s:4:"uuid";s:36:"8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";}i:3;a:9:{s:4:"uuid";s:36:"7b8fabec-b9c4-408f-9b24-651c178e8f9d";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Cerveza Artesanal";s:10:"unit_price";i:3800;s:8:"quantity";i:1;s:8:"subtotal";i:3800;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}}s:8:"subtotal";i:16500;s:10:"tax_amount";i:2634;s:15:"discount_amount";i:0;s:5:"total";i:16500;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";s:10:"updated_at";s:25:"2026-10-19T12:35:06+00:00";}}}	1792499706
pos-restaurant-cache-idempotency:1:1:7fb778f9-f48c-4138-a95b-ad3bb6782cdf	a:3:{s:12:"request_hash";s:64:"1a366cc7f55e3c6d208761c1ff6ca6681dea892f1e08c4349c1974723e0aa38a";s:13:"response_code";i:201;s:13:"response_body";a:12:{s:4:"uuid";s:36:"736047ba-c23a-4b37-bec2-676d5192f06f";s:2:"id";i:4;s:11:"bill_number";s:23:"ORD-001-20261016-0001-1";s:4:"type";s:6:"single";s:8:"subtotal";i:8900;s:10:"tax_amount";i:1421;s:5:"total";i:8900;s:11:"paid_amount";i:0;s:16:"remaining_amount";i:8900;s:6:"status";s:4:"open";s:10:"idempotent";b:0;s:7:"message";s:49:"Bill creada con montos calculados por el servidor";}}	1792523509
pos-restaurant-cache-idempotency:1:1:1d059462-6a69-4862-a699-7d30e2fb13e6	a:3:{s:12:"request_hash";s:64:"b4f23c143133aa18e26888a88f173481ab24c5332a379a9cf6a5443798453580";s:13:"response_code";i:201;s:13:"response_body";a:12:{s:4:"uuid";s:36:"cae81774-4a91-421e-a422-5fd75b524a69";s:2:"id";i:2;s:11:"bill_number";s:23:"ORD-001-20261019-0002-1";s:4:"type";s:6:"single";s:8:"subtotal";i:2500;s:10:"tax_amount";i:399;s:5:"total";i:2500;s:11:"paid_amount";i:0;s:16:"remaining_amount";i:2500;s:6:"status";s:4:"open";s:10:"idempotent";b:0;s:7:"message";s:49:"Bill creada con montos calculados por el servidor";}}	1792519924
pos-restaurant-cache-idempotency:1:1:e040c99b-d46d-4b36-89ba-c840a75a96b8	a:3:{s:12:"request_hash";s:64:"f543be9fb339484863e34c7471f71a4813f441452aa0f060b9a8353b23f6b21a";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"246376f8-6d91-4963-90ee-d7c6c15603cf";s:12:"order_number";s:21:"ORD-001-20261019-0001";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:6:{i:0;a:9:{s:4:"uuid";s:36:"57940fcf-0400-4ddb-a1ec-8dce90a98bb2";s:14:"menu_item_uuid";N;s:4:"name";s:19:"Caldillo de Congrio";s:10:"unit_price";i:8900;s:8:"quantity";i:1;s:8:"subtotal";i:8900;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}i:1;a:9:{s:4:"uuid";s:36:"2e16d908-2651-4250-99c1-ab0b016f6075";s:14:"menu_item_uuid";N;s:4:"name";s:20:"Hamburguesa Clásica";s:10:"unit_price";i:5500;s:8:"quantity";i:1;s:8:"subtotal";i:5500;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}i:2;a:9:{s:4:"uuid";s:36:"3768949b-51a3-4fab-a948-7d55c10be99b";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:1;s:8:"subtotal";i:7200;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}i:3;a:9:{s:4:"uuid";s:36:"b52f7738-a75c-40a2-b6c4-598eaa6947cf";s:14:"menu_item_uuid";N;s:4:"name";s:15:"Aros de Cebolla";s:10:"unit_price";i:2800;s:8:"quantity";i:1;s:8:"subtotal";i:2800;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";}i:4;a:9:{s:4:"uuid";s:36:"8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";}i:5;a:9:{s:4:"uuid";s:36:"7b8fabec-b9c4-408f-9b24-651c178e8f9d";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Cerveza Artesanal";s:10:"unit_price";i:3800;s:8:"quantity";i:1;s:8:"subtotal";i:3800;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}}s:8:"subtotal";i:29200;s:10:"tax_amount";i:4662;s:15:"discount_amount";i:0;s:5:"total";i:29200;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";s:10:"updated_at";s:25:"2026-10-19T12:35:06+00:00";}}}	1792499706
pos-restaurant-cache-idempotency:1:1:0f9001e5-154b-47ae-89fe-a9e75797d1a6	a:3:{s:12:"request_hash";s:64:"eb0f93426b6fa8bcf9d3615d5995b8d5a441b66c5c2f3579469ef1c2d0fa0ed3";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"246376f8-6d91-4963-90ee-d7c6c15603cf";s:12:"order_number";s:21:"ORD-001-20261019-0001";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:7:{i:0;a:9:{s:4:"uuid";s:36:"57940fcf-0400-4ddb-a1ec-8dce90a98bb2";s:14:"menu_item_uuid";N;s:4:"name";s:19:"Caldillo de Congrio";s:10:"unit_price";i:8900;s:8:"quantity";i:1;s:8:"subtotal";i:8900;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}i:1;a:9:{s:4:"uuid";s:36:"2e16d908-2651-4250-99c1-ab0b016f6075";s:14:"menu_item_uuid";N;s:4:"name";s:20:"Hamburguesa Clásica";s:10:"unit_price";i:5500;s:8:"quantity";i:1;s:8:"subtotal";i:5500;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}i:2;a:9:{s:4:"uuid";s:36:"3768949b-51a3-4fab-a948-7d55c10be99b";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:1;s:8:"subtotal";i:7200;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}i:3;a:9:{s:4:"uuid";s:36:"b52f7738-a75c-40a2-b6c4-598eaa6947cf";s:14:"menu_item_uuid";N;s:4:"name";s:15:"Aros de Cebolla";s:10:"unit_price";i:2800;s:8:"quantity";i:1;s:8:"subtotal";i:2800;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";}i:4;a:9:{s:4:"uuid";s:36:"92f5ac8f-940a-4485-8cc1-66d7dc7312e0";s:14:"menu_item_uuid";N;s:4:"name";s:14:"Ensalada Mixta";s:10:"unit_price";i:3200;s:8:"quantity";i:1;s:8:"subtotal";i:3200;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}i:5;a:9:{s:4:"uuid";s:36:"8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";}i:6;a:9:{s:4:"uuid";s:36:"7b8fabec-b9c4-408f-9b24-651c178e8f9d";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Cerveza Artesanal";s:10:"unit_price";i:3800;s:8:"quantity";i:1;s:8:"subtotal";i:3800;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}}s:8:"subtotal";i:32400;s:10:"tax_amount";i:5173;s:15:"discount_amount";i:0;s:5:"total";i:32400;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";s:10:"updated_at";s:25:"2026-10-19T12:35:07+00:00";}}}	1792499707
pos-restaurant-cache-idempotency:1:1:165a4f77-ff23-4a5e-9937-833cdeed944b	a:3:{s:12:"request_hash";s:64:"f639a7fdad3600f2571a172c3a7c92fd86a01b0ccc8a0c04509c9d32a0fa55f9";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"246376f8-6d91-4963-90ee-d7c6c15603cf";s:12:"order_number";s:21:"ORD-001-20261019-0001";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:8:{i:0;a:9:{s:4:"uuid";s:36:"1710bb9a-3340-40b6-b54d-35f117dd47e2";s:14:"menu_item_uuid";N;s:4:"name";s:23:"Empanadas de Pino (3un)";s:10:"unit_price";i:3500;s:8:"quantity";i:1;s:8:"subtotal";i:3500;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:07+00:00";}i:1;a:9:{s:4:"uuid";s:36:"57940fcf-0400-4ddb-a1ec-8dce90a98bb2";s:14:"menu_item_uuid";N;s:4:"name";s:19:"Caldillo de Congrio";s:10:"unit_price";i:8900;s:8:"quantity";i:1;s:8:"subtotal";i:8900;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}i:2;a:9:{s:4:"uuid";s:36:"2e16d908-2651-4250-99c1-ab0b016f6075";s:14:"menu_item_uuid";N;s:4:"name";s:20:"Hamburguesa Clásica";s:10:"unit_price";i:5500;s:8:"quantity";i:1;s:8:"subtotal";i:5500;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}i:3;a:9:{s:4:"uuid";s:36:"3768949b-51a3-4fab-a948-7d55c10be99b";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:1;s:8:"subtotal";i:7200;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}i:4;a:9:{s:4:"uuid";s:36:"b52f7738-a75c-40a2-b6c4-598eaa6947cf";s:14:"menu_item_uuid";N;s:4:"name";s:15:"Aros de Cebolla";s:10:"unit_price";i:2800;s:8:"quantity";i:1;s:8:"subtotal";i:2800;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";}i:5;a:9:{s:4:"uuid";s:36:"92f5ac8f-940a-4485-8cc1-66d7dc7312e0";s:14:"menu_item_uuid";N;s:4:"name";s:14:"Ensalada Mixta";s:10:"unit_price";i:3200;s:8:"quantity";i:1;s:8:"subtotal";i:3200;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}i:6;a:9:{s:4:"uuid";s:36:"8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";}i:7;a:9:{s:4:"uuid";s:36:"7b8fabec-b9c4-408f-9b24-651c178e8f9d";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Cerveza Artesanal";s:10:"unit_price";i:3800;s:8:"quantity";i:1;s:8:"subtotal";i:3800;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}}s:8:"subtotal";i:35900;s:10:"tax_amount";i:5732;s:15:"discount_amount";i:0;s:5:"total";i:35900;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";s:10:"updated_at";s:25:"2026-10-19T12:35:07+00:00";}}}	1792499707
pos-restaurant-cache-idempotency:1:1:06014285-4079-4ce7-b937-56052cdb1230	a:3:{s:12:"request_hash";s:64:"e442c954cfbd0c9c93ce0ae84f636dd8248430cb9974086d18bcb1aee33fe555";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"246376f8-6d91-4963-90ee-d7c6c15603cf";s:12:"order_number";s:21:"ORD-001-20261019-0001";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:9:"confirmed";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:8:{i:0;a:8:{s:4:"uuid";s:36:"1710bb9a-3340-40b6-b54d-35f117dd47e2";s:14:"menu_item_uuid";N;s:4:"name";s:23:"Empanadas de Pino (3un)";s:10:"unit_price";i:3500;s:8:"quantity";i:1;s:8:"subtotal";i:3500;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-19T12:35:07+00:00";}i:1;a:8:{s:4:"uuid";s:36:"57940fcf-0400-4ddb-a1ec-8dce90a98bb2";s:14:"menu_item_uuid";N;s:4:"name";s:19:"Caldillo de Congrio";s:10:"unit_price";i:8900;s:8:"quantity";i:1;s:8:"subtotal";i:8900;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}i:2;a:8:{s:4:"uuid";s:36:"2e16d908-2651-4250-99c1-ab0b016f6075";s:14:"menu_item_uuid";N;s:4:"name";s:20:"Hamburguesa Clásica";s:10:"unit_price";i:5500;s:8:"quantity";i:1;s:8:"subtotal";i:5500;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}i:3;a:8:{s:4:"uuid";s:36:"3768949b-51a3-4fab-a948-7d55c10be99b";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Hamburguesa Doble";s:10:"unit_price";i:7200;s:8:"quantity";i:1;s:8:"subtotal";i:7200;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}i:4;a:8:{s:4:"uuid";s:36:"b52f7738-a75c-40a2-b6c4-598eaa6947cf";s:14:"menu_item_uuid";N;s:4:"name";s:15:"Aros de Cebolla";s:10:"unit_price";i:2800;s:8:"quantity";i:1;s:8:"subtotal";i:2800;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";}i:5;a:8:{s:4:"uuid";s:36:"92f5ac8f-940a-4485-8cc1-66d7dc7312e0";s:14:"menu_item_uuid";N;s:4:"name";s:14:"Ensalada Mixta";s:10:"unit_price";i:3200;s:8:"quantity";i:1;s:8:"subtotal";i:3200;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}i:6;a:8:{s:4:"uuid";s:36:"8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";}i:7;a:8:{s:4:"uuid";s:36:"7b8fabec-b9c4-408f-9b24-651c178e8f9d";s:14:"menu_item_uuid";N;s:4:"name";s:17:"Cerveza Artesanal";s:10:"unit_price";i:3800;s:8:"quantity";i:1;s:8:"subtotal";i:3800;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-19T12:35:06+00:00";}}s:8:"subtotal";i:35900;s:10:"tax_amount";i:5732;s:15:"discount_amount";i:0;s:5:"total";i:35900;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-19T12:35:07+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T12:35:05+00:00";s:10:"updated_at";s:25:"2026-10-19T12:35:07+00:00";}}}	1792499707
pos-restaurant-cache-idempotency:1:1:c90473a1-3be7-488f-affb-32e0f6478d08	a:3:{s:12:"request_hash";s:64:"3b3b796f6154419994f711690aaad3734453c68fbf7fbde3e7a36feb639d2ed4";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"a90dc353-c147-46d2-abe7-3335439d33df";s:12:"order_number";s:21:"ORD-001-20261019-0003";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T18:40:19+00:00";s:10:"updated_at";s:25:"2026-10-19T18:40:19+00:00";}}}	1792521619
pos-restaurant-cache-idempotency:1:1:da249e50-a095-406c-8697-bf270fad56e6	a:3:{s:12:"request_hash";s:64:"a7b673d4fd31675cf2c4fbd5986fafb8a9c0cb76ab5d6d60f7ea700267019801";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:34:{s:4:"uuid";s:36:"c08fab23-31b1-433a-993c-aa8348c6fe63";s:12:"order_number";s:21:"ORD-001-20261019-0002";s:4:"type";s:7:"dine_in";s:19:"fulfillment_channel";s:6:"onsite";s:25:"fulfillment_channel_label";s:11:"En el local";s:10:"type_label";s:19:"Para servir en mesa";s:6:"status";s:9:"confirmed";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:5:"table";a:3:{s:4:"uuid";s:36:"4ce6978a-4c19-4f52-bce0-d2e45b92d0c1";s:12:"table_number";s:4:"B-03";s:9:"area_code";s:3:"BAR";}s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:2:{i:0;a:8:{s:4:"uuid";s:36:"0b685d54-bcda-4848-84a4-343086387c1f";s:14:"menu_item_uuid";N;s:4:"name";s:15:"Coca-Cola 500ml";s:10:"unit_price";i:1500;s:8:"quantity";i:1;s:8:"subtotal";i:1500;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-19T12:36:05+00:00";}i:1;a:8:{s:4:"uuid";s:36:"34384e04-f4dc-4442-ad98-32a927689982";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-19T12:36:05+00:00";}}s:8:"subtotal";i:2500;s:10:"tax_amount";i:399;s:15:"discount_amount";i:0;s:5:"total";i:2500;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-19T12:36:06+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T12:36:05+00:00";s:10:"updated_at";s:25:"2026-10-19T12:36:06+00:00";}}}	1792499766
pos-restaurant-cache-idempotency:1:1:e5b8f23a-15aa-4e7c-bcdc-fa7bb4cbc6bd	a:3:{s:12:"request_hash";s:64:"934ff6a0dbc90bea25a8fc3749c0a83294461f3bde59704dd512739b674cc0a2";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"d4e05f80-ad67-4784-8b88-fcbaab3c5675";s:12:"order_number";s:21:"ORD-001-20261019-0004";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T18:40:20+00:00";s:10:"updated_at";s:25:"2026-10-19T18:40:20+00:00";}}}	1792521620
pos-restaurant-cache-idempotency:1:1:7e5879de-fd7d-49e7-9ffe-322996842855	a:3:{s:12:"request_hash";s:64:"af9a605468616b995cf3934b1e03e9d5badeb487f883ad5bb68ab6acf3acb9ff";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"d1fc6d28-f7c3-44d4-8bed-120740597fd8";s:12:"order_number";s:21:"ORD-001-20261019-0005";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T18:40:20+00:00";s:10:"updated_at";s:25:"2026-10-19T18:40:20+00:00";}}}	1792521620
pos-restaurant-cache-rVIT9TQbfnVHOUZm	a:1:{s:11:"valid_until";i:1792437337;}	1793622577
pos-restaurant-cache-idempotency:1:1:51fa7bef-e771-4e04-8dbb-f3b0ef981b06	a:3:{s:12:"request_hash";s:64:"473faafbdf92234947ac917a827d3c52a9f0388c9489c014a38e1de9f1818bdd";s:13:"response_code";i:201;s:13:"response_body";a:12:{s:4:"uuid";s:36:"66295a36-1d41-4af2-a3fa-3a3b2c1f6234";s:2:"id";i:6;s:11:"bill_number";s:23:"ORD-001-20261019-0006-1";s:4:"type";s:6:"single";s:8:"subtotal";i:8900;s:10:"tax_amount";i:1421;s:5:"total";i:8900;s:11:"paid_amount";i:0;s:16:"remaining_amount";i:8900;s:6:"status";s:4:"open";s:10:"idempotent";b:0;s:7:"message";s:49:"Bill creada con montos calculados por el servidor";}}	1792529176
pos-restaurant-cache-company:1:capabilities	a:8:{i:0;s:15:"can_split_bills";i:1;s:18:"can_manage_recipes";i:2;s:24:"requires_cashier_session";i:3;s:15:"can_accept_tips";i:4;s:19:"has_kitchen_display";i:5;s:18:"can_print_receipts";i:6;s:24:"supports_loyalty_program";i:7;s:23:"can_manage_reservations";}	1792444179
pos-restaurant-cache-idempotency:1:1:03e6d938-b414-449a-9a79-7ab5e25138e2	a:3:{s:12:"request_hash";s:64:"cd7012f2440e1fc13c5f3d1ef283179fc10afb3bbb5d3bdba1f0f97aba3f06b1";s:13:"response_code";i:201;s:13:"response_body";a:12:{s:4:"uuid";s:36:"522b5b55-93c9-49cd-adda-98846aa007ec";s:2:"id";i:1;s:11:"bill_number";s:23:"ORD-001-20261017-0001-1";s:4:"type";s:6:"single";s:8:"subtotal";i:7200;s:10:"tax_amount";i:1150;s:5:"total";i:7200;s:11:"paid_amount";i:0;s:16:"remaining_amount";i:7200;s:6:"status";s:4:"open";s:10:"idempotent";b:0;s:7:"message";s:49:"Bill creada con montos calculados por el servidor";}}	1792525628
pos-restaurant-cache-idempotency:1:1:7e369704-0dc8-491a-a841-99ff59676593	a:3:{s:12:"request_hash";s:64:"5c42f561146300a1cd301cb100d15d54edc6c22711f1dc7c63b6d0215bbdf668";s:13:"response_code";i:201;s:13:"response_body";a:12:{s:4:"uuid";s:36:"ba52ffaa-7c10-42ab-8274-088f540d2b5d";s:2:"id";i:5;s:11:"bill_number";s:23:"ORD-001-20261019-0001-1";s:4:"type";s:6:"single";s:8:"subtotal";i:35900;s:10:"tax_amount";i:5732;s:5:"total";i:35900;s:11:"paid_amount";i:0;s:16:"remaining_amount";i:35900;s:6:"status";s:4:"open";s:10:"idempotent";b:0;s:7:"message";s:49:"Bill creada con montos calculados por el servidor";}}	1792525793
pos-restaurant-cache-idempotency:1:1:457153b4-40ec-4a89-88a9-c5447c7abb0c	a:3:{s:12:"request_hash";s:64:"2e809289f8a2eb61903352ab76b184ce61c0974afb35917d5a9933837f357214";s:13:"response_code";i:201;s:13:"response_body";a:12:{s:4:"uuid";s:36:"46b4085a-7fab-458c-8e18-52adaf7023de";s:2:"id";i:3;s:11:"bill_number";s:23:"ORD-001-20261016-0002-1";s:4:"type";s:6:"single";s:8:"subtotal";i:3500;s:10:"tax_amount";i:559;s:5:"total";i:3500;s:11:"paid_amount";i:0;s:16:"remaining_amount";i:3500;s:6:"status";s:4:"open";s:10:"idempotent";b:0;s:7:"message";s:49:"Bill creada con montos calculados por el servidor";}}	1792526333
pos-restaurant-cache-PjjaRRp1xIpOJvRo	a:1:{s:11:"valid_until";i:1792440817;}	1793622577
pos-restaurant-cache-idempotency:1:1:b9615ea7-f7ec-4ae5-b550-a3aec171c229	a:3:{s:12:"request_hash";s:64:"914d57bd6b74a2b2152b178ef476bbd0ed3ce3f0f0c5677a4322d0b95955fb86";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"3d37c1e5-79bb-481a-8dbc-1540c0ec805b";s:12:"order_number";s:21:"ORD-001-20261019-0006";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T20:45:31+00:00";s:10:"updated_at";s:25:"2026-10-19T20:45:31+00:00";}}}	1792529132
pos-restaurant-cache-idempotency:1:1:a4e06b17-0ec1-443c-a91e-9eb93eb36817	a:3:{s:12:"request_hash";s:64:"446d3041588cc4e860ef83237abd6118fb7e5893d4495bd519a981632f3ac781";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"3d37c1e5-79bb-481a-8dbc-1540c0ec805b";s:12:"order_number";s:21:"ORD-001-20261019-0006";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:9:{s:4:"uuid";s:36:"7ebd55c0-dd9d-412b-9da9-2928a05ba6dd";s:14:"menu_item_uuid";N;s:4:"name";s:19:"Caldillo de Congrio";s:10:"unit_price";i:8900;s:8:"quantity";i:1;s:8:"subtotal";i:8900;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T20:45:32+00:00";}}s:8:"subtotal";i:8900;s:10:"tax_amount";i:1421;s:15:"discount_amount";i:0;s:5:"total";i:8900;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T20:45:31+00:00";s:10:"updated_at";s:25:"2026-10-19T20:45:32+00:00";}}}	1792529132
pos-restaurant-cache-idempotency:1:1:a583843e-752a-4847-9865-da352e0e30a8	a:3:{s:12:"request_hash";s:64:"de8fa701d05ac1501be4561228f9d02c2c9da2ee1f9e5a4c923e2fbce6104087";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:33:{s:4:"uuid";s:36:"3d37c1e5-79bb-481a-8dbc-1540c0ec805b";s:12:"order_number";s:21:"ORD-001-20261019-0006";s:4:"type";s:7:"takeout";s:19:"fulfillment_channel";s:6:"pickup";s:25:"fulfillment_channel_label";s:16:"Retiro en tienda";s:10:"type_label";s:11:"Para llevar";s:6:"status";s:9:"confirmed";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:8:{s:4:"uuid";s:36:"7ebd55c0-dd9d-412b-9da9-2928a05ba6dd";s:14:"menu_item_uuid";N;s:4:"name";s:19:"Caldillo de Congrio";s:10:"unit_price";i:8900;s:8:"quantity";i:1;s:8:"subtotal";i:8900;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-19T20:45:32+00:00";}}s:8:"subtotal";i:8900;s:10:"tax_amount";i:1421;s:15:"discount_amount";i:0;s:5:"total";i:8900;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-19T20:45:32+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T20:45:31+00:00";s:10:"updated_at";s:25:"2026-10-19T20:45:32+00:00";}}}	1792529132
pos-restaurant-cache-idempotency:1:1:c6157b5b-8604-44bd-a25d-48c37d7bbf43	a:3:{s:12:"request_hash";s:64:"c6a18c2a85d9705dab30a98eb178fa0fdf4c0c467d0aa07f8eaef56229df25d4";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:12:{s:4:"uuid";s:36:"d9e2d64e-5761-4ada-a32b-a991d87bea4e";s:14:"payment_number";s:27:"PAY-SCL-001-20261019-702D95";s:11:"method_code";s:4:"CASH";s:6:"amount";i:1000;s:10:"tip_amount";i:0;s:12:"total_amount";i:1000;s:14:"reference_code";N;s:6:"status";s:9:"completed";s:15:"idempotency_key";s:36:"c6157b5b-8604-44bd-a25d-48c37d7bbf43";s:5:"notes";N;s:7:"paid_at";s:25:"2026-10-19T20:46:47+00:00";s:10:"created_at";s:25:"2026-10-19T20:46:47+00:00";}}}	1792529207
pos-restaurant-cache-idempotency:1:1:dfd0ec82-79f4-49f0-95e6-d0199609aeec	a:3:{s:12:"request_hash";s:64:"274394d99271dfd059d6d8c1431c1b5ce4b64cdfb4e5aa692743f3ca1d08a6ca";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:12:{s:4:"uuid";s:36:"664ef440-e013-40ef-9e89-faf78655487c";s:14:"payment_number";s:27:"PAY-SCL-001-20261019-757636";s:11:"method_code";s:4:"CASH";s:6:"amount";i:7900;s:10:"tip_amount";i:0;s:12:"total_amount";i:7900;s:14:"reference_code";N;s:6:"status";s:9:"completed";s:15:"idempotency_key";s:36:"dfd0ec82-79f4-49f0-95e6-d0199609aeec";s:5:"notes";N;s:7:"paid_at";s:25:"2026-10-19T20:46:47+00:00";s:10:"created_at";s:25:"2026-10-19T20:46:47+00:00";}}}	1792529207
pos-restaurant-cache-idempotency:1:1:499a9925-e51b-4a37-b9f1-383dce5c7ce7	a:3:{s:12:"request_hash";s:64:"2fa9e25411ddec3d0e2a4c14762e385ce79d4e9b78e018fc1e718cc1207a1555";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:34:{s:4:"uuid";s:36:"dba8663e-5e80-4275-8c35-d74cb983fa7c";s:12:"order_number";s:21:"ORD-001-20261019-0007";s:4:"type";s:7:"dine_in";s:19:"fulfillment_channel";s:6:"onsite";s:25:"fulfillment_channel_label";s:11:"En el local";s:10:"type_label";s:19:"Para servir en mesa";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:5:"table";a:3:{s:4:"uuid";s:36:"7185d16c-26b2-4806-91a9-3ecf6fde7abb";s:12:"table_number";s:4:"M-01";s:9:"area_code";s:4:"MAIN";}s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:0:{}s:8:"subtotal";i:0;s:10:"tax_amount";i:0;s:15:"discount_amount";i:0;s:5:"total";i:0;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T20:48:31+00:00";s:10:"updated_at";s:25:"2026-10-19T20:48:31+00:00";}}}	1792529312
pos-restaurant-cache-idempotency:1:1:bfa5b4d4-baf5-4893-b2ab-c4b14df5bba5	a:3:{s:12:"request_hash";s:64:"aecdcd9b5192dd7451d5bd488e44af2298fa258fecae6644bb283b28149b5702";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:34:{s:4:"uuid";s:36:"dba8663e-5e80-4275-8c35-d74cb983fa7c";s:12:"order_number";s:21:"ORD-001-20261019-0007";s:4:"type";s:7:"dine_in";s:19:"fulfillment_channel";s:6:"onsite";s:25:"fulfillment_channel_label";s:11:"En el local";s:10:"type_label";s:19:"Para servir en mesa";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:5:"table";a:3:{s:4:"uuid";s:36:"7185d16c-26b2-4806-91a9-3ecf6fde7abb";s:12:"table_number";s:4:"M-01";s:9:"area_code";s:4:"MAIN";}s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:1:{i:0;a:9:{s:4:"uuid";s:36:"6d95cf6c-b25f-4acc-bf2f-ec42f431a4f6";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T20:48:32+00:00";}}s:8:"subtotal";i:1000;s:10:"tax_amount";i:160;s:15:"discount_amount";i:0;s:5:"total";i:1000;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T20:48:31+00:00";s:10:"updated_at";s:25:"2026-10-19T20:48:32+00:00";}}}	1792529312
pos-restaurant-cache-idempotency:1:1:84aa164f-656a-4240-b6da-182ca77e4dab	a:3:{s:12:"request_hash";s:64:"344b7fa06df3eabf19f41fcac7398f5e45a0c69094d0bb7ad5feee99c4dcb36d";s:13:"response_code";i:201;s:13:"response_body";a:1:{s:4:"data";a:34:{s:4:"uuid";s:36:"dba8663e-5e80-4275-8c35-d74cb983fa7c";s:12:"order_number";s:21:"ORD-001-20261019-0007";s:4:"type";s:7:"dine_in";s:19:"fulfillment_channel";s:6:"onsite";s:25:"fulfillment_channel_label";s:11:"En el local";s:10:"type_label";s:19:"Para servir en mesa";s:6:"status";s:5:"draft";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:1;s:9:"is_active";b:1;s:5:"table";a:3:{s:4:"uuid";s:36:"7185d16c-26b2-4806-91a9-3ecf6fde7abb";s:12:"table_number";s:4:"M-01";s:9:"area_code";s:4:"MAIN";}s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:2:{i:0;a:9:{s:4:"uuid";s:36:"d4e3ab42-3ef7-47d0-961e-0d7eb9322088";s:14:"menu_item_uuid";N;s:4:"name";s:15:"Coca-Cola 500ml";s:10:"unit_price";i:1500;s:8:"quantity";i:1;s:8:"subtotal";i:1500;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T20:48:32+00:00";}i:1;a:9:{s:4:"uuid";s:36:"6d95cf6c-b25f-4acc-bf2f-ec42f431a4f6";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:9:"modifiers";a:0:{}s:10:"created_at";s:25:"2026-10-19T20:48:32+00:00";}}s:8:"subtotal";i:2500;s:10:"tax_amount";i:399;s:15:"discount_amount";i:0;s:5:"total";i:2500;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";N;s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T20:48:31+00:00";s:10:"updated_at";s:25:"2026-10-19T20:48:32+00:00";}}}	1792529312
pos-restaurant-cache-idempotency:1:1:98f5a8a2-77ea-4aae-a707-2368be59d0f1	a:3:{s:12:"request_hash";s:64:"871db295c72d5f5830fb99d1f826f877c139fbf844923ebcd9dffb697f691a13";s:13:"response_code";i:200;s:13:"response_body";a:1:{s:4:"data";a:34:{s:4:"uuid";s:36:"dba8663e-5e80-4275-8c35-d74cb983fa7c";s:12:"order_number";s:21:"ORD-001-20261019-0007";s:4:"type";s:7:"dine_in";s:19:"fulfillment_channel";s:6:"onsite";s:25:"fulfillment_channel_label";s:11:"En el local";s:10:"type_label";s:19:"Para servir en mesa";s:6:"status";s:9:"confirmed";s:11:"sync_status";s:7:"pending";s:11:"is_editable";b:0;s:9:"is_active";b:1;s:5:"table";a:3:{s:4:"uuid";s:36:"7185d16c-26b2-4806-91a9-3ecf6fde7abb";s:12:"table_number";s:4:"M-01";s:9:"area_code";s:4:"MAIN";}s:6:"waiter";a:2:{s:4:"uuid";s:36:"51a7cf85-e65c-4f21-9462-cbc0ee15044c";s:4:"name";s:15:"Admin Principal";}s:5:"items";a:2:{i:0;a:8:{s:4:"uuid";s:36:"d4e3ab42-3ef7-47d0-961e-0d7eb9322088";s:14:"menu_item_uuid";N;s:4:"name";s:15:"Coca-Cola 500ml";s:10:"unit_price";i:1500;s:8:"quantity";i:1;s:8:"subtotal";i:1500;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-19T20:48:32+00:00";}i:1;a:8:{s:4:"uuid";s:36:"6d95cf6c-b25f-4acc-bf2f-ec42f431a4f6";s:14:"menu_item_uuid";N;s:4:"name";s:12:"Agua Mineral";s:10:"unit_price";i:1000;s:8:"quantity";i:1;s:8:"subtotal";i:1000;s:5:"notes";N;s:10:"created_at";s:25:"2026-10-19T20:48:32+00:00";}}s:8:"subtotal";i:2500;s:10:"tax_amount";i:399;s:15:"discount_amount";i:0;s:5:"total";i:2500;s:5:"notes";N;s:13:"customer_name";N;s:14:"customer_phone";N;s:9:"pickup_at";N;s:16:"delivery_address";N;s:14:"delivery_notes";N;s:12:"confirmed_at";s:25:"2026-10-19T20:48:32+00:00";s:9:"served_at";N;s:12:"picked_up_at";N;s:13:"dispatched_at";N;s:12:"delivered_at";N;s:7:"paid_at";N;s:9:"closed_at";N;s:12:"cancelled_at";N;s:19:"cancellation_reason";N;s:10:"created_at";s:25:"2026-10-19T20:48:31+00:00";s:10:"updated_at";s:25:"2026-10-19T20:48:32+00:00";}}}	1792529312
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: cash_counts; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cash_counts (id, uuid, company_id, branch_id, cash_session_id, user_id, type, reason, expected_amount, counted_amount, difference, denominations, cash_amount, card_amount, transfer_amount, other_amount, notes, has_discrepancy, discrepancy_explanation, supervised_by, supervised_at, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: cash_movements; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cash_movements (id, uuid, company_id, branch_id, cash_session_id, user_id, type, amount, reason, notes, reference_type, reference_id, authorized_by, authorized_at, balance_after, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: cash_registers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cash_registers (id, uuid, company_id, branch_id, name, code, description, opening_amount_default, max_amount, requires_dual_control, printer_id, drawer_serial, is_active, last_used_at, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: cash_sessions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cash_sessions (id, uuid, company_id, branch_id, user_id, session_number, status, opening_amount, closing_amount, expected_amount, difference, opening_notes, closing_notes, opened_at, closed_at, created_at, updated_at, deleted_at, register_id) FROM stdin;
\.


--
-- Data for Name: categories; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.categories (id, uuid, company_id, branch_id, name_translations, sort_order, is_active, created_at, updated_at, deleted_at, tax_id, parent_id, depth) FROM stdin;
1	19ece6df-a84b-447b-af30-38dc6aec1fb8	1	\N	{"es": "Entradas", "zh": "开胃菜"}	1	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N	\N	0
2	985f8a0b-6931-4e38-8e14-14a04fc95592	1	\N	{"es": "Platos Principales", "zh": "主菜"}	2	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N	\N	0
3	61753f37-e982-4f05-b121-71c61d00c216	1	\N	{"es": "Hamburguesas", "zh": "汉堡"}	3	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N	\N	0
4	60997e20-e3a3-49e6-8224-9fb0d33a2368	1	\N	{"es": "Acompañamientos", "zh": "配菜"}	4	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N	\N	0
5	f6cf38df-ffcf-4f9f-b3ac-48f00e512b26	1	\N	{"es": "Bebidas", "zh": "饮料"}	5	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N	\N	0
6	d8d49f55-4302-48fc-967f-3330ed542e87	1	\N	{"es": "Postres", "zh": "甜点"}	6	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N	\N	0
7	9b5cf727-5124-433c-8fab-c3a89b073bde	1	\N	{"es": "Combos", "zh": "套餐"}	7	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N	\N	0
\.


--
-- Data for Name: companies; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.companies (id, uuid, tax_id, legal_name, trade_name, default_locale, fallback_locale, is_active, settings, created_at, updated_at, deleted_at) FROM stdin;
1	5051b464-bd75-4d88-b6aa-aecdead80247	76123456-7	Restaurante Demo SpA	Demo Restaurant	es-CL	es-CL	t	{"currency": "CLP", "timezone": "America/Santiago"}	2026-10-12 00:16:42	2026-10-12 00:16:42	\N
\.


--
-- Data for Name: company_capabilities; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.company_capabilities (id, company_id, capability_key, is_enabled, settings, created_at, updated_at) FROM stdin;
1	1	can_split_bills	t	[]	2026-10-12 00:16:45	2026-10-12 00:16:45
2	1	can_manage_recipes	t	[]	2026-10-12 00:16:45	2026-10-12 00:16:45
3	1	requires_cashier_session	t	[]	2026-10-12 00:16:45	2026-10-12 00:16:45
4	1	can_accept_tips	t	[]	2026-10-12 00:16:45	2026-10-12 00:16:45
5	1	has_kitchen_display	t	[]	2026-10-12 00:16:45	2026-10-12 00:16:45
6	1	can_print_receipts	t	[]	2026-10-12 00:16:45	2026-10-12 00:16:45
7	1	supports_loyalty_program	t	[]	2026-10-12 00:16:45	2026-10-12 00:16:45
8	1	can_manage_reservations	t	[]	2026-10-12 00:16:45	2026-10-12 00:16:45
\.


--
-- Data for Name: default_notes; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.default_notes (id, uuid, company_id, branch_id, text_translations, sort_order, is_active, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: dining_zones; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.dining_zones (id, uuid, company_id, branch_id, code, name_translations, color, floor_level, sort_order, is_active, created_at, updated_at, deleted_at, sync_status, version, last_synced_at, offline_id) FROM stdin;
1	893fa5fb-07cb-4a73-b539-f805400c46cd	1	1	BAL01	{"es": "balcon", "zh": "balcon"}	#f97316	0	0	t	2026-10-16 02:45:35	2026-10-16 02:45:49	2026-10-16 02:45:49	pending	1	\N	\N
\.


--
-- Data for Name: dte_certificates; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.dte_certificates (id, uuid, company_id, name, serial_number, issuer, certificate_content, holder_rut, holder_name, valid_from, valid_until, environment, is_active, last_used_at, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: dte_documents; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.dte_documents (id, uuid, company_id, branch_id, dte_type, folio, order_id, receiver_rut, receiver_business_name, net_amount, tax_amount, exempt_amount, total_amount, sent_xml, timbre_xml, track_id, sii_status, sii_status_description, issue_date, sent_at, accepted_at, referenced_dte_id, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: dte_folio_ranges; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.dte_folio_ranges (id, uuid, company_id, branch_id, dte_type, folio_initial, folio_final, folio_current, caf_xml, authorization_date, authorized_rut, is_active, closed_at, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: idempotency_keys; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.idempotency_keys (id, key, request_hash, response_body, response_code, user_id, endpoint, expires_at, created_at, updated_at, company_id, branch_id, processing_until, lock_token) FROM stdin;
4	1d2bf061-5b7e-4a0f-83e9-d40d755b0130	31e0eb9f5db3a19662113e36b2e8c3946ca7e02bff31cbaf52707cd4356f73c8	{"data":{"uuid":"2b188715-9d56-4293-894b-e1c13904fcb4","order_number":"ORD-001-20261016-0001","type":"dine_in","fulfillment_channel":"onsite","fulfillment_channel_label":"En el local","type_label":"Para servir en mesa","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"table":{"uuid":"f8c4272e-f51c-485f-9c1f-2fc14dd7744d","table_number":"B-01","area_code":"BAR"},"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T14:28:36+00:00","updated_at":"2026-10-16T14:28:36+00:00"}}	201	1	api/v1/orders	2026-10-17 14:28:36	2026-10-16 14:28:36	2026-10-16 14:28:36	1	1	2026-10-16 14:29:36	\N
5	017ccd70-3fb0-440d-b58c-294513eddcaa	7e5b67c20ea335111d55f634660a4aae6c915921aa26627e0fa42121b3ccc708	{"data":{"uuid":"2b188715-9d56-4293-894b-e1c13904fcb4","order_number":"ORD-001-20261016-0001","type":"dine_in","fulfillment_channel":"onsite","fulfillment_channel_label":"En el local","type_label":"Para servir en mesa","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"table":{"uuid":"f8c4272e-f51c-485f-9c1f-2fc14dd7744d","table_number":"B-01","area_code":"BAR"},"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"b6501f18-cf75-43a7-b230-080a053daf1e","menu_item_uuid":null,"name":"Caldillo de Congrio","unit_price":8900,"quantity":1,"subtotal":8900,"notes":null,"modifiers":[],"created_at":"2026-10-16T14:28:36+00:00"}],"subtotal":8900,"tax_amount":1421,"discount_amount":0,"total":8900,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T14:28:36+00:00","updated_at":"2026-10-16T14:28:36+00:00"}}	201	1	api/v1/orders/2b188715-9d56-4293-894b-e1c13904fcb4/items	2026-10-17 14:28:36	2026-10-16 14:28:36	2026-10-16 14:28:36	1	1	2026-10-16 14:29:36	\N
6	81154c6f-208f-402b-8309-b98f8c0741c5	bf127eea2a5a4f4a8cd2d26a07235101698eb89f1ee34082bcb7071a7e41e9d6	{"data":{"uuid":"2b188715-9d56-4293-894b-e1c13904fcb4","order_number":"ORD-001-20261016-0001","type":"dine_in","fulfillment_channel":"onsite","fulfillment_channel_label":"En el local","type_label":"Para servir en mesa","status":"confirmed","sync_status":"pending","is_editable":false,"is_active":true,"table":{"uuid":"f8c4272e-f51c-485f-9c1f-2fc14dd7744d","table_number":"B-01","area_code":"BAR"},"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"b6501f18-cf75-43a7-b230-080a053daf1e","menu_item_uuid":null,"name":"Caldillo de Congrio","unit_price":8900,"quantity":1,"subtotal":8900,"notes":null,"created_at":"2026-10-16T14:28:36+00:00"}],"subtotal":8900,"tax_amount":1421,"discount_amount":0,"total":8900,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-16T14:28:37+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T14:28:36+00:00","updated_at":"2026-10-16T14:28:37+00:00"}}	200	1	api/v1/orders/2b188715-9d56-4293-894b-e1c13904fcb4/confirm	2026-10-17 14:28:37	2026-10-16 14:28:37	2026-10-16 14:28:37	1	1	2026-10-16 14:29:37	\N
7	d7f29f22-fd3e-43e6-adb7-d60186369683	d2c0c056a267c4b26e1b1d34eed6b34085de73fff7eb0407bf19ab00aa9e4624	{"data":{"uuid":"2b188715-9d56-4293-894b-e1c13904fcb4","order_number":"ORD-001-20261016-0001","type":"dine_in","fulfillment_channel":"onsite","fulfillment_channel_label":"En el local","type_label":"Para servir en mesa","status":"preparing","sync_status":"pending","is_editable":false,"is_active":true,"table":{"uuid":"f8c4272e-f51c-485f-9c1f-2fc14dd7744d","table_number":"B-01","area_code":"BAR"},"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"b6501f18-cf75-43a7-b230-080a053daf1e","menu_item_uuid":null,"name":"Caldillo de Congrio","unit_price":8900,"quantity":1,"subtotal":8900,"notes":null,"created_at":"2026-10-16T14:28:36+00:00"}],"subtotal":8900,"tax_amount":1421,"discount_amount":0,"total":8900,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-16T14:28:37+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T14:28:36+00:00","updated_at":"2026-10-16T15:17:35+00:00"}}	200	1	api/v1/orders/2b188715-9d56-4293-894b-e1c13904fcb4/prepare	2026-10-17 15:17:35	2026-10-16 15:17:35	2026-10-16 15:17:35	1	1	2026-10-16 15:18:35	\N
236	bf516d54-15e4-44ce-9d04-5c745fb386b7	7df1a8913c72e49954e1504b5e0d7e35fb9ea100848e0cce5d66c37a867347af	{"data":{"uuid":"f70b2e8b-851d-429b-a0d9-ad7c6a11d589","payment_number":"PAY-SCL-001-20261019-C9188A","method_code":"CASH","amount":3500,"tip_amount":0,"total_amount":3500,"reference_code":null,"status":"completed","idempotency_key":"bf516d54-15e4-44ce-9d04-5c745fb386b7","notes":null,"paid_at":"2026-10-19T19:59:08+00:00","created_at":"2026-10-19T19:59:08+00:00"}}	201	1	api/v1/billing/payments	2026-10-20 19:59:08	2026-10-19 19:59:08	2026-10-19 19:59:08	1	1	2026-10-19 20:00:08	\N
238	a4e06b17-0ec1-443c-a91e-9eb93eb36817	446d3041588cc4e860ef83237abd6118fb7e5893d4495bd519a981632f3ac781	{"data":{"uuid":"3d37c1e5-79bb-481a-8dbc-1540c0ec805b","order_number":"ORD-001-20261019-0006","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"7ebd55c0-dd9d-412b-9da9-2928a05ba6dd","menu_item_uuid":null,"name":"Caldillo de Congrio","unit_price":8900,"quantity":1,"subtotal":8900,"notes":null,"modifiers":[],"created_at":"2026-10-19T20:45:32+00:00"}],"subtotal":8900,"tax_amount":1421,"discount_amount":0,"total":8900,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T20:45:31+00:00","updated_at":"2026-10-19T20:45:32+00:00"}}	201	1	api/v1/orders/3d37c1e5-79bb-481a-8dbc-1540c0ec805b/items	2026-10-20 20:45:32	2026-10-19 20:45:32	2026-10-19 20:45:32	1	1	2026-10-19 20:46:32	\N
240	51fa7bef-e771-4e04-8dbb-f3b0ef981b06	473faafbdf92234947ac917a827d3c52a9f0388c9489c014a38e1de9f1818bdd	{"uuid":"66295a36-1d41-4af2-a3fa-3a3b2c1f6234","id":6,"bill_number":"ORD-001-20261019-0006-1","type":"single","subtotal":8900,"tax_amount":1421,"total":8900,"paid_amount":0,"remaining_amount":8900,"status":"open","idempotent":false,"message":"Bill creada con montos calculados por el servidor"}	201	1	api/v1/bills	2026-10-20 20:46:16	2026-10-19 20:46:16	2026-10-19 20:46:16	1	1	2026-10-19 20:47:16	\N
8	8d34b41c-7ec2-4827-b431-9025a95152c5	04c98f0c6d43fe439a702df403795c7df37029d08952b0d94759a244830c1a31	{"data":{"uuid":"2b188715-9d56-4293-894b-e1c13904fcb4","order_number":"ORD-001-20261016-0001","type":"dine_in","fulfillment_channel":"onsite","fulfillment_channel_label":"En el local","type_label":"Para servir en mesa","status":"ready","sync_status":"pending","is_editable":false,"is_active":true,"table":{"uuid":"f8c4272e-f51c-485f-9c1f-2fc14dd7744d","table_number":"B-01","area_code":"BAR"},"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"b6501f18-cf75-43a7-b230-080a053daf1e","menu_item_uuid":null,"name":"Caldillo de Congrio","unit_price":8900,"quantity":1,"subtotal":8900,"notes":null,"created_at":"2026-10-16T14:28:36+00:00"}],"subtotal":8900,"tax_amount":1421,"discount_amount":0,"total":8900,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-16T14:28:37+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T14:28:36+00:00","updated_at":"2026-10-16T15:17:38+00:00"}}	200	1	api/v1/orders/2b188715-9d56-4293-894b-e1c13904fcb4/ready	2026-10-17 15:17:38	2026-10-16 15:17:38	2026-10-16 15:17:38	1	1	2026-10-16 15:18:38	\N
9	d5f04cfa-f6a6-46bb-9655-ccf058f0035b	8d9ccd4958fd52a828cb5391400d283f7b8045a805dd921ed7d2915f5f0694ef	{"data":{"uuid":"2b188715-9d56-4293-894b-e1c13904fcb4","order_number":"ORD-001-20261016-0001","type":"dine_in","fulfillment_channel":"onsite","fulfillment_channel_label":"En el local","type_label":"Para servir en mesa","status":"served","sync_status":"pending","is_editable":false,"is_active":true,"table":{"uuid":"f8c4272e-f51c-485f-9c1f-2fc14dd7744d","table_number":"B-01","area_code":"BAR"},"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"b6501f18-cf75-43a7-b230-080a053daf1e","menu_item_uuid":null,"name":"Caldillo de Congrio","unit_price":8900,"quantity":1,"subtotal":8900,"notes":null,"created_at":"2026-10-16T14:28:36+00:00"}],"subtotal":8900,"tax_amount":1421,"discount_amount":0,"total":8900,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-16T14:28:37+00:00","served_at":"2026-10-16T15:17:41+00:00","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T14:28:36+00:00","updated_at":"2026-10-16T15:17:41+00:00"}}	200	1	api/v1/orders/2b188715-9d56-4293-894b-e1c13904fcb4/serve	2026-10-17 15:17:41	2026-10-16 15:17:41	2026-10-16 15:17:41	1	1	2026-10-16 15:18:41	\N
10	436eb5bb-0784-4284-8eb3-6719ee89716f	f9fe02acae380e23c04b549e4552f9584a08285149368b56a0914765b1c5935a	{"data":{"uuid":"d975b860-a5e5-46ce-be54-793a8a592152","order_number":"ORD-001-20261016-0002","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T15:20:12+00:00","updated_at":"2026-10-16T15:20:12+00:00"}}	201	1	api/v1/orders	2026-10-17 15:20:12	2026-10-16 15:20:12	2026-10-16 15:20:12	1	1	2026-10-16 15:21:12	\N
11	428cf852-da30-4577-83e9-dfbb6acda2ad	971eed7ba31f4666dc8dbdc19459d4b099cc458c6677876700c610e7f673dd3c	{"data":{"uuid":"d975b860-a5e5-46ce-be54-793a8a592152","order_number":"ORD-001-20261016-0002","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"42cd2483-adb2-443c-b551-dbad6c85524d","menu_item_uuid":null,"name":"Empanadas de Pino (3un)","unit_price":3500,"quantity":1,"subtotal":3500,"notes":null,"modifiers":[],"created_at":"2026-10-16T15:20:13+00:00"}],"subtotal":3500,"tax_amount":559,"discount_amount":0,"total":3500,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T15:20:12+00:00","updated_at":"2026-10-16T15:20:13+00:00"}}	201	1	api/v1/orders/d975b860-a5e5-46ce-be54-793a8a592152/items	2026-10-17 15:20:13	2026-10-16 15:20:13	2026-10-16 15:20:13	1	1	2026-10-16 15:21:13	\N
12	95be9535-9c4d-43f2-bb9c-ed87fc0ae88f	e3b2d6edacf8e2ade3880fcc2fdaf91346e4d015fcf27bc73a4d83e3d484f8fa	{"data":{"uuid":"d975b860-a5e5-46ce-be54-793a8a592152","order_number":"ORD-001-20261016-0002","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"confirmed","sync_status":"pending","is_editable":false,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"42cd2483-adb2-443c-b551-dbad6c85524d","menu_item_uuid":null,"name":"Empanadas de Pino (3un)","unit_price":3500,"quantity":1,"subtotal":3500,"notes":null,"created_at":"2026-10-16T15:20:13+00:00"}],"subtotal":3500,"tax_amount":559,"discount_amount":0,"total":3500,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-16T15:20:13+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T15:20:12+00:00","updated_at":"2026-10-16T15:20:13+00:00"}}	200	1	api/v1/orders/d975b860-a5e5-46ce-be54-793a8a592152/confirm	2026-10-17 15:20:13	2026-10-16 15:20:13	2026-10-16 15:20:13	1	1	2026-10-16 15:21:13	\N
176	c90473a1-3be7-488f-affb-32e0f6478d08	3b3b796f6154419994f711690aaad3734453c68fbf7fbde3e7a36feb639d2ed4	{"data":{"uuid":"a90dc353-c147-46d2-abe7-3335439d33df","order_number":"ORD-001-20261019-0003","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T18:40:19+00:00","updated_at":"2026-10-19T18:40:19+00:00"}}	201	1	api/v1/orders	2026-10-20 18:40:19	2026-10-19 18:40:19	2026-10-19 18:40:19	1	1	2026-10-19 18:41:19	\N
30	0e2de178-3c31-45fc-a508-7cc895652cfe	974ef9e0506b3c1919391aa9b4c07fbacaed0c2e20a7f83f5ad3f6f3b6b24919	{"data":{"uuid":"6235e9fa-a1c6-4485-94f4-a2f9c1f3e097","order_number":"ORD-001-20261016-0006","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T15:52:08+00:00","updated_at":"2026-10-16T15:52:08+00:00"}}	201	1	api/v1/orders	2026-10-17 15:52:08	2026-10-16 15:52:08	2026-10-16 15:52:08	1	1	2026-10-16 15:53:08	\N
31	954570bd-4f65-49db-b7f6-d100b4bbfa34	b3fe84cb00b310a0f309ffc069020fefd8486e09d31c65281d27508822c35097	{"data":{"uuid":"6235e9fa-a1c6-4485-94f4-a2f9c1f3e097","order_number":"ORD-001-20261016-0006","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"56c569b0-8c9f-4a4b-a98a-a6978e2f2ce6","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-16T15:52:08+00:00"}],"subtotal":1000,"tax_amount":160,"discount_amount":0,"total":1000,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T15:52:08+00:00","updated_at":"2026-10-16T15:52:08+00:00"}}	201	1	api/v1/orders/6235e9fa-a1c6-4485-94f4-a2f9c1f3e097/items	2026-10-17 15:52:08	2026-10-16 15:52:08	2026-10-16 15:52:08	1	1	2026-10-16 15:53:08	\N
32	09d726e8-6937-4cd9-8293-a5dc049f731f	d98b1b4cfc50f9eab43d25f14fa5ca28a4dd42a60c9e111f5d60bf5ef2f8bbd6	{"data":{"uuid":"6235e9fa-a1c6-4485-94f4-a2f9c1f3e097","order_number":"ORD-001-20261016-0006","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"9cd3d388-04b1-44a3-aee7-c81f9d898c5e","menu_item_uuid":null,"name":"Coca-Cola 500ml","unit_price":1500,"quantity":1,"subtotal":1500,"notes":null,"modifiers":[],"created_at":"2026-10-16T15:52:08+00:00"},{"uuid":"56c569b0-8c9f-4a4b-a98a-a6978e2f2ce6","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-16T15:52:08+00:00"}],"subtotal":2500,"tax_amount":399,"discount_amount":0,"total":2500,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T15:52:08+00:00","updated_at":"2026-10-16T15:52:08+00:00"}}	201	1	api/v1/orders/6235e9fa-a1c6-4485-94f4-a2f9c1f3e097/items	2026-10-17 15:52:08	2026-10-16 15:52:08	2026-10-16 15:52:08	1	1	2026-10-16 15:53:08	\N
33	28aeff34-15fe-40aa-afeb-ca40a86d168d	bafacc815512d96957149b8cbd161313ff76a2513087f74aee6930231dfa4a00	{"data":{"uuid":"6235e9fa-a1c6-4485-94f4-a2f9c1f3e097","order_number":"ORD-001-20261016-0006","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"confirmed","sync_status":"pending","is_editable":false,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"9cd3d388-04b1-44a3-aee7-c81f9d898c5e","menu_item_uuid":null,"name":"Coca-Cola 500ml","unit_price":1500,"quantity":1,"subtotal":1500,"notes":null,"created_at":"2026-10-16T15:52:08+00:00"},{"uuid":"56c569b0-8c9f-4a4b-a98a-a6978e2f2ce6","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"created_at":"2026-10-16T15:52:08+00:00"}],"subtotal":2500,"tax_amount":399,"discount_amount":0,"total":2500,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-16T15:52:08+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T15:52:08+00:00","updated_at":"2026-10-16T15:52:08+00:00"}}	200	1	api/v1/orders/6235e9fa-a1c6-4485-94f4-a2f9c1f3e097/confirm	2026-10-17 15:52:08	2026-10-16 15:52:08	2026-10-16 15:52:08	1	1	2026-10-16 15:53:08	\N
41	3af5d544-ae1e-4cb8-9e87-2c80e684ead4	3b82827fb9b9e433a84d4cb9eb37c2c9e41b31b290f6df67fb77d4bf04ebbb28	{"data":{"uuid":"ab321bf9-19aa-4689-9dbb-52706407d4a7","order_number":"ORD-001-20261016-0007","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T15:55:31+00:00","updated_at":"2026-10-16T15:55:31+00:00"}}	201	1	api/v1/orders	2026-10-17 15:55:31	2026-10-16 15:55:31	2026-10-16 15:55:32	1	1	2026-10-16 15:56:31	\N
42	2935527f-17bd-433f-9590-b0faeb058cdc	11854912141ab4cb41737338aae95b9e8f9a3a133a3239f369789f94709c595b	{"data":{"uuid":"ab321bf9-19aa-4689-9dbb-52706407d4a7","order_number":"ORD-001-20261016-0007","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"a90f7639-303b-4eca-b1e1-bd729ec4ea90","menu_item_uuid":null,"name":"Caldillo de Congrio","unit_price":8900,"quantity":1,"subtotal":8900,"notes":null,"modifiers":[],"created_at":"2026-10-16T15:55:32+00:00"}],"subtotal":8900,"tax_amount":1421,"discount_amount":0,"total":8900,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T15:55:31+00:00","updated_at":"2026-10-16T15:55:32+00:00"}}	201	1	api/v1/orders/ab321bf9-19aa-4689-9dbb-52706407d4a7/items	2026-10-17 15:55:32	2026-10-16 15:55:32	2026-10-16 15:55:32	1	1	2026-10-16 15:56:32	\N
43	a96634e8-4991-4622-bb0e-3ee527b7e093	1a35689811da48d85f39a5f60c0e36a087b80baf13ff2e7c50060c1825500087	{"data":{"uuid":"ab321bf9-19aa-4689-9dbb-52706407d4a7","order_number":"ORD-001-20261016-0007","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"confirmed","sync_status":"pending","is_editable":false,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"a90f7639-303b-4eca-b1e1-bd729ec4ea90","menu_item_uuid":null,"name":"Caldillo de Congrio","unit_price":8900,"quantity":1,"subtotal":8900,"notes":null,"created_at":"2026-10-16T15:55:32+00:00"}],"subtotal":8900,"tax_amount":1421,"discount_amount":0,"total":8900,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-16T15:55:32+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T15:55:31+00:00","updated_at":"2026-10-16T15:55:32+00:00"}}	200	1	api/v1/orders/ab321bf9-19aa-4689-9dbb-52706407d4a7/confirm	2026-10-17 15:55:32	2026-10-16 15:55:32	2026-10-16 15:55:32	1	1	2026-10-16 15:56:32	\N
130	32e1b173-4f22-4bde-b538-2045c55cb1c6	9214b499c1df41bd2e81c3b4fad6758ee5875e05ca1e411dc7247329167b7ef8	{"uuid":"522b5b55-93c9-49cd-adda-98846aa007ec","id":1,"bill_number":"ORD-001-20261017-0001-1","type":"single","subtotal":7200,"tax_amount":1150,"total":7200,"paid_amount":0,"remaining_amount":7200,"status":"open","idempotent":false,"message":"Bill creada con montos calculados por el servidor"}	201	1	api/v1/bills	2026-10-20 18:07:49	2026-10-19 18:07:49	2026-10-19 18:07:49	1	1	2026-10-19 18:08:49	\N
49	34feb1c1-bf8c-467b-95c0-035c03f0d074	79e0b70ef4998b8aed3aca5adf2262c196366adefa0e1000e537e5cae654a48c	{"data":{"uuid":"f9fb9996-b05e-458d-a737-c53a196f67c8","order_number":"ORD-001-20261016-0008","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T16:21:26+00:00","updated_at":"2026-10-16T16:21:26+00:00"}}	201	1	api/v1/orders	2026-10-17 16:21:26	2026-10-16 16:21:26	2026-10-16 16:21:26	1	1	2026-10-16 16:22:26	\N
50	45608b7b-dfcd-4ecd-9e72-9f29ef152b50	d67e6b4ef756bf551a000f477299483ba9de9bc8ebc5a1832dad20c886e378b5	{"data":{"uuid":"f9fb9996-b05e-458d-a737-c53a196f67c8","order_number":"ORD-001-20261016-0008","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"945fefa8-a514-43ef-9251-2e7fd2a74170","menu_item_uuid":null,"name":"Ensalada Mixta","unit_price":3200,"quantity":1,"subtotal":3200,"notes":null,"modifiers":[],"created_at":"2026-10-16T16:21:27+00:00"}],"subtotal":3200,"tax_amount":511,"discount_amount":0,"total":3200,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T16:21:26+00:00","updated_at":"2026-10-16T16:21:27+00:00"}}	201	1	api/v1/orders/f9fb9996-b05e-458d-a737-c53a196f67c8/items	2026-10-17 16:21:26	2026-10-16 16:21:26	2026-10-16 16:21:27	1	1	2026-10-16 16:22:26	\N
51	4e47af0a-0e2d-48e3-98bd-3ba043100297	b016f0a6aef479317532922224a9f3cf26505ed6cc8c9e468800d4fa359d0c91	{"data":{"uuid":"f9fb9996-b05e-458d-a737-c53a196f67c8","order_number":"ORD-001-20261016-0008","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"confirmed","sync_status":"pending","is_editable":false,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"945fefa8-a514-43ef-9251-2e7fd2a74170","menu_item_uuid":null,"name":"Ensalada Mixta","unit_price":3200,"quantity":1,"subtotal":3200,"notes":null,"created_at":"2026-10-16T16:21:27+00:00"}],"subtotal":3200,"tax_amount":511,"discount_amount":0,"total":3200,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-16T16:21:27+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T16:21:26+00:00","updated_at":"2026-10-16T16:21:27+00:00"}}	200	1	api/v1/orders/f9fb9996-b05e-458d-a737-c53a196f67c8/confirm	2026-10-17 16:21:27	2026-10-16 16:21:27	2026-10-16 16:21:27	1	1	2026-10-16 16:22:27	\N
55	03eb24c8-7d75-4fe0-beec-e5e2fb5bb567	b5e9194e09c0469a9c3bb906ddb371104e30f055a7b1fc563fc837ebe59c9398	{"data":{"uuid":"76e29d27-9058-4622-a734-bfc3a044423b","order_number":"ORD-001-20261016-0009","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T16:30:18+00:00","updated_at":"2026-10-16T16:30:18+00:00"}}	201	1	api/v1/orders	2026-10-17 16:30:18	2026-10-16 16:30:18	2026-10-16 16:30:18	1	1	2026-10-16 16:31:18	\N
56	b086aa93-06e2-4ac3-a6be-fe057dcf0bb7	4b6f5aed305ce06fcacee3469fac1ec06a585865aa8d0a388b12aa00774d2246	{"data":{"uuid":"76e29d27-9058-4622-a734-bfc3a044423b","order_number":"ORD-001-20261016-0009","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"163e19cb-1bf3-408d-8d6d-0c98c360551c","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":1,"subtotal":7200,"notes":null,"modifiers":[],"created_at":"2026-10-16T16:30:18+00:00"}],"subtotal":7200,"tax_amount":1150,"discount_amount":0,"total":7200,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T16:30:18+00:00","updated_at":"2026-10-16T16:30:18+00:00"}}	201	1	api/v1/orders/76e29d27-9058-4622-a734-bfc3a044423b/items	2026-10-17 16:30:18	2026-10-16 16:30:18	2026-10-16 16:30:18	1	1	2026-10-16 16:31:18	\N
57	fa5fd222-8dae-4417-b67f-994d5e38a054	82a65888e52f76ad28f874714b3899a6e58de0a7dea6ca89865e2b4c9bc8c21f	{"data":{"uuid":"76e29d27-9058-4622-a734-bfc3a044423b","order_number":"ORD-001-20261016-0009","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"dff551c5-f3b6-4f8a-afa7-3135757df903","menu_item_uuid":null,"name":"Hamburguesa Cl\\u00e1sica","unit_price":5500,"quantity":1,"subtotal":5500,"notes":null,"modifiers":[],"created_at":"2026-10-16T16:30:18+00:00"},{"uuid":"163e19cb-1bf3-408d-8d6d-0c98c360551c","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":1,"subtotal":7200,"notes":null,"modifiers":[],"created_at":"2026-10-16T16:30:18+00:00"}],"subtotal":12700,"tax_amount":2028,"discount_amount":0,"total":12700,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T16:30:18+00:00","updated_at":"2026-10-16T16:30:18+00:00"}}	201	1	api/v1/orders/76e29d27-9058-4622-a734-bfc3a044423b/items	2026-10-17 16:30:18	2026-10-16 16:30:18	2026-10-16 16:30:18	1	1	2026-10-16 16:31:18	\N
58	faa93f9b-8294-4750-b94d-efb27f4c70f7	2a600af2c3e09cc8b492ac3e202533a927f1badb9d2c81763a47eb3dd7e72485	{"data":{"uuid":"76e29d27-9058-4622-a734-bfc3a044423b","order_number":"ORD-001-20261016-0009","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"confirmed","sync_status":"pending","is_editable":false,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"dff551c5-f3b6-4f8a-afa7-3135757df903","menu_item_uuid":null,"name":"Hamburguesa Cl\\u00e1sica","unit_price":5500,"quantity":1,"subtotal":5500,"notes":null,"created_at":"2026-10-16T16:30:18+00:00"},{"uuid":"163e19cb-1bf3-408d-8d6d-0c98c360551c","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":1,"subtotal":7200,"notes":null,"created_at":"2026-10-16T16:30:18+00:00"}],"subtotal":12700,"tax_amount":2028,"discount_amount":0,"total":12700,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-16T16:30:18+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16T16:30:18+00:00","updated_at":"2026-10-16T16:30:18+00:00"}}	200	1	api/v1/orders/76e29d27-9058-4622-a734-bfc3a044423b/confirm	2026-10-17 16:30:18	2026-10-16 16:30:18	2026-10-16 16:30:18	1	1	2026-10-16 16:31:18	\N
66	3353d227-7b0b-450d-943f-69c6edffa5f3	fbf9508ddc90a4c4741fb9b1a57e0ed8beab02758e589acea416eb36f3f407c3	{"data":{"uuid":"8025f6bb-3add-42c7-a80f-20145b50f23f","order_number":"ORD-001-20261017-0001","type":"dine_in","fulfillment_channel":"onsite","fulfillment_channel_label":"En el local","type_label":"Para servir en mesa","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"table":{"uuid":"36c74c84-bb86-49a9-a2f1-fe8ab7d736b0","table_number":"B-02","area_code":"BAR"},"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T13:59:27+00:00","updated_at":"2026-10-17T13:59:27+00:00"}}	201	1	api/v1/orders	2026-10-18 13:59:27	2026-10-17 13:59:27	2026-10-17 13:59:27	1	1	2026-10-17 14:00:27	\N
67	03795567-261f-4790-b34a-566e38fddfb4	4d1a13e967a26fea9df10b83d9700dba7e867a539f0dca69d77f2e03d441c1f9	{"data":{"uuid":"8025f6bb-3add-42c7-a80f-20145b50f23f","order_number":"ORD-001-20261017-0001","type":"dine_in","fulfillment_channel":"onsite","fulfillment_channel_label":"En el local","type_label":"Para servir en mesa","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"table":{"uuid":"36c74c84-bb86-49a9-a2f1-fe8ab7d736b0","table_number":"B-02","area_code":"BAR"},"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"5e1e8a83-632f-42e7-a96a-e39ac39cfe8f","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":1,"subtotal":7200,"notes":null,"modifiers":[],"created_at":"2026-10-17T13:59:27+00:00"}],"subtotal":7200,"tax_amount":1150,"discount_amount":0,"total":7200,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T13:59:27+00:00","updated_at":"2026-10-17T13:59:27+00:00"}}	201	1	api/v1/orders/8025f6bb-3add-42c7-a80f-20145b50f23f/items	2026-10-18 13:59:27	2026-10-17 13:59:27	2026-10-17 13:59:27	1	1	2026-10-17 14:00:27	\N
68	3b7a277f-c158-44a3-8c11-d36b76096538	181410bf27cd7fe1e65e833cc7903e15ca7ffd8d8b4315ae3c65c5447ca002dd	{"data":{"uuid":"8025f6bb-3add-42c7-a80f-20145b50f23f","order_number":"ORD-001-20261017-0001","type":"dine_in","fulfillment_channel":"onsite","fulfillment_channel_label":"En el local","type_label":"Para servir en mesa","status":"confirmed","sync_status":"pending","is_editable":false,"is_active":true,"table":{"uuid":"36c74c84-bb86-49a9-a2f1-fe8ab7d736b0","table_number":"B-02","area_code":"BAR"},"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"5e1e8a83-632f-42e7-a96a-e39ac39cfe8f","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":1,"subtotal":7200,"notes":null,"created_at":"2026-10-17T13:59:27+00:00"}],"subtotal":7200,"tax_amount":1150,"discount_amount":0,"total":7200,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-17T13:59:28+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T13:59:27+00:00","updated_at":"2026-10-17T13:59:28+00:00"}}	200	1	api/v1/orders/8025f6bb-3add-42c7-a80f-20145b50f23f/confirm	2026-10-18 13:59:28	2026-10-17 13:59:28	2026-10-17 13:59:28	1	1	2026-10-17 14:00:28	\N
69	879cd9a4-cb4d-44e4-b071-37d31877facf	08b797d9020c696561afe1e4fbf140c93500308a110e6320d5a5895ab0c809b2	{"data":{"uuid":"c293b196-d412-4123-8185-55bca2a3866e","session_number":"CS-20261017-2AED9D","status":"open","opening_amount":12000,"closing_amount":null,"expected_amount":null,"difference":null,"opening_notes":null,"closing_notes":null,"opened_at":"2026-10-17T14:00:02+00:00","closed_at":null,"user":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"breakdown":{"cash_sales":0,"card_sales":0,"transfer_sales":0,"total_tips":0,"cash_tips_received":0,"movements_in":0,"movements_out":0,"pending_sync_payments":0}}}	201	1	api/v1/cash-sessions/open	2026-10-18 14:00:02	2026-10-17 14:00:02	2026-10-17 14:00:02	1	1	2026-10-17 14:01:02	\N
73	79b90f15-a790-4b0a-9af3-d5e95f86639a	0f0bc08fa9aea51de853011d093161bb1e24e69f30ba86a44441d6e5c26368da	{"data":{"uuid":"dcf06f08-aa7b-41ba-9f47-b55e6e940c17","order_number":"ORD-001-20261017-0002","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:13:59+00:00","updated_at":"2026-10-17T15:13:59+00:00"}}	201	1	api/v1/orders	2026-10-18 15:13:59	2026-10-17 15:13:59	2026-10-17 15:13:59	1	1	2026-10-17 15:14:59	\N
74	d00acd46-ec4e-4f38-bc38-0c4518fbfa99	f78edf65efe5614f33683245143f4ee4f1205eb60b2f5d1423b7e7ea36d881cf	{"data":{"uuid":"dcf06f08-aa7b-41ba-9f47-b55e6e940c17","order_number":"ORD-001-20261017-0002","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"ebc3a133-dc9b-4cb1-a132-94d008144f56","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":1,"subtotal":7200,"notes":null,"modifiers":[],"created_at":"2026-10-17T15:13:59+00:00"}],"subtotal":7200,"tax_amount":1150,"discount_amount":0,"total":7200,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:13:59+00:00","updated_at":"2026-10-17T15:13:59+00:00"}}	201	1	api/v1/orders/dcf06f08-aa7b-41ba-9f47-b55e6e940c17/items	2026-10-18 15:13:59	2026-10-17 15:13:59	2026-10-17 15:13:59	1	1	2026-10-17 15:14:59	\N
75	4a54d990-3979-47b0-9095-de3cc017671d	09b518cb33982a519de35bb4d1793a003ab0329604f58a1c77d088d1730c5c7e	{"data":{"uuid":"dcf06f08-aa7b-41ba-9f47-b55e6e940c17","order_number":"ORD-001-20261017-0002","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"bd9fabef-a976-4d0d-b726-98930f19eb71","menu_item_uuid":null,"name":"Hamburguesa Cl\\u00e1sica","unit_price":5500,"quantity":1,"subtotal":5500,"notes":null,"modifiers":[],"created_at":"2026-10-17T15:13:59+00:00"},{"uuid":"ebc3a133-dc9b-4cb1-a132-94d008144f56","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":1,"subtotal":7200,"notes":null,"modifiers":[],"created_at":"2026-10-17T15:13:59+00:00"}],"subtotal":12700,"tax_amount":2028,"discount_amount":0,"total":12700,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:13:59+00:00","updated_at":"2026-10-17T15:13:59+00:00"}}	201	1	api/v1/orders/dcf06f08-aa7b-41ba-9f47-b55e6e940c17/items	2026-10-18 15:13:59	2026-10-17 15:13:59	2026-10-17 15:13:59	1	1	2026-10-17 15:14:59	\N
76	e5337cd5-c90f-41b5-943f-060648b88977	b00d3b1d9a9d3f7d43c9cd7c4996ba05861b30748523f3539edcdf4ee3189708	{"data":{"uuid":"dcf06f08-aa7b-41ba-9f47-b55e6e940c17","order_number":"ORD-001-20261017-0002","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"bd9fabef-a976-4d0d-b726-98930f19eb71","menu_item_uuid":null,"name":"Hamburguesa Cl\\u00e1sica","unit_price":5500,"quantity":1,"subtotal":5500,"notes":null,"modifiers":[],"created_at":"2026-10-17T15:13:59+00:00"},{"uuid":"ebc3a133-dc9b-4cb1-a132-94d008144f56","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":1,"subtotal":7200,"notes":null,"modifiers":[],"created_at":"2026-10-17T15:13:59+00:00"},{"uuid":"9bd6e96b-6b51-40cc-9b90-51f271d45e7f","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-17T15:13:59+00:00"}],"subtotal":13700,"tax_amount":2187,"discount_amount":0,"total":13700,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:13:59+00:00","updated_at":"2026-10-17T15:13:59+00:00"}}	201	1	api/v1/orders/dcf06f08-aa7b-41ba-9f47-b55e6e940c17/items	2026-10-18 15:13:59	2026-10-17 15:13:59	2026-10-17 15:13:59	1	1	2026-10-17 15:14:59	\N
203	a9a9333a-9c51-4f0f-9083-8072683653aa	2db63702266d75d6f4c65be3dca7fe04497f3e30d494039326caffd421c59392	{"uuid":"46b4085a-7fab-458c-8e18-52adaf7023de","id":3,"bill_number":"ORD-001-20261016-0002-1","type":"single","subtotal":3500,"tax_amount":559,"total":3500,"paid_amount":0,"remaining_amount":3500,"status":"open","idempotent":false,"message":"Bill creada con montos calculados por el servidor"}	201	1	api/v1/bills	2026-10-20 18:41:05	2026-10-19 18:41:05	2026-10-19 18:41:05	1	1	2026-10-19 18:42:05	\N
82	6da9102e-ce1d-4931-b4c1-b7f33b49d6a4	e59c7229adcdf8b54a7988d1b4ee51a28860cefb7de4669db62395b4bd6be936	{"data":{"uuid":"6cfc9534-bba3-4171-a473-4c4bbb1682c2","order_number":"ORD-001-20261017-0003","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"confirmed","sync_status":"pending","is_editable":false,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"7a6d63f5-dd9e-4c5a-bc9d-723096ee379f","menu_item_uuid":null,"name":"Hamburguesa Cl\\u00e1sica","unit_price":5500,"quantity":1,"subtotal":5500,"notes":null,"created_at":"2026-10-17T15:16:29+00:00"},{"uuid":"86e26acc-ff1d-4a47-b8dd-423396206784","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"created_at":"2026-10-17T15:16:29+00:00"}],"subtotal":6500,"tax_amount":1038,"discount_amount":0,"total":6500,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-17T15:16:29+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:16:29+00:00","updated_at":"2026-10-17T15:16:29+00:00"}}	200	1	api/v1/orders/6cfc9534-bba3-4171-a473-4c4bbb1682c2/confirm	2026-10-18 15:16:29	2026-10-17 15:16:29	2026-10-17 15:16:29	1	1	2026-10-17 15:17:29	\N
77	ed9530b5-825b-436e-bc25-a0525fa2e28f	641f73123f09ff6fcf11252f165ebd2a7726eb66c091f2ad35b0e7588f23b257	{"data":{"uuid":"dcf06f08-aa7b-41ba-9f47-b55e6e940c17","order_number":"ORD-001-20261017-0002","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"confirmed","sync_status":"pending","is_editable":false,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"bd9fabef-a976-4d0d-b726-98930f19eb71","menu_item_uuid":null,"name":"Hamburguesa Cl\\u00e1sica","unit_price":5500,"quantity":1,"subtotal":5500,"notes":null,"created_at":"2026-10-17T15:13:59+00:00"},{"uuid":"ebc3a133-dc9b-4cb1-a132-94d008144f56","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":1,"subtotal":7200,"notes":null,"created_at":"2026-10-17T15:13:59+00:00"},{"uuid":"9bd6e96b-6b51-40cc-9b90-51f271d45e7f","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"created_at":"2026-10-17T15:13:59+00:00"}],"subtotal":13700,"tax_amount":2187,"discount_amount":0,"total":13700,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-17T15:13:59+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:13:59+00:00","updated_at":"2026-10-17T15:13:59+00:00"}}	200	1	api/v1/orders/dcf06f08-aa7b-41ba-9f47-b55e6e940c17/confirm	2026-10-18 15:13:59	2026-10-17 15:13:59	2026-10-17 15:14:00	1	1	2026-10-17 15:14:59	\N
79	aa26441e-cbc5-4db8-a852-b741155d394b	5dc64d05683abb8b178abccb31fa2e6466155059b095ec173f9a8ce6d8643933	{"data":{"uuid":"6cfc9534-bba3-4171-a473-4c4bbb1682c2","order_number":"ORD-001-20261017-0003","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:16:29+00:00","updated_at":"2026-10-17T15:16:29+00:00"}}	201	1	api/v1/orders	2026-10-18 15:16:29	2026-10-17 15:16:29	2026-10-17 15:16:29	1	1	2026-10-17 15:17:29	\N
80	7f26482d-cec7-4065-bdc6-ab011d128897	d8c635680437c83732af2aec6592a91be00918502063ae4106c60221a5661c53	{"data":{"uuid":"6cfc9534-bba3-4171-a473-4c4bbb1682c2","order_number":"ORD-001-20261017-0003","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"7a6d63f5-dd9e-4c5a-bc9d-723096ee379f","menu_item_uuid":null,"name":"Hamburguesa Cl\\u00e1sica","unit_price":5500,"quantity":1,"subtotal":5500,"notes":null,"modifiers":[],"created_at":"2026-10-17T15:16:29+00:00"}],"subtotal":5500,"tax_amount":878,"discount_amount":0,"total":5500,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:16:29+00:00","updated_at":"2026-10-17T15:16:29+00:00"}}	201	1	api/v1/orders/6cfc9534-bba3-4171-a473-4c4bbb1682c2/items	2026-10-18 15:16:29	2026-10-17 15:16:29	2026-10-17 15:16:29	1	1	2026-10-17 15:17:29	\N
81	527753e7-6323-4037-bb52-e99bd168e121	c08f90ebd0068bce01ae0057f15e4f841c9000bafc1dd9df8eed472c4fa0d723	{"data":{"uuid":"6cfc9534-bba3-4171-a473-4c4bbb1682c2","order_number":"ORD-001-20261017-0003","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"7a6d63f5-dd9e-4c5a-bc9d-723096ee379f","menu_item_uuid":null,"name":"Hamburguesa Cl\\u00e1sica","unit_price":5500,"quantity":1,"subtotal":5500,"notes":null,"modifiers":[],"created_at":"2026-10-17T15:16:29+00:00"},{"uuid":"86e26acc-ff1d-4a47-b8dd-423396206784","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-17T15:16:29+00:00"}],"subtotal":6500,"tax_amount":1038,"discount_amount":0,"total":6500,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:16:29+00:00","updated_at":"2026-10-17T15:16:29+00:00"}}	201	1	api/v1/orders/6cfc9534-bba3-4171-a473-4c4bbb1682c2/items	2026-10-18 15:16:29	2026-10-17 15:16:29	2026-10-17 15:16:29	1	1	2026-10-17 15:17:29	\N
83	e196c245-fb61-4d46-bc23-d7b06ce96dc7	3d129a6d59e225788ce5e6101fa163e5eb1ae7129db9327bcc06163c449fd68d	{"data":{"uuid":"84e19ad2-e4f6-4bd9-86a4-76d8188cf7ab","order_number":"ORD-001-20261017-0004","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:17:29+00:00","updated_at":"2026-10-17T15:17:29+00:00"}}	201	1	api/v1/orders	2026-10-18 15:17:29	2026-10-17 15:17:29	2026-10-17 15:17:29	1	1	2026-10-17 15:18:29	\N
97	5f7a236e-df0d-4d04-ba79-b8761569c060	3f01ae0f5ee2dda53d168070685be08fdfdb0d69d1aa80bbe428916612ef926b	{"data":{"uuid":"5326a415-d42f-4d41-81cf-f4d6ca584a89","order_number":"ORD-001-20261017-0008","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T16:51:33+00:00","updated_at":"2026-10-17T16:51:33+00:00"}}	201	1	api/v1/orders	2026-10-18 16:51:32	2026-10-17 16:51:32	2026-10-17 16:51:33	1	1	2026-10-17 16:52:32	\N
84	d6558c61-e1c5-4fc7-b100-eecc1de43049	2641514945c2d3cd2553c807df1f7ad53cb040280fcac563040a43249f3e29a2	{"data":{"uuid":"84e19ad2-e4f6-4bd9-86a4-76d8188cf7ab","order_number":"ORD-001-20261017-0004","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"9e4d8f75-19ce-4711-bdb0-6dead292d400","menu_item_uuid":null,"name":"Hamburguesa Cl\\u00e1sica","unit_price":5500,"quantity":1,"subtotal":5500,"notes":null,"modifiers":[],"created_at":"2026-10-17T15:17:29+00:00"}],"subtotal":5500,"tax_amount":878,"discount_amount":0,"total":5500,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:17:29+00:00","updated_at":"2026-10-17T15:17:29+00:00"}}	201	1	api/v1/orders/84e19ad2-e4f6-4bd9-86a4-76d8188cf7ab/items	2026-10-18 15:17:29	2026-10-17 15:17:29	2026-10-17 15:17:29	1	1	2026-10-17 15:18:29	\N
85	4d65f9b2-7e87-40f5-849d-4696166380fa	92d453ec7ac30663ca7b2f9f4c8f072924df1a4851427fecc41efa542e3bba0f	{"data":{"uuid":"84e19ad2-e4f6-4bd9-86a4-76d8188cf7ab","order_number":"ORD-001-20261017-0004","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"confirmed","sync_status":"pending","is_editable":false,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"9e4d8f75-19ce-4711-bdb0-6dead292d400","menu_item_uuid":null,"name":"Hamburguesa Cl\\u00e1sica","unit_price":5500,"quantity":1,"subtotal":5500,"notes":null,"created_at":"2026-10-17T15:17:29+00:00"}],"subtotal":5500,"tax_amount":878,"discount_amount":0,"total":5500,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-17T15:17:29+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:17:29+00:00","updated_at":"2026-10-17T15:17:29+00:00"}}	200	1	api/v1/orders/84e19ad2-e4f6-4bd9-86a4-76d8188cf7ab/confirm	2026-10-18 15:17:29	2026-10-17 15:17:29	2026-10-17 15:17:29	1	1	2026-10-17 15:18:29	\N
86	ffefa7aa-bf5d-4d85-8ef7-5c1b9010a000	4d72d20b0ca9d76a4dec2fe93ca99bcaf2b64f98119bccbdef28c710745c7c2d	{"data":{"uuid":"15129255-33d6-4d66-b7fd-a266bbe1f860","order_number":"ORD-001-20261017-0005","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:19:14+00:00","updated_at":"2026-10-17T15:19:14+00:00"}}	201	1	api/v1/orders	2026-10-18 15:19:14	2026-10-17 15:19:14	2026-10-17 15:19:14	1	1	2026-10-17 15:20:14	\N
87	01fcf3cc-94c9-49bd-9611-00525f768f3e	0b92121758780fee3bad3d7285c07cc28e281deaae58a374ca744d294704c37b	{"data":{"uuid":"15129255-33d6-4d66-b7fd-a266bbe1f860","order_number":"ORD-001-20261017-0005","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"32e67eae-8807-45e6-a857-ca70347e325b","menu_item_uuid":null,"name":"Hamburguesa Cl\\u00e1sica","unit_price":5500,"quantity":1,"subtotal":5500,"notes":null,"modifiers":[],"created_at":"2026-10-17T15:19:14+00:00"}],"subtotal":5500,"tax_amount":878,"discount_amount":0,"total":5500,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:19:14+00:00","updated_at":"2026-10-17T15:19:14+00:00"}}	201	1	api/v1/orders/15129255-33d6-4d66-b7fd-a266bbe1f860/items	2026-10-18 15:19:14	2026-10-17 15:19:14	2026-10-17 15:19:14	1	1	2026-10-17 15:20:14	\N
88	8fc57df6-1d1a-4080-877d-aa633e6409b1	4d5924cbdc9df345dbcfdc6412c0859026d8fbdd7eab985b6559bce14d238c9e	{"data":{"uuid":"15129255-33d6-4d66-b7fd-a266bbe1f860","order_number":"ORD-001-20261017-0005","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"32e67eae-8807-45e6-a857-ca70347e325b","menu_item_uuid":null,"name":"Hamburguesa Cl\\u00e1sica","unit_price":5500,"quantity":1,"subtotal":5500,"notes":null,"modifiers":[],"created_at":"2026-10-17T15:19:14+00:00"},{"uuid":"e876192a-2dac-402b-85e2-1458b7fe1cde","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":1,"subtotal":7200,"notes":null,"modifiers":[],"created_at":"2026-10-17T15:19:14+00:00"}],"subtotal":12700,"tax_amount":2028,"discount_amount":0,"total":12700,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:19:14+00:00","updated_at":"2026-10-17T15:19:14+00:00"}}	201	1	api/v1/orders/15129255-33d6-4d66-b7fd-a266bbe1f860/items	2026-10-18 15:19:14	2026-10-17 15:19:14	2026-10-17 15:19:14	1	1	2026-10-17 15:20:14	\N
96	8e71aab3-4b14-42cb-b967-04c1580cba5a	2c5194078af0e416e9b0fee44ebdec94df2ded96323331136deb802350176173	{"data":{"uuid":"6c66e010-b93b-4c1d-8f21-b3d0f5e9babe","order_number":"ORD-001-20261017-0007","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"confirmed","sync_status":"pending","is_editable":false,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"b5bd218a-4b78-4bab-84ea-f50208f8370e","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":1,"subtotal":7200,"notes":null,"created_at":"2026-10-17T15:54:51+00:00"}],"subtotal":7200,"tax_amount":1150,"discount_amount":0,"total":7200,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-17T15:54:52+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:54:51+00:00","updated_at":"2026-10-17T15:54:52+00:00"}}	200	1	api/v1/orders/6c66e010-b93b-4c1d-8f21-b3d0f5e9babe/confirm	2026-10-18 15:54:52	2026-10-17 15:54:52	2026-10-17 15:54:52	1	1	2026-10-17 15:55:52	\N
89	16b8f2d6-6bfe-4805-9f56-937998143e17	0921e3f1a91be675068859b8d8ce009115a5784b1c7e08e99d9c950dbb5d8424	{"data":{"uuid":"15129255-33d6-4d66-b7fd-a266bbe1f860","order_number":"ORD-001-20261017-0005","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"confirmed","sync_status":"pending","is_editable":false,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"32e67eae-8807-45e6-a857-ca70347e325b","menu_item_uuid":null,"name":"Hamburguesa Cl\\u00e1sica","unit_price":5500,"quantity":1,"subtotal":5500,"notes":null,"created_at":"2026-10-17T15:19:14+00:00"},{"uuid":"e876192a-2dac-402b-85e2-1458b7fe1cde","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":1,"subtotal":7200,"notes":null,"created_at":"2026-10-17T15:19:14+00:00"}],"subtotal":12700,"tax_amount":2028,"discount_amount":0,"total":12700,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-17T15:19:14+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:19:14+00:00","updated_at":"2026-10-17T15:19:14+00:00"}}	200	1	api/v1/orders/15129255-33d6-4d66-b7fd-a266bbe1f860/confirm	2026-10-18 15:19:14	2026-10-17 15:19:14	2026-10-17 15:19:15	1	1	2026-10-17 15:20:14	\N
91	53c44f1a-167a-4158-98e9-d7b7804ae51d	31036a138a07f4b53e80aa4128d937156d5590aea05c2169e11bb9fcb4883374	{"data":{"uuid":"953a0899-7047-41a4-a386-9032159c99a8","order_number":"ORD-001-20261017-0006","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:50:36+00:00","updated_at":"2026-10-17T15:50:36+00:00"}}	201	1	api/v1/orders	2026-10-18 15:50:36	2026-10-17 15:50:36	2026-10-17 15:50:36	1	1	2026-10-17 15:51:36	\N
92	ef07d0ea-d658-43da-ae54-9d8e91395c48	141f9dbb0f6cc1393fe556517b62c7366ab0e388fa5b022c57c5a93c79e2a234	{"data":{"uuid":"953a0899-7047-41a4-a386-9032159c99a8","order_number":"ORD-001-20261017-0006","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"f3e1dfaf-9a9f-4ee0-9aee-acc73b9afe59","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":2,"subtotal":14400,"notes":null,"modifiers":[],"created_at":"2026-10-17T15:50:37+00:00"}],"subtotal":14400,"tax_amount":2299,"discount_amount":0,"total":14400,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:50:36+00:00","updated_at":"2026-10-17T15:50:37+00:00"}}	201	1	api/v1/orders/953a0899-7047-41a4-a386-9032159c99a8/items	2026-10-18 15:50:37	2026-10-17 15:50:37	2026-10-17 15:50:37	1	1	2026-10-17 15:51:37	\N
93	f10bbb38-db02-4153-b607-674757c3aa5d	6803a468926d3a72ef9ebb0cd45713afb1851a38f244efef87858c2308120f6d	{"data":{"uuid":"953a0899-7047-41a4-a386-9032159c99a8","order_number":"ORD-001-20261017-0006","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"confirmed","sync_status":"pending","is_editable":false,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"f3e1dfaf-9a9f-4ee0-9aee-acc73b9afe59","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":2,"subtotal":14400,"notes":null,"created_at":"2026-10-17T15:50:37+00:00"}],"subtotal":14400,"tax_amount":2299,"discount_amount":0,"total":14400,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-17T15:50:37+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:50:36+00:00","updated_at":"2026-10-17T15:50:37+00:00"}}	200	1	api/v1/orders/953a0899-7047-41a4-a386-9032159c99a8/confirm	2026-10-18 15:50:37	2026-10-17 15:50:37	2026-10-17 15:50:37	1	1	2026-10-17 15:51:37	\N
94	0dbe1fe4-2b40-42c3-a39b-07f36d2c6705	bbbb0999192af02a941910df6e60acb08d9d00707f51d210d8610f2a72e71f71	{"data":{"uuid":"6c66e010-b93b-4c1d-8f21-b3d0f5e9babe","order_number":"ORD-001-20261017-0007","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:54:51+00:00","updated_at":"2026-10-17T15:54:51+00:00"}}	201	1	api/v1/orders	2026-10-18 15:54:51	2026-10-17 15:54:51	2026-10-17 15:54:51	1	1	2026-10-17 15:55:51	\N
95	84590da1-893a-4135-ac4d-fa6ced136230	778d53190da28084e5d119877a4510970817259ba018f2d15bf5fdf3692ba196	{"data":{"uuid":"6c66e010-b93b-4c1d-8f21-b3d0f5e9babe","order_number":"ORD-001-20261017-0007","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"b5bd218a-4b78-4bab-84ea-f50208f8370e","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":1,"subtotal":7200,"notes":null,"modifiers":[],"created_at":"2026-10-17T15:54:51+00:00"}],"subtotal":7200,"tax_amount":1150,"discount_amount":0,"total":7200,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T15:54:51+00:00","updated_at":"2026-10-17T15:54:51+00:00"}}	201	1	api/v1/orders/6c66e010-b93b-4c1d-8f21-b3d0f5e9babe/items	2026-10-18 15:54:51	2026-10-17 15:54:51	2026-10-17 15:54:51	1	1	2026-10-17 15:55:51	\N
98	8e269dec-072a-4d02-a847-b4426dbec829	2d122b14040e2dd299e7f7de7b6f03a5f6baa5a6466b10f9d65ab91a8a85df20	{"data":{"uuid":"5326a415-d42f-4d41-81cf-f4d6ca584a89","order_number":"ORD-001-20261017-0008","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"02143e39-cb2e-4737-8b60-8ad852ec5d2f","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-17T16:51:33+00:00"}],"subtotal":1000,"tax_amount":160,"discount_amount":0,"total":1000,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T16:51:33+00:00","updated_at":"2026-10-17T16:51:33+00:00"}}	201	1	api/v1/orders/5326a415-d42f-4d41-81cf-f4d6ca584a89/items	2026-10-18 16:51:33	2026-10-17 16:51:33	2026-10-17 16:51:33	1	1	2026-10-17 16:52:33	\N
99	3b3a910b-9fa0-4b71-bc66-88a8049df045	de0198b75f702f81ecba049abcffa3100d357bd23c8082ceb94bc4ddcd266eb6	{"data":{"uuid":"5326a415-d42f-4d41-81cf-f4d6ca584a89","order_number":"ORD-001-20261017-0008","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"df963b27-9061-4dbc-b6bf-d4e2740a1703","menu_item_uuid":null,"name":"Aros de Cebolla","unit_price":2800,"quantity":1,"subtotal":2800,"notes":null,"modifiers":[],"created_at":"2026-10-17T16:51:33+00:00"},{"uuid":"02143e39-cb2e-4737-8b60-8ad852ec5d2f","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-17T16:51:33+00:00"}],"subtotal":3800,"tax_amount":607,"discount_amount":0,"total":3800,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T16:51:33+00:00","updated_at":"2026-10-17T16:51:33+00:00"}}	201	1	api/v1/orders/5326a415-d42f-4d41-81cf-f4d6ca584a89/items	2026-10-18 16:51:33	2026-10-17 16:51:33	2026-10-17 16:51:33	1	1	2026-10-17 16:52:33	\N
100	705d1e71-4f4f-4dda-8b58-3f4919ffe4af	d6a4ef6ac6c7c9ab9cf1565d55d2f7d61a09ec37a5c84ec5115eb6e70dca93e0	{"data":{"uuid":"5326a415-d42f-4d41-81cf-f4d6ca584a89","order_number":"ORD-001-20261017-0008","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"5b8c8563-6150-4831-a82f-8ca4bfe9deeb","menu_item_uuid":null,"name":"Caldillo de Congrio","unit_price":8900,"quantity":1,"subtotal":8900,"notes":null,"modifiers":[],"created_at":"2026-10-17T16:51:33+00:00"},{"uuid":"df963b27-9061-4dbc-b6bf-d4e2740a1703","menu_item_uuid":null,"name":"Aros de Cebolla","unit_price":2800,"quantity":1,"subtotal":2800,"notes":null,"modifiers":[],"created_at":"2026-10-17T16:51:33+00:00"},{"uuid":"02143e39-cb2e-4737-8b60-8ad852ec5d2f","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-17T16:51:33+00:00"}],"subtotal":12700,"tax_amount":2028,"discount_amount":0,"total":12700,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T16:51:33+00:00","updated_at":"2026-10-17T16:51:33+00:00"}}	201	1	api/v1/orders/5326a415-d42f-4d41-81cf-f4d6ca584a89/items	2026-10-18 16:51:33	2026-10-17 16:51:33	2026-10-17 16:51:33	1	1	2026-10-17 16:52:33	\N
101	e66d1ffd-db6e-439b-91c5-4cf825acbe81	a33f71d05ae467d4eea902f56959cb5e7d27f450fd865983b56e2d2c07c3369f	{"data":{"uuid":"5326a415-d42f-4d41-81cf-f4d6ca584a89","order_number":"ORD-001-20261017-0008","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"5b8c8563-6150-4831-a82f-8ca4bfe9deeb","menu_item_uuid":null,"name":"Caldillo de Congrio","unit_price":8900,"quantity":1,"subtotal":8900,"notes":null,"modifiers":[],"created_at":"2026-10-17T16:51:33+00:00"},{"uuid":"df963b27-9061-4dbc-b6bf-d4e2740a1703","menu_item_uuid":null,"name":"Aros de Cebolla","unit_price":2800,"quantity":1,"subtotal":2800,"notes":null,"modifiers":[],"created_at":"2026-10-17T16:51:33+00:00"},{"uuid":"02143e39-cb2e-4737-8b60-8ad852ec5d2f","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-17T16:51:33+00:00"},{"uuid":"85ad35be-5de0-4dba-b0e6-1defbe673164","menu_item_uuid":null,"name":"Cerveza Artesanal","unit_price":3800,"quantity":1,"subtotal":3800,"notes":null,"modifiers":[],"created_at":"2026-10-17T16:51:33+00:00"}],"subtotal":16500,"tax_amount":2634,"discount_amount":0,"total":16500,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T16:51:33+00:00","updated_at":"2026-10-17T16:51:33+00:00"}}	201	1	api/v1/orders/5326a415-d42f-4d41-81cf-f4d6ca584a89/items	2026-10-18 16:51:33	2026-10-17 16:51:33	2026-10-17 16:51:33	1	1	2026-10-17 16:52:33	\N
113	53474767-6aa0-4950-9b3f-76d232bfca63	fcf1a17c9e794fb30c479063d72692c32261559d4a32af8220cd40f0aea427ce	{"data":{"uuid":"c08fab23-31b1-433a-993c-aa8348c6fe63","order_number":"ORD-001-20261019-0002","type":"dine_in","fulfillment_channel":"onsite","fulfillment_channel_label":"En el local","type_label":"Para servir en mesa","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"table":{"uuid":"4ce6978a-4c19-4f52-bce0-d2e45b92d0c1","table_number":"B-03","area_code":"BAR"},"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T12:36:05+00:00","updated_at":"2026-10-19T12:36:05+00:00"}}	201	1	api/v1/orders	2026-10-20 12:36:05	2026-10-19 12:36:05	2026-10-19 12:36:05	1	1	2026-10-19 12:37:05	\N
217	7fb778f9-f48c-4138-a95b-ad3bb6782cdf	1a366cc7f55e3c6d208761c1ff6ca6681dea892f1e08c4349c1974723e0aa38a	{"uuid":"736047ba-c23a-4b37-bec2-676d5192f06f","id":4,"bill_number":"ORD-001-20261016-0001-1","type":"single","subtotal":8900,"tax_amount":1421,"total":8900,"paid_amount":0,"remaining_amount":8900,"status":"open","idempotent":false,"message":"Bill creada con montos calculados por el servidor"}	201	1	api/v1/bills	2026-10-20 19:11:49	2026-10-19 19:11:49	2026-10-19 19:11:49	1	1	2026-10-19 19:12:49	\N
102	bd532edf-67e1-4354-8702-6063f2a941c5	9cc99712be9a8be57ba05d34bdf28a77b2149a55afa9e51883bf5dd97c18a548	{"data":{"uuid":"5326a415-d42f-4d41-81cf-f4d6ca584a89","order_number":"ORD-001-20261017-0008","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"confirmed","sync_status":"pending","is_editable":false,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"5b8c8563-6150-4831-a82f-8ca4bfe9deeb","menu_item_uuid":null,"name":"Caldillo de Congrio","unit_price":8900,"quantity":1,"subtotal":8900,"notes":null,"created_at":"2026-10-17T16:51:33+00:00"},{"uuid":"df963b27-9061-4dbc-b6bf-d4e2740a1703","menu_item_uuid":null,"name":"Aros de Cebolla","unit_price":2800,"quantity":1,"subtotal":2800,"notes":null,"created_at":"2026-10-17T16:51:33+00:00"},{"uuid":"02143e39-cb2e-4737-8b60-8ad852ec5d2f","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"created_at":"2026-10-17T16:51:33+00:00"},{"uuid":"85ad35be-5de0-4dba-b0e6-1defbe673164","menu_item_uuid":null,"name":"Cerveza Artesanal","unit_price":3800,"quantity":1,"subtotal":3800,"notes":null,"created_at":"2026-10-17T16:51:33+00:00"}],"subtotal":16500,"tax_amount":2634,"discount_amount":0,"total":16500,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-17T16:51:33+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17T16:51:33+00:00","updated_at":"2026-10-17T16:51:33+00:00"}}	200	1	api/v1/orders/5326a415-d42f-4d41-81cf-f4d6ca584a89/confirm	2026-10-18 16:51:33	2026-10-17 16:51:33	2026-10-17 16:51:33	1	1	2026-10-17 16:52:33	\N
103	48e792b4-5891-4379-ac80-525db60e79d3	e5d80a3f42013d78c577e296a19c4fe023fc51406f831ac2d7101e5bce40d9eb	{"data":{"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","order_number":"ORD-001-20261019-0001","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T12:35:05+00:00","updated_at":"2026-10-19T12:35:05+00:00"}}	201	1	api/v1/orders	2026-10-20 12:35:05	2026-10-19 12:35:05	2026-10-19 12:35:05	1	1	2026-10-19 12:36:05	\N
104	c4bfbe58-500a-4b23-9bab-f5124e1af5e9	78eee85726ff05d38ac5df42a745c346a4a687a44c1808a080e671284b5e17e1	{"data":{"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","order_number":"ORD-001-20261019-0001","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:05+00:00"}],"subtotal":1000,"tax_amount":160,"discount_amount":0,"total":1000,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T12:35:05+00:00","updated_at":"2026-10-19T12:35:05+00:00"}}	201	1	api/v1/orders/246376f8-6d91-4963-90ee-d7c6c15603cf/items	2026-10-20 12:35:05	2026-10-19 12:35:05	2026-10-19 12:35:05	1	1	2026-10-19 12:36:05	\N
105	a04022a4-4348-4781-a135-608ce0a04f6f	e828d58b98ca5123922f1de57799924bf7271f3c4519c0ee3bd9903837ddef84	{"data":{"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","order_number":"ORD-001-20261019-0001","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"b52f7738-a75c-40a2-b6c4-598eaa6947cf","menu_item_uuid":null,"name":"Aros de Cebolla","unit_price":2800,"quantity":1,"subtotal":2800,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:05+00:00"},{"uuid":"8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:05+00:00"}],"subtotal":3800,"tax_amount":607,"discount_amount":0,"total":3800,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T12:35:05+00:00","updated_at":"2026-10-19T12:35:05+00:00"}}	201	1	api/v1/orders/246376f8-6d91-4963-90ee-d7c6c15603cf/items	2026-10-20 12:35:05	2026-10-19 12:35:05	2026-10-19 12:35:05	1	1	2026-10-19 12:36:05	\N
110	0f9001e5-154b-47ae-89fe-a9e75797d1a6	eb0f93426b6fa8bcf9d3615d5995b8d5a441b66c5c2f3579469ef1c2d0fa0ed3	{"data":{"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","order_number":"ORD-001-20261019-0001","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"57940fcf-0400-4ddb-a1ec-8dce90a98bb2","menu_item_uuid":null,"name":"Caldillo de Congrio","unit_price":8900,"quantity":1,"subtotal":8900,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"},{"uuid":"2e16d908-2651-4250-99c1-ab0b016f6075","menu_item_uuid":null,"name":"Hamburguesa Cl\\u00e1sica","unit_price":5500,"quantity":1,"subtotal":5500,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"},{"uuid":"3768949b-51a3-4fab-a948-7d55c10be99b","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":1,"subtotal":7200,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"},{"uuid":"b52f7738-a75c-40a2-b6c4-598eaa6947cf","menu_item_uuid":null,"name":"Aros de Cebolla","unit_price":2800,"quantity":1,"subtotal":2800,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:05+00:00"},{"uuid":"92f5ac8f-940a-4485-8cc1-66d7dc7312e0","menu_item_uuid":null,"name":"Ensalada Mixta","unit_price":3200,"quantity":1,"subtotal":3200,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"},{"uuid":"8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:05+00:00"},{"uuid":"7b8fabec-b9c4-408f-9b24-651c178e8f9d","menu_item_uuid":null,"name":"Cerveza Artesanal","unit_price":3800,"quantity":1,"subtotal":3800,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"}],"subtotal":32400,"tax_amount":5173,"discount_amount":0,"total":32400,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T12:35:05+00:00","updated_at":"2026-10-19T12:35:07+00:00"}}	201	1	api/v1/orders/246376f8-6d91-4963-90ee-d7c6c15603cf/items	2026-10-20 12:35:06	2026-10-19 12:35:06	2026-10-19 12:35:07	1	1	2026-10-19 12:36:06	\N
133	1d059462-6a69-4862-a699-7d30e2fb13e6	b4f23c143133aa18e26888a88f173481ab24c5332a379a9cf6a5443798453580	{"uuid":"cae81774-4a91-421e-a422-5fd75b524a69","id":2,"bill_number":"ORD-001-20261019-0002-1","type":"single","subtotal":2500,"tax_amount":399,"total":2500,"paid_amount":0,"remaining_amount":2500,"status":"open","idempotent":false,"message":"Bill creada con montos calculados por el servidor"}	201	1	api/v1/bills	2026-10-20 18:12:04	2026-10-19 18:12:04	2026-10-19 18:12:04	1	1	2026-10-19 18:13:04	\N
106	cab9a27c-6d8e-4454-a823-9997704c9914	bfbdb3b579765f2fcde6fd9fa3bfa06a63f15f0b364d17ede39ccac01583b91a	{"data":{"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","order_number":"ORD-001-20261019-0001","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"57940fcf-0400-4ddb-a1ec-8dce90a98bb2","menu_item_uuid":null,"name":"Caldillo de Congrio","unit_price":8900,"quantity":1,"subtotal":8900,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"},{"uuid":"b52f7738-a75c-40a2-b6c4-598eaa6947cf","menu_item_uuid":null,"name":"Aros de Cebolla","unit_price":2800,"quantity":1,"subtotal":2800,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:05+00:00"},{"uuid":"8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:05+00:00"}],"subtotal":12700,"tax_amount":2028,"discount_amount":0,"total":12700,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T12:35:05+00:00","updated_at":"2026-10-19T12:35:06+00:00"}}	201	1	api/v1/orders/246376f8-6d91-4963-90ee-d7c6c15603cf/items	2026-10-20 12:35:06	2026-10-19 12:35:06	2026-10-19 12:35:06	1	1	2026-10-19 12:36:06	\N
107	ef1c3a7f-76a6-43c3-82d8-6ab68f718497	e7c17b87ee23f50791ec41eb8b488848eab4670db431e0c85e45689fde015e3a	{"data":{"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","order_number":"ORD-001-20261019-0001","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"57940fcf-0400-4ddb-a1ec-8dce90a98bb2","menu_item_uuid":null,"name":"Caldillo de Congrio","unit_price":8900,"quantity":1,"subtotal":8900,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"},{"uuid":"b52f7738-a75c-40a2-b6c4-598eaa6947cf","menu_item_uuid":null,"name":"Aros de Cebolla","unit_price":2800,"quantity":1,"subtotal":2800,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:05+00:00"},{"uuid":"8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:05+00:00"},{"uuid":"7b8fabec-b9c4-408f-9b24-651c178e8f9d","menu_item_uuid":null,"name":"Cerveza Artesanal","unit_price":3800,"quantity":1,"subtotal":3800,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"}],"subtotal":16500,"tax_amount":2634,"discount_amount":0,"total":16500,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T12:35:05+00:00","updated_at":"2026-10-19T12:35:06+00:00"}}	201	1	api/v1/orders/246376f8-6d91-4963-90ee-d7c6c15603cf/items	2026-10-20 12:35:06	2026-10-19 12:35:06	2026-10-19 12:35:06	1	1	2026-10-19 12:36:06	\N
108	5866360a-bfd1-475f-976e-e5f1285620bb	86d7d1478232810fd9b10390aced8bc1e80a7eeb502be17ba67330c22ef00d48	{"data":{"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","order_number":"ORD-001-20261019-0001","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"57940fcf-0400-4ddb-a1ec-8dce90a98bb2","menu_item_uuid":null,"name":"Caldillo de Congrio","unit_price":8900,"quantity":1,"subtotal":8900,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"},{"uuid":"3768949b-51a3-4fab-a948-7d55c10be99b","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":1,"subtotal":7200,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"},{"uuid":"b52f7738-a75c-40a2-b6c4-598eaa6947cf","menu_item_uuid":null,"name":"Aros de Cebolla","unit_price":2800,"quantity":1,"subtotal":2800,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:05+00:00"},{"uuid":"8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:05+00:00"},{"uuid":"7b8fabec-b9c4-408f-9b24-651c178e8f9d","menu_item_uuid":null,"name":"Cerveza Artesanal","unit_price":3800,"quantity":1,"subtotal":3800,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"}],"subtotal":23700,"tax_amount":3784,"discount_amount":0,"total":23700,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T12:35:05+00:00","updated_at":"2026-10-19T12:35:06+00:00"}}	201	1	api/v1/orders/246376f8-6d91-4963-90ee-d7c6c15603cf/items	2026-10-20 12:35:06	2026-10-19 12:35:06	2026-10-19 12:35:06	1	1	2026-10-19 12:36:06	\N
109	e040c99b-d46d-4b36-89ba-c840a75a96b8	f543be9fb339484863e34c7471f71a4813f441452aa0f060b9a8353b23f6b21a	{"data":{"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","order_number":"ORD-001-20261019-0001","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"57940fcf-0400-4ddb-a1ec-8dce90a98bb2","menu_item_uuid":null,"name":"Caldillo de Congrio","unit_price":8900,"quantity":1,"subtotal":8900,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"},{"uuid":"2e16d908-2651-4250-99c1-ab0b016f6075","menu_item_uuid":null,"name":"Hamburguesa Cl\\u00e1sica","unit_price":5500,"quantity":1,"subtotal":5500,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"},{"uuid":"3768949b-51a3-4fab-a948-7d55c10be99b","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":1,"subtotal":7200,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"},{"uuid":"b52f7738-a75c-40a2-b6c4-598eaa6947cf","menu_item_uuid":null,"name":"Aros de Cebolla","unit_price":2800,"quantity":1,"subtotal":2800,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:05+00:00"},{"uuid":"8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:05+00:00"},{"uuid":"7b8fabec-b9c4-408f-9b24-651c178e8f9d","menu_item_uuid":null,"name":"Cerveza Artesanal","unit_price":3800,"quantity":1,"subtotal":3800,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"}],"subtotal":29200,"tax_amount":4662,"discount_amount":0,"total":29200,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T12:35:05+00:00","updated_at":"2026-10-19T12:35:06+00:00"}}	201	1	api/v1/orders/246376f8-6d91-4963-90ee-d7c6c15603cf/items	2026-10-20 12:35:06	2026-10-19 12:35:06	2026-10-19 12:35:06	1	1	2026-10-19 12:36:06	\N
111	165a4f77-ff23-4a5e-9937-833cdeed944b	f639a7fdad3600f2571a172c3a7c92fd86a01b0ccc8a0c04509c9d32a0fa55f9	{"data":{"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","order_number":"ORD-001-20261019-0001","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"1710bb9a-3340-40b6-b54d-35f117dd47e2","menu_item_uuid":null,"name":"Empanadas de Pino (3un)","unit_price":3500,"quantity":1,"subtotal":3500,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:07+00:00"},{"uuid":"57940fcf-0400-4ddb-a1ec-8dce90a98bb2","menu_item_uuid":null,"name":"Caldillo de Congrio","unit_price":8900,"quantity":1,"subtotal":8900,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"},{"uuid":"2e16d908-2651-4250-99c1-ab0b016f6075","menu_item_uuid":null,"name":"Hamburguesa Cl\\u00e1sica","unit_price":5500,"quantity":1,"subtotal":5500,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"},{"uuid":"3768949b-51a3-4fab-a948-7d55c10be99b","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":1,"subtotal":7200,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"},{"uuid":"b52f7738-a75c-40a2-b6c4-598eaa6947cf","menu_item_uuid":null,"name":"Aros de Cebolla","unit_price":2800,"quantity":1,"subtotal":2800,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:05+00:00"},{"uuid":"92f5ac8f-940a-4485-8cc1-66d7dc7312e0","menu_item_uuid":null,"name":"Ensalada Mixta","unit_price":3200,"quantity":1,"subtotal":3200,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"},{"uuid":"8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:05+00:00"},{"uuid":"7b8fabec-b9c4-408f-9b24-651c178e8f9d","menu_item_uuid":null,"name":"Cerveza Artesanal","unit_price":3800,"quantity":1,"subtotal":3800,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:35:06+00:00"}],"subtotal":35900,"tax_amount":5732,"discount_amount":0,"total":35900,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T12:35:05+00:00","updated_at":"2026-10-19T12:35:07+00:00"}}	201	1	api/v1/orders/246376f8-6d91-4963-90ee-d7c6c15603cf/items	2026-10-20 12:35:07	2026-10-19 12:35:07	2026-10-19 12:35:07	1	1	2026-10-19 12:36:07	\N
112	06014285-4079-4ce7-b937-56052cdb1230	e442c954cfbd0c9c93ce0ae84f636dd8248430cb9974086d18bcb1aee33fe555	{"data":{"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","order_number":"ORD-001-20261019-0001","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"confirmed","sync_status":"pending","is_editable":false,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"1710bb9a-3340-40b6-b54d-35f117dd47e2","menu_item_uuid":null,"name":"Empanadas de Pino (3un)","unit_price":3500,"quantity":1,"subtotal":3500,"notes":null,"created_at":"2026-10-19T12:35:07+00:00"},{"uuid":"57940fcf-0400-4ddb-a1ec-8dce90a98bb2","menu_item_uuid":null,"name":"Caldillo de Congrio","unit_price":8900,"quantity":1,"subtotal":8900,"notes":null,"created_at":"2026-10-19T12:35:06+00:00"},{"uuid":"2e16d908-2651-4250-99c1-ab0b016f6075","menu_item_uuid":null,"name":"Hamburguesa Cl\\u00e1sica","unit_price":5500,"quantity":1,"subtotal":5500,"notes":null,"created_at":"2026-10-19T12:35:06+00:00"},{"uuid":"3768949b-51a3-4fab-a948-7d55c10be99b","menu_item_uuid":null,"name":"Hamburguesa Doble","unit_price":7200,"quantity":1,"subtotal":7200,"notes":null,"created_at":"2026-10-19T12:35:06+00:00"},{"uuid":"b52f7738-a75c-40a2-b6c4-598eaa6947cf","menu_item_uuid":null,"name":"Aros de Cebolla","unit_price":2800,"quantity":1,"subtotal":2800,"notes":null,"created_at":"2026-10-19T12:35:05+00:00"},{"uuid":"92f5ac8f-940a-4485-8cc1-66d7dc7312e0","menu_item_uuid":null,"name":"Ensalada Mixta","unit_price":3200,"quantity":1,"subtotal":3200,"notes":null,"created_at":"2026-10-19T12:35:06+00:00"},{"uuid":"8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"created_at":"2026-10-19T12:35:05+00:00"},{"uuid":"7b8fabec-b9c4-408f-9b24-651c178e8f9d","menu_item_uuid":null,"name":"Cerveza Artesanal","unit_price":3800,"quantity":1,"subtotal":3800,"notes":null,"created_at":"2026-10-19T12:35:06+00:00"}],"subtotal":35900,"tax_amount":5732,"discount_amount":0,"total":35900,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-19T12:35:07+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T12:35:05+00:00","updated_at":"2026-10-19T12:35:07+00:00"}}	200	1	api/v1/orders/246376f8-6d91-4963-90ee-d7c6c15603cf/confirm	2026-10-20 12:35:07	2026-10-19 12:35:07	2026-10-19 12:35:07	1	1	2026-10-19 12:36:07	\N
114	e20c71bc-3536-4fa2-ae62-76861663b962	3369fdc0965eb223f17f7094ee8ad28ee9df89e97dac3a5fdb1def178d2eba0d	{"data":{"uuid":"c08fab23-31b1-433a-993c-aa8348c6fe63","order_number":"ORD-001-20261019-0002","type":"dine_in","fulfillment_channel":"onsite","fulfillment_channel_label":"En el local","type_label":"Para servir en mesa","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"table":{"uuid":"4ce6978a-4c19-4f52-bce0-d2e45b92d0c1","table_number":"B-03","area_code":"BAR"},"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"34384e04-f4dc-4442-ad98-32a927689982","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:36:05+00:00"}],"subtotal":1000,"tax_amount":160,"discount_amount":0,"total":1000,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T12:36:05+00:00","updated_at":"2026-10-19T12:36:05+00:00"}}	201	1	api/v1/orders/c08fab23-31b1-433a-993c-aa8348c6fe63/items	2026-10-20 12:36:05	2026-10-19 12:36:05	2026-10-19 12:36:05	1	1	2026-10-19 12:37:05	\N
115	2df5616e-bc01-421c-bfce-349085862511	9f112eb88ecb2cd2e50295945e0e48febeb0de56032843ee12548c056da11ba8	{"data":{"uuid":"c08fab23-31b1-433a-993c-aa8348c6fe63","order_number":"ORD-001-20261019-0002","type":"dine_in","fulfillment_channel":"onsite","fulfillment_channel_label":"En el local","type_label":"Para servir en mesa","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"table":{"uuid":"4ce6978a-4c19-4f52-bce0-d2e45b92d0c1","table_number":"B-03","area_code":"BAR"},"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"0b685d54-bcda-4848-84a4-343086387c1f","menu_item_uuid":null,"name":"Coca-Cola 500ml","unit_price":1500,"quantity":1,"subtotal":1500,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:36:05+00:00"},{"uuid":"34384e04-f4dc-4442-ad98-32a927689982","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-19T12:36:05+00:00"}],"subtotal":2500,"tax_amount":399,"discount_amount":0,"total":2500,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T12:36:05+00:00","updated_at":"2026-10-19T12:36:05+00:00"}}	201	1	api/v1/orders/c08fab23-31b1-433a-993c-aa8348c6fe63/items	2026-10-20 12:36:05	2026-10-19 12:36:05	2026-10-19 12:36:06	1	1	2026-10-19 12:37:05	\N
116	da249e50-a095-406c-8697-bf270fad56e6	a7b673d4fd31675cf2c4fbd5986fafb8a9c0cb76ab5d6d60f7ea700267019801	{"data":{"uuid":"c08fab23-31b1-433a-993c-aa8348c6fe63","order_number":"ORD-001-20261019-0002","type":"dine_in","fulfillment_channel":"onsite","fulfillment_channel_label":"En el local","type_label":"Para servir en mesa","status":"confirmed","sync_status":"pending","is_editable":false,"is_active":true,"table":{"uuid":"4ce6978a-4c19-4f52-bce0-d2e45b92d0c1","table_number":"B-03","area_code":"BAR"},"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"0b685d54-bcda-4848-84a4-343086387c1f","menu_item_uuid":null,"name":"Coca-Cola 500ml","unit_price":1500,"quantity":1,"subtotal":1500,"notes":null,"created_at":"2026-10-19T12:36:05+00:00"},{"uuid":"34384e04-f4dc-4442-ad98-32a927689982","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"created_at":"2026-10-19T12:36:05+00:00"}],"subtotal":2500,"tax_amount":399,"discount_amount":0,"total":2500,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-19T12:36:06+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T12:36:05+00:00","updated_at":"2026-10-19T12:36:06+00:00"}}	200	1	api/v1/orders/c08fab23-31b1-433a-993c-aa8348c6fe63/confirm	2026-10-20 12:36:06	2026-10-19 12:36:06	2026-10-19 12:36:06	1	1	2026-10-19 12:37:06	\N
227	03e6d938-b414-449a-9a79-7ab5e25138e2	cd7012f2440e1fc13c5f3d1ef283179fc10afb3bbb5d3bdba1f0f97aba3f06b1	{"uuid":"522b5b55-93c9-49cd-adda-98846aa007ec","id":1,"bill_number":"ORD-001-20261017-0001-1","type":"single","subtotal":7200,"tax_amount":1150,"total":7200,"paid_amount":0,"remaining_amount":7200,"status":"open","idempotent":false,"message":"Bill creada con montos calculados por el servidor"}	201	1	api/v1/bills	2026-10-20 19:47:08	2026-10-19 19:47:08	2026-10-19 19:47:08	1	1	2026-10-19 19:48:08	\N
178	e5b8f23a-15aa-4e7c-bcdc-fa7bb4cbc6bd	934ff6a0dbc90bea25a8fc3749c0a83294461f3bde59704dd512739b674cc0a2	{"data":{"uuid":"d4e05f80-ad67-4784-8b88-fcbaab3c5675","order_number":"ORD-001-20261019-0004","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T18:40:20+00:00","updated_at":"2026-10-19T18:40:20+00:00"}}	201	1	api/v1/orders	2026-10-20 18:40:20	2026-10-19 18:40:20	2026-10-19 18:40:20	1	1	2026-10-19 18:41:20	\N
180	7e5879de-fd7d-49e7-9ffe-322996842855	af9a605468616b995cf3934b1e03e9d5badeb487f883ad5bb68ab6acf3acb9ff	{"data":{"uuid":"d1fc6d28-f7c3-44d4-8bed-120740597fd8","order_number":"ORD-001-20261019-0005","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T18:40:20+00:00","updated_at":"2026-10-19T18:40:20+00:00"}}	201	1	api/v1/orders	2026-10-20 18:40:20	2026-10-19 18:40:20	2026-10-19 18:40:20	1	1	2026-10-19 18:41:20	\N
233	7e369704-0dc8-491a-a841-99ff59676593	5c42f561146300a1cd301cb100d15d54edc6c22711f1dc7c63b6d0215bbdf668	{"uuid":"ba52ffaa-7c10-42ab-8274-088f540d2b5d","id":5,"bill_number":"ORD-001-20261019-0001-1","type":"single","subtotal":35900,"tax_amount":5732,"total":35900,"paid_amount":0,"remaining_amount":35900,"status":"open","idempotent":false,"message":"Bill creada con montos calculados por el servidor"}	201	1	api/v1/bills	2026-10-20 19:49:53	2026-10-19 19:49:53	2026-10-19 19:49:53	1	1	2026-10-19 19:50:53	\N
235	457153b4-40ec-4a89-88a9-c5447c7abb0c	2e809289f8a2eb61903352ab76b184ce61c0974afb35917d5a9933837f357214	{"uuid":"46b4085a-7fab-458c-8e18-52adaf7023de","id":3,"bill_number":"ORD-001-20261016-0002-1","type":"single","subtotal":3500,"tax_amount":559,"total":3500,"paid_amount":0,"remaining_amount":3500,"status":"open","idempotent":false,"message":"Bill creada con montos calculados por el servidor"}	201	1	api/v1/bills	2026-10-20 19:58:53	2026-10-19 19:58:53	2026-10-19 19:58:53	1	1	2026-10-19 19:59:53	\N
237	b9615ea7-f7ec-4ae5-b550-a3aec171c229	914d57bd6b74a2b2152b178ef476bbd0ed3ce3f0f0c5677a4322d0b95955fb86	{"data":{"uuid":"3d37c1e5-79bb-481a-8dbc-1540c0ec805b","order_number":"ORD-001-20261019-0006","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T20:45:31+00:00","updated_at":"2026-10-19T20:45:31+00:00"}}	201	1	api/v1/orders	2026-10-20 20:45:31	2026-10-19 20:45:31	2026-10-19 20:45:32	1	1	2026-10-19 20:46:31	\N
239	a583843e-752a-4847-9865-da352e0e30a8	de8fa701d05ac1501be4561228f9d02c2c9da2ee1f9e5a4c923e2fbce6104087	{"data":{"uuid":"3d37c1e5-79bb-481a-8dbc-1540c0ec805b","order_number":"ORD-001-20261019-0006","type":"takeout","fulfillment_channel":"pickup","fulfillment_channel_label":"Retiro en tienda","type_label":"Para llevar","status":"confirmed","sync_status":"pending","is_editable":false,"is_active":true,"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"7ebd55c0-dd9d-412b-9da9-2928a05ba6dd","menu_item_uuid":null,"name":"Caldillo de Congrio","unit_price":8900,"quantity":1,"subtotal":8900,"notes":null,"created_at":"2026-10-19T20:45:32+00:00"}],"subtotal":8900,"tax_amount":1421,"discount_amount":0,"total":8900,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-19T20:45:32+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T20:45:31+00:00","updated_at":"2026-10-19T20:45:32+00:00"}}	200	1	api/v1/orders/3d37c1e5-79bb-481a-8dbc-1540c0ec805b/confirm	2026-10-20 20:45:32	2026-10-19 20:45:32	2026-10-19 20:45:32	1	1	2026-10-19 20:46:32	\N
241	c6157b5b-8604-44bd-a25d-48c37d7bbf43	c6a18c2a85d9705dab30a98eb178fa0fdf4c0c467d0aa07f8eaef56229df25d4	{"data":{"uuid":"d9e2d64e-5761-4ada-a32b-a991d87bea4e","payment_number":"PAY-SCL-001-20261019-702D95","method_code":"CASH","amount":1000,"tip_amount":0,"total_amount":1000,"reference_code":null,"status":"completed","idempotency_key":"c6157b5b-8604-44bd-a25d-48c37d7bbf43","notes":null,"paid_at":"2026-10-19T20:46:47+00:00","created_at":"2026-10-19T20:46:47+00:00"}}	201	1	api/v1/billing/payments	2026-10-20 20:46:46	2026-10-19 20:46:46	2026-10-19 20:46:47	1	1	2026-10-19 20:47:46	\N
242	dfd0ec82-79f4-49f0-95e6-d0199609aeec	274394d99271dfd059d6d8c1431c1b5ce4b64cdfb4e5aa692743f3ca1d08a6ca	{"data":{"uuid":"664ef440-e013-40ef-9e89-faf78655487c","payment_number":"PAY-SCL-001-20261019-757636","method_code":"CASH","amount":7900,"tip_amount":0,"total_amount":7900,"reference_code":null,"status":"completed","idempotency_key":"dfd0ec82-79f4-49f0-95e6-d0199609aeec","notes":null,"paid_at":"2026-10-19T20:46:47+00:00","created_at":"2026-10-19T20:46:47+00:00"}}	201	1	api/v1/billing/payments	2026-10-20 20:46:47	2026-10-19 20:46:47	2026-10-19 20:46:47	1	1	2026-10-19 20:47:47	\N
243	499a9925-e51b-4a37-b9f1-383dce5c7ce7	2fa9e25411ddec3d0e2a4c14762e385ce79d4e9b78e018fc1e718cc1207a1555	{"data":{"uuid":"dba8663e-5e80-4275-8c35-d74cb983fa7c","order_number":"ORD-001-20261019-0007","type":"dine_in","fulfillment_channel":"onsite","fulfillment_channel_label":"En el local","type_label":"Para servir en mesa","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"table":{"uuid":"7185d16c-26b2-4806-91a9-3ecf6fde7abb","table_number":"M-01","area_code":"MAIN"},"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[],"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T20:48:31+00:00","updated_at":"2026-10-19T20:48:31+00:00"}}	201	1	api/v1/orders	2026-10-20 20:48:31	2026-10-19 20:48:31	2026-10-19 20:48:32	1	1	2026-10-19 20:49:31	\N
244	bfa5b4d4-baf5-4893-b2ab-c4b14df5bba5	aecdcd9b5192dd7451d5bd488e44af2298fa258fecae6644bb283b28149b5702	{"data":{"uuid":"dba8663e-5e80-4275-8c35-d74cb983fa7c","order_number":"ORD-001-20261019-0007","type":"dine_in","fulfillment_channel":"onsite","fulfillment_channel_label":"En el local","type_label":"Para servir en mesa","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"table":{"uuid":"7185d16c-26b2-4806-91a9-3ecf6fde7abb","table_number":"M-01","area_code":"MAIN"},"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"6d95cf6c-b25f-4acc-bf2f-ec42f431a4f6","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-19T20:48:32+00:00"}],"subtotal":1000,"tax_amount":160,"discount_amount":0,"total":1000,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T20:48:31+00:00","updated_at":"2026-10-19T20:48:32+00:00"}}	201	1	api/v1/orders/dba8663e-5e80-4275-8c35-d74cb983fa7c/items	2026-10-20 20:48:32	2026-10-19 20:48:32	2026-10-19 20:48:32	1	1	2026-10-19 20:49:32	\N
245	84aa164f-656a-4240-b6da-182ca77e4dab	344b7fa06df3eabf19f41fcac7398f5e45a0c69094d0bb7ad5feee99c4dcb36d	{"data":{"uuid":"dba8663e-5e80-4275-8c35-d74cb983fa7c","order_number":"ORD-001-20261019-0007","type":"dine_in","fulfillment_channel":"onsite","fulfillment_channel_label":"En el local","type_label":"Para servir en mesa","status":"draft","sync_status":"pending","is_editable":true,"is_active":true,"table":{"uuid":"7185d16c-26b2-4806-91a9-3ecf6fde7abb","table_number":"M-01","area_code":"MAIN"},"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"d4e3ab42-3ef7-47d0-961e-0d7eb9322088","menu_item_uuid":null,"name":"Coca-Cola 500ml","unit_price":1500,"quantity":1,"subtotal":1500,"notes":null,"modifiers":[],"created_at":"2026-10-19T20:48:32+00:00"},{"uuid":"6d95cf6c-b25f-4acc-bf2f-ec42f431a4f6","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"modifiers":[],"created_at":"2026-10-19T20:48:32+00:00"}],"subtotal":2500,"tax_amount":399,"discount_amount":0,"total":2500,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":null,"served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T20:48:31+00:00","updated_at":"2026-10-19T20:48:32+00:00"}}	201	1	api/v1/orders/dba8663e-5e80-4275-8c35-d74cb983fa7c/items	2026-10-20 20:48:32	2026-10-19 20:48:32	2026-10-19 20:48:32	1	1	2026-10-19 20:49:32	\N
246	98f5a8a2-77ea-4aae-a707-2368be59d0f1	871db295c72d5f5830fb99d1f826f877c139fbf844923ebcd9dffb697f691a13	{"data":{"uuid":"dba8663e-5e80-4275-8c35-d74cb983fa7c","order_number":"ORD-001-20261019-0007","type":"dine_in","fulfillment_channel":"onsite","fulfillment_channel_label":"En el local","type_label":"Para servir en mesa","status":"confirmed","sync_status":"pending","is_editable":false,"is_active":true,"table":{"uuid":"7185d16c-26b2-4806-91a9-3ecf6fde7abb","table_number":"M-01","area_code":"MAIN"},"waiter":{"uuid":"51a7cf85-e65c-4f21-9462-cbc0ee15044c","name":"Admin Principal"},"items":[{"uuid":"d4e3ab42-3ef7-47d0-961e-0d7eb9322088","menu_item_uuid":null,"name":"Coca-Cola 500ml","unit_price":1500,"quantity":1,"subtotal":1500,"notes":null,"created_at":"2026-10-19T20:48:32+00:00"},{"uuid":"6d95cf6c-b25f-4acc-bf2f-ec42f431a4f6","menu_item_uuid":null,"name":"Agua Mineral","unit_price":1000,"quantity":1,"subtotal":1000,"notes":null,"created_at":"2026-10-19T20:48:32+00:00"}],"subtotal":2500,"tax_amount":399,"discount_amount":0,"total":2500,"notes":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"confirmed_at":"2026-10-19T20:48:32+00:00","served_at":null,"picked_up_at":null,"dispatched_at":null,"delivered_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19T20:48:31+00:00","updated_at":"2026-10-19T20:48:32+00:00"}}	200	1	api/v1/orders/dba8663e-5e80-4275-8c35-d74cb983fa7c/confirm	2026-10-20 20:48:32	2026-10-19 20:48:32	2026-10-19 20:48:32	1	1	2026-10-19 20:49:32	\N
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: journal_entries; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.journal_entries (id, uuid, company_id, branch_id, journal_entry_number, entry_date, reference_type, reference_id, description, user_id, created_at, updated_at) FROM stdin;
1	cb60b68a-ca14-42a7-8947-55b48bf06fa0	1	1	JE-20261019-0001	2026-10-19 19:59:08	payment	1	Pago #PAY-SCL-001-20261019-C9188A (CASH)	1	2026-10-19 19:59:08	2026-10-19 19:59:08
2	d0b1c343-850f-4838-a913-49305e74fff0	1	1	JE-20261019-0002	2026-10-19 20:46:47	payment	2	Pago #PAY-SCL-001-20261019-702D95 (CASH)	1	2026-10-19 20:46:47	2026-10-19 20:46:47
3	f38b0b7f-8230-48b4-aef8-8eb1b57fade7	1	1	JE-20261019-0003	2026-10-19 20:46:47	payment	3	Pago #PAY-SCL-001-20261019-757636 (CASH)	1	2026-10-19 20:46:47	2026-10-19 20:46:47
\.


--
-- Data for Name: ledger_entries; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.ledger_entries (id, uuid, company_id, branch_id, journal_entry_id, account_id, debit_amount, credit_amount, description, created_at, updated_at) FROM stdin;
1	6e980eb5-f167-452b-bbb6-f1e1e9993c4c	1	1	1	1	4059	0	Pago #PAY-SCL-001-20261019-C9188A recibido	2026-10-19 19:59:08	2026-10-19 19:59:08
2	5a4e00f0-e258-4b53-8b21-0a9d0a9bde41	1	1	1	7	0	3500	Ingreso proporcional (1)	2026-10-19 19:59:08	2026-10-19 19:59:08
3	db516095-22de-4712-9069-3efecede9ce6	1	1	1	5	0	559	IVA proporcional (1)	2026-10-19 19:59:08	2026-10-19 19:59:08
4	60d737e5-4a49-44c3-8db3-05f305139395	1	1	2	1	1160	0	Pago #PAY-SCL-001-20261019-702D95 recibido	2026-10-19 20:46:47	2026-10-19 20:46:47
5	d5f9d6bf-92b9-43ee-8ca5-a9add929a83a	1	1	2	7	0	1000	Ingreso proporcional (0.1123595505618)	2026-10-19 20:46:47	2026-10-19 20:46:47
6	6df28a01-6d01-4883-affe-2a5ece12cd37	1	1	2	5	0	160	IVA proporcional (0.1123595505618)	2026-10-19 20:46:47	2026-10-19 20:46:47
7	96a16f1a-aca0-4c90-a741-cbe660ad6034	1	1	3	1	9161	0	Pago #PAY-SCL-001-20261019-757636 recibido	2026-10-19 20:46:47	2026-10-19 20:46:47
8	9f9bb67b-9974-429a-abb7-c6d5163dc5c4	1	1	3	7	0	7900	Ingreso proporcional (0.8876404494382)	2026-10-19 20:46:47	2026-10-19 20:46:47
9	04443115-db67-41f8-bce9-3945bd5eb6ed	1	1	3	5	0	1261	IVA proporcional (0.8876404494382)	2026-10-19 20:46:47	2026-10-19 20:46:47
\.


--
-- Data for Name: menu_activations; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.menu_activations (id, uuid, menu_id, channel_type, days_of_week, time_from, time_to, priority, is_active, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: menu_item_products; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.menu_item_products (id, uuid, menu_item_id, product_id, quantity, is_substitutable, created_at, updated_at) FROM stdin;
1	df005794-f51e-4e45-ade6-fc8869178dc9	1	6	1	f	2026-10-12 00:16:45	2026-10-12 00:16:45
2	f81e3487-ee56-4300-8d40-b711d593c82c	1	9	1	t	2026-10-12 00:16:45	2026-10-12 00:16:45
3	65e8922b-9cb2-4b1d-b6f3-a160a289f3cc	1	12	1	t	2026-10-12 00:16:45	2026-10-12 00:16:45
4	15881259-36c9-4282-a21a-254d4c8b7433	2	7	1	f	2026-10-12 00:16:45	2026-10-12 00:16:45
5	122f133e-41a7-47d5-b8ae-9ec573e0ffd7	2	9	1	t	2026-10-12 00:16:45	2026-10-12 00:16:45
6	55c105f9-78bd-4409-9de8-d1d46ecee585	2	12	1	t	2026-10-12 00:16:45	2026-10-12 00:16:45
7	742561fb-5e82-4a12-b4fd-a44a60f8d4b5	3	6	4	f	2026-10-12 00:16:45	2026-10-12 00:16:45
8	ee7211a6-98fe-4e4a-a636-5ce9631bf48d	3	9	2	t	2026-10-12 00:16:45	2026-10-12 00:16:45
9	02b9848b-4253-45f3-9376-f163b7dd456a	3	12	4	t	2026-10-12 00:16:45	2026-10-12 00:16:45
\.


--
-- Data for Name: menu_item_replacement_rules; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.menu_item_replacement_rules (id, uuid, company_id, branch_id, menu_item_id, target_product_id, rule_type, allowed_product_id, allowed_category_id, max_price_delta, requires_authorization, priority, is_active, description_translations, created_at, updated_at, deleted_at) FROM stdin;
1	29f0d7f8-52e1-4d1a-be56-cf1a1880dfd5	1	\N	1	12	allowed_category	\N	5	2000	f	1	t	{"es": "Permite cambiar Coca-Cola por cualquier bebida", "zh": "允许将可口可乐换成任何饮料"}	2026-10-12 00:16:45	2026-10-12 00:16:45	\N
2	115352e2-b66c-490e-8697-65dee6b5b2ef	1	\N	1	9	allowed_category	\N	4	1500	f	1	t	{"es": "Permite cambiar papas fritas por cualquier acompañamiento", "zh": "允许将薯条换成任何配菜"}	2026-10-12 00:16:45	2026-10-12 00:16:45	\N
\.


--
-- Data for Name: menu_items; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.menu_items (id, uuid, company_id, branch_id, product_id, base_price, discount_amount, is_active, created_at, updated_at, deleted_at) FROM stdin;
1	ee50c1bd-01c7-45e4-9287-2834a51b15e2	1	\N	20	8500	1000.00	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N
2	87449b0b-281c-462a-b16a-258ecdbbe48d	1	\N	21	10200	1000.00	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N
3	b472bb2a-dc3d-4036-8044-474986989025	1	\N	22	28000	3500.00	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N
\.


--
-- Data for Name: menu_products; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.menu_products (id, uuid, menu_id, product_id, "position", is_available, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: menus; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.menus (id, uuid, company_id, branch_id, name, description, price_list_id, is_default, is_active, created_at, updated_at, deleted_at) FROM stdin;
1	3b21a370-a977-4a7c-ae09-b4219467cfe1	1	1	Carta default - Sucursal Centro	Carta default generada automáticamente por el sistema	1	t	t	2026-10-12 00:16:42	2026-10-12 00:16:42	\N
2	bd247ad4-ef8c-4f9b-8ac4-a473c7860704	1	2	Carta default - Sucursal Providencia	Carta default generada automáticamente por el sistema	2	t	t	2026-10-12 00:16:42	2026-10-12 00:16:42	\N
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_08_08_041931_create_personal_access_tokens_table	1
5	2026_08_08_042305_create_permission_tables	1
6	2026_08_09_000001_create_companies_table	1
7	2026_08_09_000002_create_branches_table	1
8	2026_08_09_000003_create_terminals_table	1
9	2026_08_09_000004_add_tenant_fields_to_users_table	1
10	2026_08_09_225250_fix_restaurant_tables_area_code_unique_constraint	1
11	2026_08_10_000001_create_categories_table	1
12	2026_08_10_000002_create_products_table	1
13	2026_08_10_000003_create_menu_items_table	1
14	2026_08_10_000004_create_menu_item_products_table	1
15	2026_08_10_000005_create_menu_item_replacement_rules_table	1
16	2026_08_10_100000_create_restaurant_tables_table	1
17	2026_08_10_100001_fix_restaurant_tables_area_code_unique_constraint	1
18	2026_08_11_001_create_orders_table	1
19	2026_08_11_002_create_order_items_table	1
20	2026_08_11_003_create_order_item_modifiers_table	1
21	2026_08_12_021148_add_cook_and_priority_to_orders_table	1
22	2026_08_12_025957_create_inventory_tables	1
23	2026_08_13_030207_create_cash_sessions_table	1
24	2026_08_13_030207_create_payment_methods_table	1
25	2026_08_13_030208_create_bills_table	1
26	2026_08_13_030208_create_payments_table	1
27	2026_08_13_040505_create_raw_ingredients_table	1
28	2026_08_13_040506_create_product_recipes_table	1
29	2026_08_13_040506_create_raw_ingredient_purchases_table	1
30	2026_08_13_040531_create_recipe_items_table	1
31	2026_08_13_215444_add_product_id_to_order_items_table	1
32	2026_08_13_224453_create_printers_table	1
33	2026_08_13_224454_create_printer_station_mappings_table	1
34	2026_08_13_224455_create_print_jobs_table	1
35	2026_08_14_000307_create_cash_registers_table	1
36	2026_08_14_000308_add_register_id_to_cash_sessions_table	1
37	2026_08_14_000309_create_cash_movements_table	1
38	2026_08_14_000310_create_cash_counts_table	1
39	2026_08_14_031721_create_dte_documents_table	1
40	2026_08_14_031721_create_dte_folio_ranges_table	1
41	2026_08_14_031722_create_dte_certificates_table	1
42	2026_08_15_000001_create_taxes_table	1
43	2026_08_15_000002_add_tax_id_to_products_categories	1
44	2026_08_15_000003_migrate_tax_data	1
45	2026_08_15_000004_add_tax_fields_to_order_items	1
46	2026_08_15_100001_add_sync_columns_to_tables	1
47	2026_08_15_100002_create_sync_queue_table	1
48	2026_08_15_100003_create_sync_log_table	1
49	2026_08_15_100004_add_conflict_data_to_sync_queue	1
50	2026_08_15_200000_create_idempotency_keys_table	1
51	2026_08_15_210000_create_audit_logs_table	1
52	2026_08_18_000001_create_tip_policies_table	1
53	2026_08_18_000002_create_tip_payouts_table	1
54	2026_08_21_042105_make_menu_item_id_nullable_in_order_items	1
55	2026_08_24_000001_add_claim_fields_to_print_jobs	1
56	2026_08_24_200000_add_parent_id_to_categories	1
57	2026_08_24_210000_create_price_lists_table	1
58	2026_08_24_210001_create_product_prices_table	1
59	2026_08_24_220000_create_menus_table	1
60	2026_08_24_220001_create_menu_activations_table	1
61	2026_08_24_220002_create_menu_products_table	1
62	2026_08_25_010000_add_timezone_to_branches	1
63	2026_08_29_180806_create_company_capabilities_table	1
64	2026_08_29_182650_add_super_admin_role_to_users_table	1
65	2026_08_29_184338_seed_default_capabilities_for_existing_companies	1
66	2026_08_31_000001_add_fulfillment_fields_to_orders_table	1
67	2026_08_31_100000_add_fulfillment_channel_to_orders_table	1
68	2026_08_31_200000_add_fulfillment_timestamps_to_orders_table	1
69	2026_08_31_300000_update_orders_status_check_constraint	1
70	2026_08_31_400000_create_accounts_table	1
71	2026_08_31_400001_create_journal_entries_table	1
72	2026_08_31_400002_create_ledger_entries_table	1
73	2026_08_31_400003_seed_default_accounts	1
74	2026_08_31_500000_create_refunds_table	1
75	2026_08_31_600000_update_payments_status_check_constraint	1
76	2026_08_31_700000_add_gift_cards_and_discount_accounts	1
77	2026_09_01_000000_add_unique_constraint_to_journal_entries_reference	1
78	2026_09_07_100000_add_currency_config_to_company_settings	1
79	2026_09_15_000001_scope_idempotency_keys_to_tenant	1
80	2026_09_15_000002_scope_payments_idempotency_key_to_tenant	1
81	2026_09_15_000003_scope_journal_entry_number_to_tenant	1
82	2026_09_15_000004_add_chilean_model_fields_to_orders	1
83	2026_09_17_000001_convert_decimal_to_integer	1
84	2026_09_17_000002_convert_sqlite_real_to_integer	1
85	2026_09_17_120001_add_idempotency_key_to_bills	1
86	2026_09_21_000000_fix_settings_json_decode_in_migrations	1
87	2026_09_21_000002_add_money_integrity_constraints	1
88	2026_09_23_162330_add_processing_until_to_idempotency_keys_table	1
89	2026_09_24_192105_add_lock_token_to_idempotency_keys_table	1
90	2026_09_29_172608_create_default_notes_table	1
91	2026_10_02_123000_create_raw_ingredient_movements_table	1
92	2026_10_02_140000_drop_inventory_tables	1
93	2026_10_03_110000_rename_can_manage_inventory_to_can_manage_recipes	1
94	2026_10_04_100000_add_document_fields_to_raw_ingredient_purchases	1
95	2026_10_04_200000_create_dining_zones_table	1
96	2026_10_04_200001_add_floor_plan_fields_to_restaurant_tables	1
97	2026_10_04_200002_seed_dining_zones_from_area_codes	1
98	2026_10_11_000000_add_payload_hash_to_payments	1
99	2026_10_11_000001_update_bills_idempotency_scope_and_hash	1
100	2026_10_11_000002_add_lease_fields_to_sync_queue	1
101	2026_10_11_030000_add_version_and_idempotency_to_recipes	1
102	2026_10_11_040000_add_original_unit_audit_to_recipes	1
103	2026_10_11_050000_add_cost_audit_to_movements	1
104	2026_10_11_060000_add_stock_availability_fields	1
105	2026_10_11_070000_add_syncable_columns_to_orders	1
106	2026_10_12_191804_update_idempotency_keys_unique_index_to_include_branch	2
\.


--
-- Data for Name: model_has_permissions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.model_has_permissions (permission_id, model_type, model_id) FROM stdin;
\.


--
-- Data for Name: model_has_roles; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.model_has_roles (role_id, model_type, model_id) FROM stdin;
\.


--
-- Data for Name: order_item_modifiers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.order_item_modifiers (id, order_item_id, original_product_id, substitute_product_id, added_product_id, price_adjustment, reason, requires_authorization, authorized_by, created_at, updated_at, sync_status, version, last_synced_at, offline_id) FROM stdin;
\.


--
-- Data for Name: order_items; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.order_items (id, uuid, company_id, order_id, menu_item_id, name_snapshot, unit_price_snapshot, quantity, notes, subtotal, created_at, updated_at, product_id, tax_amount, tax_rate_snapshot, tax_name_snapshot, sync_status, version, last_synced_at, offline_id) FROM stdin;
\.


--
-- Data for Name: orders; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.orders (id, uuid, company_id, branch_id, order_number, type, status, table_id, waiter_id, cashier_id, subtotal, tax_amount, discount_amount, total, notes, confirmed_at, served_at, paid_at, closed_at, cancelled_at, cancellation_reason, created_at, updated_at, assigned_cook_id, priority, sync_status, version, last_synced_at, offline_id, customer_name, customer_phone, pickup_at, delivery_address, delivery_notes, fulfillment_channel, picked_up_at, dispatched_at, delivered_at, subtotal_gross, net_amount, tip_amount, amount_due) FROM stdin;
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: payment_methods; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.payment_methods (id, uuid, company_id, branch_id, code, name_translations, type, icon, max_amount, requires_reference, is_active, sort_order, created_at, updated_at) FROM stdin;
1	6eec4983-7d06-4a54-b3b5-dcb1b7846b82	1	1	CASH	{"es": "Efectivo", "zh": "现金"}	cash	💵	\N	f	t	1	2026-10-12 00:16:45	2026-10-12 00:16:45
2	44d85c1a-a081-4744-8016-04cf7e096be8	1	1	CARD	{"es": "Tarjeta de Crédito/Débito", "zh": "信用卡/借记卡"}	card	💳	\N	t	t	2	2026-10-12 00:16:45	2026-10-12 00:16:45
3	a12b0665-3d27-491f-a157-dd641fccd86e	1	1	TRANSFER	{"es": "Transferencia Bancaria", "zh": "银行转账"}	transfer	🏦	\N	t	t	3	2026-10-12 00:16:45	2026-10-12 00:16:45
4	28427a88-ce43-4465-8381-2fd124ffd4ff	1	1	GIFT_CARD	{"es": "Tarjeta de Regalo", "zh": "礼品卡"}	gift_card	🎁	\N	t	t	4	2026-10-12 00:16:45	2026-10-12 00:16:45
\.


--
-- Data for Name: payments; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.payments (id, uuid, company_id, branch_id, order_id, bill_id, cash_session_id, payment_method_id, user_id, payment_number, method_code, amount, tip_amount, total_amount, reference_code, status, idempotency_key, notes, paid_at, created_at, updated_at, deleted_at, sync_status, version, last_synced_at, offline_id, payload_hash) FROM stdin;
\.


--
-- Data for Name: permissions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.permissions (id, name, guard_name, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: personal_access_tokens; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.personal_access_tokens (id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: price_lists; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.price_lists (id, uuid, company_id, branch_id, name, display_name, channel_type, currency, is_default, is_active, created_at, updated_at, deleted_at) FROM stdin;
1	da4de8f4-ac9c-4c73-ba9d-ce6942647507	1	1	default_sucursal-centro	Lista default - Sucursal Centro	\N	CLP	t	t	2026-10-12 00:16:42	2026-10-12 00:16:42	\N
2	9bf01706-7b35-4e92-b084-cb386302e4f0	1	2	default_sucursal-providencia	Lista default - Sucursal Providencia	\N	CLP	t	t	2026-10-12 00:16:42	2026-10-12 00:16:42	\N
\.


--
-- Data for Name: print_jobs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.print_jobs (id, uuid, company_id, branch_id, printer_id, job_type, order_id, escpos_bytes, status, attempts, max_attempts, error_message, printed_at, created_at, updated_at, deleted_at, claimed_by, claimed_at) FROM stdin;
\.


--
-- Data for Name: printer_station_mappings; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.printer_station_mappings (id, uuid, company_id, branch_id, printer_id, category_id, product_keywords, priority, is_active, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: printers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.printers (id, uuid, company_id, branch_id, name, type, connection_type, host, port, device_path, paper_width, auto_cut, open_drawer_on_print, is_active, last_printed_at, print_count, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: product_prices; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.product_prices (id, uuid, product_id, price_list_id, price, currency, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: product_recipes; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.product_recipes (id, uuid, company_id, product_id, description, yield_servings, total_recipe_cost, created_at, updated_at, deleted_at, version) FROM stdin;
\.


--
-- Data for Name: products; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.products (id, uuid, company_id, branch_id, category_id, sku, name_translations, description_translations, base_price, tax_rate, is_combo, kitchen_zone_id, is_active, created_at, updated_at, deleted_at, tax_id) FROM stdin;
1	694c2646-124b-4d64-93b5-fb5697f13846	1	\N	1	ENT-001	{"es": "Empanadas de Pino (3un)", "zh": "智利肉馅卷饼 (3个)"}	{"es": "Descripción de Empanadas de Pino (3un)", "zh": "智利肉馅卷饼 (3个)的描述"}	3500	19.00	f	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
2	399f5e08-d987-40a6-86a4-1f81104ce00b	1	\N	1	ENT-002	{"es": "Sopaipillas (6un)", "zh": "炸南瓜饼 (6个)"}	{"es": "Descripción de Sopaipillas (6un)", "zh": "炸南瓜饼 (6个)的描述"}	2800	19.00	f	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
3	3926cad7-4a75-4f9a-a539-8f2d7d0a3ac2	1	\N	2	PRI-001	{"es": "Lomo a lo Pobre", "zh": "智利牛排配薯条"}	{"es": "Descripción de Lomo a lo Pobre", "zh": "智利牛排配薯条的描述"}	9800	19.00	f	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
4	fab991bd-829a-4eb1-acb5-2d05c037f887	1	\N	2	PRI-002	{"es": "Pastel de Choclo", "zh": "智利玉米派"}	{"es": "Descripción de Pastel de Choclo", "zh": "智利玉米派的描述"}	7500	19.00	f	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
5	edd0c74c-9b67-4909-8523-14a8d303ddf3	1	\N	2	PRI-003	{"es": "Caldillo de Congrio", "zh": "智利鳗鱼汤"}	{"es": "Descripción de Caldillo de Congrio", "zh": "智利鳗鱼汤的描述"}	8900	19.00	f	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
6	2c10b4e6-f980-4358-a2d0-d9d44394c173	1	\N	3	HAM-001	{"es": "Hamburguesa Clásica", "zh": "经典汉堡"}	{"es": "Descripción de Hamburguesa Clásica", "zh": "经典汉堡的描述"}	5500	19.00	f	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
7	1d7dec67-e48e-41c7-825d-b2300715f121	1	\N	3	HAM-002	{"es": "Hamburguesa Doble", "zh": "双层汉堡"}	{"es": "Descripción de Hamburguesa Doble", "zh": "双层汉堡的描述"}	7200	19.00	f	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
8	747c2aef-ec74-4bc9-86e3-19cede303c9b	1	\N	3	HAM-003	{"es": "Hamburguesa Vegetariana", "zh": "素食汉堡"}	{"es": "Descripción de Hamburguesa Vegetariana", "zh": "素食汉堡的描述"}	6800	19.00	f	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
9	3cde360f-37ea-4f93-86f7-ea6c24955a82	1	\N	4	ACO-001	{"es": "Papas Fritas", "zh": "薯条"}	{"es": "Descripción de Papas Fritas", "zh": "薯条的描述"}	2500	19.00	f	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
10	ec2788a8-3791-4296-992e-78054fcaa79f	1	\N	4	ACO-002	{"es": "Aros de Cebolla", "zh": "洋葱圈"}	{"es": "Descripción de Aros de Cebolla", "zh": "洋葱圈的描述"}	2800	19.00	f	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
11	fb70f703-beee-4f1f-ad02-9688280772a7	1	\N	4	ACO-003	{"es": "Ensalada Mixta", "zh": "混合沙拉"}	{"es": "Descripción de Ensalada Mixta", "zh": "混合沙拉的描述"}	3200	19.00	f	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
12	d366b6d9-b67f-45cc-bd13-6b20177be0bf	1	\N	5	BEB-001	{"es": "Coca-Cola 500ml", "zh": "可口可乐 500毫升"}	{"es": "Descripción de Coca-Cola 500ml", "zh": "可口可乐 500毫升的描述"}	1500	19.00	f	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
13	66819abd-5c2e-41c7-b9d4-a36c75abb526	1	\N	5	BEB-002	{"es": "Sprite 500ml", "zh": "雪碧 500毫升"}	{"es": "Descripción de Sprite 500ml", "zh": "雪碧 500毫升的描述"}	1500	19.00	f	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
14	9cf04433-61a4-4f80-963c-238449ee2f46	1	\N	5	BEB-003	{"es": "Jugo Natural", "zh": "天然果汁"}	{"es": "Descripción de Jugo Natural", "zh": "天然果汁的描述"}	2200	19.00	f	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
15	957363d0-7b57-448f-a7f8-3310d8ca75cc	1	\N	5	BEB-004	{"es": "Agua Mineral", "zh": "矿泉水"}	{"es": "Descripción de Agua Mineral", "zh": "矿泉水的描述"}	1000	19.00	f	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
16	8d2ecd13-53cf-4e67-b9e4-ad8b378d40f9	1	\N	5	BEB-005	{"es": "Cerveza Artesanal", "zh": "精酿啤酒"}	{"es": "Descripción de Cerveza Artesanal", "zh": "精酿啤酒的描述"}	3800	19.00	f	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
17	b29289e6-8ab9-451d-b919-f1c8e817ec60	1	\N	6	POS-001	{"es": "Helado 2 Bolas", "zh": "两球冰淇淋"}	{"es": "Descripción de Helado 2 Bolas", "zh": "两球冰淇淋的描述"}	2800	19.00	f	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
18	52af9bb3-22bf-4e53-adfb-f67db4471a26	1	\N	6	POS-002	{"es": "Tres Leches", "zh": "三奶蛋糕"}	{"es": "Descripción de Tres Leches", "zh": "三奶蛋糕的描述"}	3500	19.00	f	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
19	bc074e36-e69b-450d-828c-c040651686d7	1	\N	6	POS-003	{"es": "Tiramisú", "zh": "提拉米苏"}	{"es": "Descripción de Tiramisú", "zh": "提拉米苏的描述"}	4200	19.00	f	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
20	58e95540-2cc1-49b5-b678-462145542b60	1	\N	7	COMBO-001	{"es": "Combo Hamburguesa Clásica", "zh": "经典汉堡套餐"}	{"es": "Combo que incluye 3 productos", "zh": "包含 3 种产品的套餐"}	0	19.00	t	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
21	a23bf12f-6a9d-4d91-a92f-7d6aeaaf98d8	1	\N	7	COMBO-002	{"es": "Combo Hamburguesa Doble", "zh": "双层汉堡套餐"}	{"es": "Combo que incluye 3 productos", "zh": "包含 3 种产品的套餐"}	0	19.00	t	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
22	42d04ae5-4bf8-4553-b9cf-7ecf8bebf19a	1	\N	7	COMBO-003	{"es": "Combo Familiar (4 personas)", "zh": "家庭套餐 (4人)"}	{"es": "Combo que incluye 3 productos", "zh": "包含 3 种产品的套餐"}	0	19.00	t	\N	t	2026-10-12 00:16:45	2026-10-12 00:16:45	\N	\N
\.


--
-- Data for Name: raw_ingredient_movements; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.raw_ingredient_movements (id, uuid, company_id, branch_id, raw_ingredient_id, type, quantity_base, balance_after, reference_type, reference_id, user_id, reason, created_at, updated_at, idempotency_key, order_item_uuid, recipe_version, original_quantity, original_unit, cost_per_base_unit_at_transaction, total_cost_at_transaction) FROM stdin;
\.


--
-- Data for Name: raw_ingredient_purchases; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.raw_ingredient_purchases (id, uuid, raw_ingredient_id, user_id, purchase_unit_name, purchase_quantity, conversion_factor_to_base, total_base_quantity_added, total_purchase_cost, calculated_cost_per_base_unit, purchase_date, created_at, updated_at, document_type, document_number, supplier_name, supplier_rut) FROM stdin;
\.


--
-- Data for Name: raw_ingredients; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.raw_ingredients (id, uuid, company_id, branch_id, sku, name_translations, dimension_type, base_unit, current_stock_base, minimum_stock_base, cost_per_base_unit, is_active, created_at, updated_at, deleted_at, committed_stock_base, pending_incoming_base, reorder_level_base) FROM stdin;
\.


--
-- Data for Name: recipe_items; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.recipe_items (id, uuid, recipe_id, raw_ingredient_id, quantity_base_unit, waste_percentage, effective_discount_base_quantity, calculated_item_cost, created_at, updated_at, original_quantity, original_unit) FROM stdin;
\.


--
-- Data for Name: refunds; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.refunds (id, uuid, company_id, branch_id, payment_id, refund_number, amount, status, reason, processed_at, processed_by, journal_entry_id, notes, idempotency_key, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: restaurant_tables; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.restaurant_tables (id, uuid, company_id, branch_id, area_code, area_name_translations, table_number, capacity, status, current_order_id, created_at, updated_at, deleted_at, sync_status, version, last_synced_at, offline_id, zone_id, position_x, position_y, rotation, shape, width, height) FROM stdin;
2	95409893-027e-4a41-9a36-8717303c9406	1	1	MAIN	{"es": "Salón Principal", "zh": "主厅"}	M-02	4	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
3	7c23b2dd-ef37-45f7-9078-96a7cc44d86f	1	1	MAIN	{"es": "Salón Principal", "zh": "主厅"}	M-03	6	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
4	3ca1e5ba-36aa-4308-b2c9-ec13fd0fa661	1	1	MAIN	{"es": "Salón Principal", "zh": "主厅"}	M-04	2	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
5	dfa6557c-3f97-4125-9252-42bb452316f4	1	1	MAIN	{"es": "Salón Principal", "zh": "主厅"}	M-05	8	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
6	f81c57c1-4e4a-432b-81af-a969732cf572	1	1	TERRAZA	{"es": "Terraza", "zh": "露台"}	T-01	2	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
7	30151c3f-8923-4a31-8ab8-0169219e1d9e	1	1	TERRAZA	{"es": "Terraza", "zh": "露台"}	T-02	2	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
8	f22bd329-f010-4022-b9d6-63be0c4b7b07	1	1	TERRAZA	{"es": "Terraza", "zh": "露台"}	T-03	4	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
9	0259d8d6-39f2-4b0c-b226-503525633414	1	1	TERRAZA	{"es": "Terraza", "zh": "露台"}	T-04	4	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
10	c3c47483-46a8-428b-825a-538af341c895	1	1	VIP	{"es": "Área VIP", "zh": "贵宾区"}	VIP-01	8	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
11	62d596c7-6ad7-4d77-8bde-0fdab94e012d	1	1	VIP	{"es": "Área VIP", "zh": "贵宾区"}	VIP-02	12	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
15	1d438266-4bcc-472d-ba36-ce0507e60773	1	2	MAIN	{"es": "Salón Principal", "zh": "主厅"}	M-01	4	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
16	a5d7ee3e-1260-4845-8c2a-ed2cb32a7ebc	1	2	MAIN	{"es": "Salón Principal", "zh": "主厅"}	M-02	4	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
17	4a1daaf7-fa77-430d-8cc1-f91b8735cf67	1	2	MAIN	{"es": "Salón Principal", "zh": "主厅"}	M-03	6	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
18	27b66538-13ec-4a4f-b945-0e4fb80e7f08	1	2	MAIN	{"es": "Salón Principal", "zh": "主厅"}	M-04	2	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
19	8a100194-36de-4a24-9d1c-aa01998dfa33	1	2	MAIN	{"es": "Salón Principal", "zh": "主厅"}	M-05	8	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
20	0d7ba1be-2213-4921-bd5c-f2b008594846	1	2	TERRAZA	{"es": "Terraza", "zh": "露台"}	T-01	2	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
21	681116e3-da65-4aa3-82f6-3b731d80f47d	1	2	TERRAZA	{"es": "Terraza", "zh": "露台"}	T-02	2	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
22	16a2e976-fc10-4259-95a9-5eb2a4554ddc	1	2	TERRAZA	{"es": "Terraza", "zh": "露台"}	T-03	4	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
23	fde4c05c-742e-4f05-88e4-cf02f68f1b18	1	2	TERRAZA	{"es": "Terraza", "zh": "露台"}	T-04	4	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
24	04dd6cd9-7004-4d2b-8905-947175b9f322	1	2	VIP	{"es": "Área VIP", "zh": "贵宾区"}	VIP-01	8	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
25	24c30bbb-3a95-43a6-82ad-98b1faeee9a8	1	2	VIP	{"es": "Área VIP", "zh": "贵宾区"}	VIP-02	12	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
26	1b708d54-4d47-4fe8-9215-869af1c87ba0	1	2	BAR	{"es": "Barra", "zh": "吧台"}	B-01	2	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
27	98d9ab3b-c6c8-4bef-ba0b-163039a50198	1	2	BAR	{"es": "Barra", "zh": "吧台"}	B-02	2	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
28	dbca6689-fac3-429d-bcc3-f691e33e7374	1	2	BAR	{"es": "Barra", "zh": "吧台"}	B-03	2	available	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	pending	1	\N	\N	\N	0	0	0	square	\N	\N
14	4ce6978a-4c19-4f52-bce0-d2e45b92d0c1	1	1	BAR	{"es": "Barra", "zh": "吧台"}	B-03	2	available	\N	2026-10-12 00:16:46	2026-10-19 18:29:34	\N	pending	3	\N	\N	\N	0	0	0	square	\N	\N
12	f8c4272e-f51c-485f-9c1f-2fc14dd7744d	1	1	BAR	{"es": "Barra", "zh": "吧台"}	B-01	2	available	\N	2026-10-12 00:16:46	2026-10-19 19:37:38	\N	pending	3	\N	\N	\N	0	0	0	square	\N	\N
13	36c74c84-bb86-49a9-a2f1-fe8ab7d736b0	1	1	BAR	{"es": "Barra", "zh": "吧台"}	B-02	2	available	\N	2026-10-12 00:16:46	2026-10-19 19:47:23	\N	pending	3	\N	\N	\N	0	0	0	square	\N	\N
1	7185d16c-26b2-4806-91a9-3ecf6fde7abb	1	1	MAIN	{"es": "Salón Principal", "zh": "主厅"}	M-01	4	occupied	24	2026-10-12 00:16:46	2026-10-19 20:48:32	\N	pending	2	\N	\N	\N	0	0	0	square	\N	\N
\.


--
-- Data for Name: role_has_permissions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.role_has_permissions (permission_id, role_id) FROM stdin;
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.roles (id, name, guard_name, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
\.


--
-- Data for Name: sync_log; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sync_log (id, uuid, company_id, branch_id, sync_session_id, direction, entity_type, entity_id, entity_uuid, action, result, conflict_data, error_message, duration_ms, metadata, synced_at) FROM stdin;
\.


--
-- Data for Name: sync_queue; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sync_queue (id, uuid, company_id, branch_id, entity_type, entity_id, entity_uuid, action, payload, version, attempts, status, error_message, last_attempt_at, next_attempt_at, created_at, updated_at, deleted_at, conflict_data, processing_started_at, lease_expires_at, last_error_code) FROM stdin;
1	662b9f02-f94c-4240-ad14-c97ef329d3a2	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	1	7185d16c-26b2-4806-91a9-3ecf6fde7abb	create	{"branch_id":1,"table_number":"M-01","company_id":1,"area_code":"MAIN","area_name_translations":"{\\"es\\":\\"Sal\\\\u00f3n Principal\\",\\"zh\\":\\"\\\\u4e3b\\\\u5385\\"}","capacity":4,"status":"available","uuid":"7185d16c-26b2-4806-91a9-3ecf6fde7abb","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":1}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
2	a1e67485-7962-4c10-b077-5aeec97b4e4b	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	2	95409893-027e-4a41-9a36-8717303c9406	create	{"branch_id":1,"table_number":"M-02","company_id":1,"area_code":"MAIN","area_name_translations":"{\\"es\\":\\"Sal\\\\u00f3n Principal\\",\\"zh\\":\\"\\\\u4e3b\\\\u5385\\"}","capacity":4,"status":"available","uuid":"95409893-027e-4a41-9a36-8717303c9406","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":2}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
3	e32ac017-be3e-4c37-95c7-28b528c886f6	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	3	7c23b2dd-ef37-45f7-9078-96a7cc44d86f	create	{"branch_id":1,"table_number":"M-03","company_id":1,"area_code":"MAIN","area_name_translations":"{\\"es\\":\\"Sal\\\\u00f3n Principal\\",\\"zh\\":\\"\\\\u4e3b\\\\u5385\\"}","capacity":6,"status":"available","uuid":"7c23b2dd-ef37-45f7-9078-96a7cc44d86f","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":3}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
4	00bb3ea5-29be-403c-a6ab-def5fa336398	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	4	3ca1e5ba-36aa-4308-b2c9-ec13fd0fa661	create	{"branch_id":1,"table_number":"M-04","company_id":1,"area_code":"MAIN","area_name_translations":"{\\"es\\":\\"Sal\\\\u00f3n Principal\\",\\"zh\\":\\"\\\\u4e3b\\\\u5385\\"}","capacity":2,"status":"available","uuid":"3ca1e5ba-36aa-4308-b2c9-ec13fd0fa661","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":4}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
5	7bc87faf-4dfb-45e3-abfc-c2b3b4f309ff	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	5	dfa6557c-3f97-4125-9252-42bb452316f4	create	{"branch_id":1,"table_number":"M-05","company_id":1,"area_code":"MAIN","area_name_translations":"{\\"es\\":\\"Sal\\\\u00f3n Principal\\",\\"zh\\":\\"\\\\u4e3b\\\\u5385\\"}","capacity":8,"status":"available","uuid":"dfa6557c-3f97-4125-9252-42bb452316f4","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":5}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
6	bd014c8c-d85c-4ab3-b9a3-1d0be3482ba7	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	6	f81c57c1-4e4a-432b-81af-a969732cf572	create	{"branch_id":1,"table_number":"T-01","company_id":1,"area_code":"TERRAZA","area_name_translations":"{\\"es\\":\\"Terraza\\",\\"zh\\":\\"\\\\u9732\\\\u53f0\\"}","capacity":2,"status":"available","uuid":"f81c57c1-4e4a-432b-81af-a969732cf572","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":6}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
7	265c6aea-bd9b-47ea-958b-6f54f793c875	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	7	30151c3f-8923-4a31-8ab8-0169219e1d9e	create	{"branch_id":1,"table_number":"T-02","company_id":1,"area_code":"TERRAZA","area_name_translations":"{\\"es\\":\\"Terraza\\",\\"zh\\":\\"\\\\u9732\\\\u53f0\\"}","capacity":2,"status":"available","uuid":"30151c3f-8923-4a31-8ab8-0169219e1d9e","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":7}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
8	34b3bc45-96b1-4cb7-a7a7-7a30b82c96d9	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	8	f22bd329-f010-4022-b9d6-63be0c4b7b07	create	{"branch_id":1,"table_number":"T-03","company_id":1,"area_code":"TERRAZA","area_name_translations":"{\\"es\\":\\"Terraza\\",\\"zh\\":\\"\\\\u9732\\\\u53f0\\"}","capacity":4,"status":"available","uuid":"f22bd329-f010-4022-b9d6-63be0c4b7b07","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":8}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
9	9fdce7b6-3554-4e11-b4f4-32f1d0bcdcea	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	9	0259d8d6-39f2-4b0c-b226-503525633414	create	{"branch_id":1,"table_number":"T-04","company_id":1,"area_code":"TERRAZA","area_name_translations":"{\\"es\\":\\"Terraza\\",\\"zh\\":\\"\\\\u9732\\\\u53f0\\"}","capacity":4,"status":"available","uuid":"0259d8d6-39f2-4b0c-b226-503525633414","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":9}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
10	488ffbd3-0395-4d09-97ef-29a4dcaff656	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	10	c3c47483-46a8-428b-825a-538af341c895	create	{"branch_id":1,"table_number":"VIP-01","company_id":1,"area_code":"VIP","area_name_translations":"{\\"es\\":\\"\\\\u00c1rea VIP\\",\\"zh\\":\\"\\\\u8d35\\\\u5bbe\\\\u533a\\"}","capacity":8,"status":"available","uuid":"c3c47483-46a8-428b-825a-538af341c895","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":10}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
11	637e72b3-4d83-4e7b-a6ff-ae08863d0094	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	11	62d596c7-6ad7-4d77-8bde-0fdab94e012d	create	{"branch_id":1,"table_number":"VIP-02","company_id":1,"area_code":"VIP","area_name_translations":"{\\"es\\":\\"\\\\u00c1rea VIP\\",\\"zh\\":\\"\\\\u8d35\\\\u5bbe\\\\u533a\\"}","capacity":12,"status":"available","uuid":"62d596c7-6ad7-4d77-8bde-0fdab94e012d","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":11}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
12	dd7589d3-03aa-4f0e-a941-9999f78feb4e	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	12	f8c4272e-f51c-485f-9c1f-2fc14dd7744d	create	{"branch_id":1,"table_number":"B-01","company_id":1,"area_code":"BAR","area_name_translations":"{\\"es\\":\\"Barra\\",\\"zh\\":\\"\\\\u5427\\\\u53f0\\"}","capacity":2,"status":"available","uuid":"f8c4272e-f51c-485f-9c1f-2fc14dd7744d","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":12}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
13	c5c68562-0098-4f7d-9b45-1f41ae84c32a	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	13	36c74c84-bb86-49a9-a2f1-fe8ab7d736b0	create	{"branch_id":1,"table_number":"B-02","company_id":1,"area_code":"BAR","area_name_translations":"{\\"es\\":\\"Barra\\",\\"zh\\":\\"\\\\u5427\\\\u53f0\\"}","capacity":2,"status":"available","uuid":"36c74c84-bb86-49a9-a2f1-fe8ab7d736b0","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":13}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
14	9bf63beb-7c70-4c75-990a-b93d410cab36	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	14	4ce6978a-4c19-4f52-bce0-d2e45b92d0c1	create	{"branch_id":1,"table_number":"B-03","company_id":1,"area_code":"BAR","area_name_translations":"{\\"es\\":\\"Barra\\",\\"zh\\":\\"\\\\u5427\\\\u53f0\\"}","capacity":2,"status":"available","uuid":"4ce6978a-4c19-4f52-bce0-d2e45b92d0c1","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":14}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
15	979c8006-bea5-4006-8bef-82d93600515d	1	2	Modules\\Tables\\Domain\\Entities\\RestaurantTable	15	1d438266-4bcc-472d-ba36-ce0507e60773	create	{"branch_id":2,"table_number":"M-01","company_id":1,"area_code":"MAIN","area_name_translations":"{\\"es\\":\\"Sal\\\\u00f3n Principal\\",\\"zh\\":\\"\\\\u4e3b\\\\u5385\\"}","capacity":4,"status":"available","uuid":"1d438266-4bcc-472d-ba36-ce0507e60773","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":15}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
16	86a7ce51-8195-4b5f-b363-669f28d22e97	1	2	Modules\\Tables\\Domain\\Entities\\RestaurantTable	16	a5d7ee3e-1260-4845-8c2a-ed2cb32a7ebc	create	{"branch_id":2,"table_number":"M-02","company_id":1,"area_code":"MAIN","area_name_translations":"{\\"es\\":\\"Sal\\\\u00f3n Principal\\",\\"zh\\":\\"\\\\u4e3b\\\\u5385\\"}","capacity":4,"status":"available","uuid":"a5d7ee3e-1260-4845-8c2a-ed2cb32a7ebc","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":16}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
17	7d9dc518-1910-4cde-a755-6b7ea5c0c4d0	1	2	Modules\\Tables\\Domain\\Entities\\RestaurantTable	17	4a1daaf7-fa77-430d-8cc1-f91b8735cf67	create	{"branch_id":2,"table_number":"M-03","company_id":1,"area_code":"MAIN","area_name_translations":"{\\"es\\":\\"Sal\\\\u00f3n Principal\\",\\"zh\\":\\"\\\\u4e3b\\\\u5385\\"}","capacity":6,"status":"available","uuid":"4a1daaf7-fa77-430d-8cc1-f91b8735cf67","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":17}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
18	8ceab1ed-67b7-402d-9f60-73f661a0d238	1	2	Modules\\Tables\\Domain\\Entities\\RestaurantTable	18	27b66538-13ec-4a4f-b945-0e4fb80e7f08	create	{"branch_id":2,"table_number":"M-04","company_id":1,"area_code":"MAIN","area_name_translations":"{\\"es\\":\\"Sal\\\\u00f3n Principal\\",\\"zh\\":\\"\\\\u4e3b\\\\u5385\\"}","capacity":2,"status":"available","uuid":"27b66538-13ec-4a4f-b945-0e4fb80e7f08","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":18}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
19	7befab0b-e1cc-4f55-a628-621f4a3518ed	1	2	Modules\\Tables\\Domain\\Entities\\RestaurantTable	19	8a100194-36de-4a24-9d1c-aa01998dfa33	create	{"branch_id":2,"table_number":"M-05","company_id":1,"area_code":"MAIN","area_name_translations":"{\\"es\\":\\"Sal\\\\u00f3n Principal\\",\\"zh\\":\\"\\\\u4e3b\\\\u5385\\"}","capacity":8,"status":"available","uuid":"8a100194-36de-4a24-9d1c-aa01998dfa33","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":19}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
20	dd8b3119-713c-4580-9b29-9a093c3bc314	1	2	Modules\\Tables\\Domain\\Entities\\RestaurantTable	20	0d7ba1be-2213-4921-bd5c-f2b008594846	create	{"branch_id":2,"table_number":"T-01","company_id":1,"area_code":"TERRAZA","area_name_translations":"{\\"es\\":\\"Terraza\\",\\"zh\\":\\"\\\\u9732\\\\u53f0\\"}","capacity":2,"status":"available","uuid":"0d7ba1be-2213-4921-bd5c-f2b008594846","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":20}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
21	c666205b-8fe8-4649-a86f-1a85fe611493	1	2	Modules\\Tables\\Domain\\Entities\\RestaurantTable	21	681116e3-da65-4aa3-82f6-3b731d80f47d	create	{"branch_id":2,"table_number":"T-02","company_id":1,"area_code":"TERRAZA","area_name_translations":"{\\"es\\":\\"Terraza\\",\\"zh\\":\\"\\\\u9732\\\\u53f0\\"}","capacity":2,"status":"available","uuid":"681116e3-da65-4aa3-82f6-3b731d80f47d","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":21}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
22	ed25ec76-4cb1-4d51-a2b6-68e480dfc6a1	1	2	Modules\\Tables\\Domain\\Entities\\RestaurantTable	22	16a2e976-fc10-4259-95a9-5eb2a4554ddc	create	{"branch_id":2,"table_number":"T-03","company_id":1,"area_code":"TERRAZA","area_name_translations":"{\\"es\\":\\"Terraza\\",\\"zh\\":\\"\\\\u9732\\\\u53f0\\"}","capacity":4,"status":"available","uuid":"16a2e976-fc10-4259-95a9-5eb2a4554ddc","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":22}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
23	2917c722-0d92-4be1-911c-7926701710e2	1	2	Modules\\Tables\\Domain\\Entities\\RestaurantTable	23	fde4c05c-742e-4f05-88e4-cf02f68f1b18	create	{"branch_id":2,"table_number":"T-04","company_id":1,"area_code":"TERRAZA","area_name_translations":"{\\"es\\":\\"Terraza\\",\\"zh\\":\\"\\\\u9732\\\\u53f0\\"}","capacity":4,"status":"available","uuid":"fde4c05c-742e-4f05-88e4-cf02f68f1b18","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":23}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
24	b3876d49-2c4f-4a68-a72d-636c35d25163	1	2	Modules\\Tables\\Domain\\Entities\\RestaurantTable	24	04dd6cd9-7004-4d2b-8905-947175b9f322	create	{"branch_id":2,"table_number":"VIP-01","company_id":1,"area_code":"VIP","area_name_translations":"{\\"es\\":\\"\\\\u00c1rea VIP\\",\\"zh\\":\\"\\\\u8d35\\\\u5bbe\\\\u533a\\"}","capacity":8,"status":"available","uuid":"04dd6cd9-7004-4d2b-8905-947175b9f322","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":24}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
25	872c04c8-23b5-4471-b785-3ef0eb14b5a8	1	2	Modules\\Tables\\Domain\\Entities\\RestaurantTable	25	24c30bbb-3a95-43a6-82ad-98b1faeee9a8	create	{"branch_id":2,"table_number":"VIP-02","company_id":1,"area_code":"VIP","area_name_translations":"{\\"es\\":\\"\\\\u00c1rea VIP\\",\\"zh\\":\\"\\\\u8d35\\\\u5bbe\\\\u533a\\"}","capacity":12,"status":"available","uuid":"24c30bbb-3a95-43a6-82ad-98b1faeee9a8","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":25}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
26	24662ad5-4ffc-4cba-8ef7-95c38592b984	1	2	Modules\\Tables\\Domain\\Entities\\RestaurantTable	26	1b708d54-4d47-4fe8-9215-869af1c87ba0	create	{"branch_id":2,"table_number":"B-01","company_id":1,"area_code":"BAR","area_name_translations":"{\\"es\\":\\"Barra\\",\\"zh\\":\\"\\\\u5427\\\\u53f0\\"}","capacity":2,"status":"available","uuid":"1b708d54-4d47-4fe8-9215-869af1c87ba0","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":26}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
27	29d20114-c637-4ba5-9029-fc399d8c96af	1	2	Modules\\Tables\\Domain\\Entities\\RestaurantTable	27	98d9ab3b-c6c8-4bef-ba0b-163039a50198	create	{"branch_id":2,"table_number":"B-02","company_id":1,"area_code":"BAR","area_name_translations":"{\\"es\\":\\"Barra\\",\\"zh\\":\\"\\\\u5427\\\\u53f0\\"}","capacity":2,"status":"available","uuid":"98d9ab3b-c6c8-4bef-ba0b-163039a50198","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":27}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
28	997be0f8-f00f-4d62-9e16-1f924d035fbd	1	2	Modules\\Tables\\Domain\\Entities\\RestaurantTable	28	dbca6689-fac3-429d-bcc3-f691e33e7374	create	{"branch_id":2,"table_number":"B-03","company_id":1,"area_code":"BAR","area_name_translations":"{\\"es\\":\\"Barra\\",\\"zh\\":\\"\\\\u5427\\\\u53f0\\"}","capacity":2,"status":"available","uuid":"dbca6689-fac3-429d-bcc3-f691e33e7374","version":1,"sync_status":"pending","updated_at":"2026-10-12 00:16:46","created_at":"2026-10-12 00:16:46","id":28}	1	0	pending	\N	\N	\N	2026-10-12 00:16:46	2026-10-12 00:16:46	\N	\N	\N	\N	\N
29	4a4a3a6b-18ed-4c79-bb1d-bc32d38d720c	1	1	Modules\\Tables\\Domain\\Entities\\DiningZone	1	893fa5fb-07cb-4a73-b539-f805400c46cd	create	{"code":"BAL01","name_translations":"{\\"es\\":\\"balcon\\",\\"zh\\":\\"balcon\\"}","color":"#f97316","floor_level":0,"company_id":1,"branch_id":1,"uuid":"893fa5fb-07cb-4a73-b539-f805400c46cd","version":1,"sync_status":"pending","updated_at":"2026-10-16 02:45:35","created_at":"2026-10-16 02:45:35","id":1}	1	0	pending	\N	\N	\N	2026-10-16 02:45:35	2026-10-16 02:45:35	\N	\N	\N	\N	\N
30	eeea041b-31f4-4131-b1d1-eff9d0b03208	1	1	Modules\\Tables\\Domain\\Entities\\DiningZone	1	893fa5fb-07cb-4a73-b539-f805400c46cd	delete	{"id":1}	1	0	pending	\N	\N	\N	2026-10-16 02:45:49	2026-10-16 02:45:49	\N	\N	\N	\N	\N
31	90637e6a-3dce-4738-b66f-4ca18b7169e0	1	1	Modules\\Orders\\Domain\\Entities\\Order	1	2b188715-9d56-4293-894b-e1c13904fcb4	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0001","type":"dine_in","fulfillment_channel":"onsite","status":"draft","table_id":12,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"2b188715-9d56-4293-894b-e1c13904fcb4","version":1,"sync_status":"pending","updated_at":"2026-10-16 14:28:36","created_at":"2026-10-16 14:28:36","id":1}	1	0	pending	\N	\N	\N	2026-10-16 14:28:36	2026-10-16 14:28:36	\N	\N	\N	\N	\N
32	1f511df8-82a9-4006-bc06-4801337c79d1	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	1	b6501f18-cf75-43a7-b230-080a053daf1e	create	{"product_id":5,"company_id":1,"order_id":1,"menu_item_id":null,"name_snapshot":"Caldillo de Congrio","unit_price_snapshot":8900,"quantity":1,"notes":null,"subtotal":8900,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"b6501f18-cf75-43a7-b230-080a053daf1e","version":1,"sync_status":"pending","updated_at":"2026-10-16 14:28:36","created_at":"2026-10-16 14:28:36","id":1}	1	0	pending	\N	\N	\N	2026-10-16 14:28:36	2026-10-16 14:28:36	\N	\N	\N	\N	\N
33	a0c34deb-30d5-43f5-9dec-236b760b44e5	1	1	Modules\\Orders\\Domain\\Entities\\Order	1	2b188715-9d56-4293-894b-e1c13904fcb4	update	{"id":1,"uuid":"2b188715-9d56-4293-894b-e1c13904fcb4","company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0001","type":"dine_in","status":"draft","table_id":12,"waiter_id":1,"cashier_id":null,"subtotal":8900,"tax_amount":1421,"discount_amount":0,"total":8900,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16 14:28:36","updated_at":"2026-10-16 14:28:36","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"onsite","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":8900,"net_amount":7479,"tip_amount":0,"amount_due":8900}	2	0	pending	\N	\N	\N	2026-10-16 14:28:36	2026-10-16 14:28:36	\N	\N	\N	\N	\N
34	7559f5c3-07f8-4d53-bf04-d511dde8a8f4	1	1	Modules\\Orders\\Domain\\Entities\\Order	1	2b188715-9d56-4293-894b-e1c13904fcb4	update	{"id":1,"uuid":"2b188715-9d56-4293-894b-e1c13904fcb4","company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0001","type":"dine_in","status":"confirmed","table_id":12,"waiter_id":1,"cashier_id":null,"subtotal":8900,"tax_amount":1421,"discount_amount":0,"total":8900,"notes":null,"confirmed_at":"2026-10-16 14:28:37","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16 14:28:36","updated_at":"2026-10-16 14:28:37","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"onsite","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":8900,"net_amount":7479,"tip_amount":0,"amount_due":8900}	3	0	pending	\N	\N	\N	2026-10-16 14:28:37	2026-10-16 14:28:37	\N	\N	\N	\N	\N
35	a270f49d-2e96-474d-a3da-ab0a637cc30f	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	12	f8c4272e-f51c-485f-9c1f-2fc14dd7744d	update	{"id":12,"uuid":"f8c4272e-f51c-485f-9c1f-2fc14dd7744d","company_id":1,"branch_id":1,"area_code":"BAR","area_name_translations":"{\\"es\\": \\"Barra\\", \\"zh\\": \\"\\u5427\\u53f0\\"}","table_number":"B-01","capacity":2,"status":"occupied","current_order_id":1,"created_at":"2026-10-12 00:16:46","updated_at":"2026-10-16 14:28:37","deleted_at":null,"sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"zone_id":null,"position_x":0,"position_y":0,"rotation":0,"shape":"square","width":null,"height":null}	2	0	pending	\N	\N	\N	2026-10-16 14:28:37	2026-10-16 14:28:37	\N	\N	\N	\N	\N
36	80fb9ab0-4ec0-4c0c-89a1-d734b1e095fb	1	1	Modules\\Orders\\Domain\\Entities\\Order	1	2b188715-9d56-4293-894b-e1c13904fcb4	update	{"id":1,"uuid":"2b188715-9d56-4293-894b-e1c13904fcb4","company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0001","type":"dine_in","status":"preparing","table_id":12,"waiter_id":1,"cashier_id":null,"subtotal":8900,"tax_amount":1421,"discount_amount":0,"total":8900,"notes":null,"confirmed_at":"2026-10-16 14:28:37","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16 14:28:36","updated_at":"2026-10-16 15:17:35","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":4,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"onsite","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":8900,"net_amount":7479,"tip_amount":0,"amount_due":8900}	4	0	pending	\N	\N	\N	2026-10-16 15:17:35	2026-10-16 15:17:35	\N	\N	\N	\N	\N
37	9d7af6d2-495c-404d-9027-bd6c02599a8f	1	1	Modules\\Orders\\Domain\\Entities\\Order	1	2b188715-9d56-4293-894b-e1c13904fcb4	update	{"id":1,"uuid":"2b188715-9d56-4293-894b-e1c13904fcb4","company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0001","type":"dine_in","status":"ready","table_id":12,"waiter_id":1,"cashier_id":null,"subtotal":8900,"tax_amount":1421,"discount_amount":0,"total":8900,"notes":null,"confirmed_at":"2026-10-16 14:28:37","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16 14:28:36","updated_at":"2026-10-16 15:17:38","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":5,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"onsite","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":8900,"net_amount":7479,"tip_amount":0,"amount_due":8900}	5	0	pending	\N	\N	\N	2026-10-16 15:17:38	2026-10-16 15:17:38	\N	\N	\N	\N	\N
38	5d08711e-a78c-445f-8690-166dc342ce8e	1	1	Modules\\Orders\\Domain\\Entities\\Order	1	2b188715-9d56-4293-894b-e1c13904fcb4	update	{"id":1,"uuid":"2b188715-9d56-4293-894b-e1c13904fcb4","company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0001","type":"dine_in","status":"served","table_id":12,"waiter_id":1,"cashier_id":null,"subtotal":8900,"tax_amount":1421,"discount_amount":0,"total":8900,"notes":null,"confirmed_at":"2026-10-16 14:28:37","served_at":"2026-10-16 15:17:41","paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16 14:28:36","updated_at":"2026-10-16 15:17:41","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":6,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"onsite","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":8900,"net_amount":7479,"tip_amount":0,"amount_due":8900}	6	0	pending	\N	\N	\N	2026-10-16 15:17:41	2026-10-16 15:17:41	\N	\N	\N	\N	\N
39	cc0e6931-0074-43ca-b0c6-5348627a8e8c	1	1	Modules\\Orders\\Domain\\Entities\\Order	2	d975b860-a5e5-46ce-be54-793a8a592152	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0002","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"d975b860-a5e5-46ce-be54-793a8a592152","version":1,"sync_status":"pending","updated_at":"2026-10-16 15:20:12","created_at":"2026-10-16 15:20:12","id":2}	1	0	pending	\N	\N	\N	2026-10-16 15:20:12	2026-10-16 15:20:12	\N	\N	\N	\N	\N
40	ed51f367-f47a-4bce-b546-5c664322c79b	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	2	42cd2483-adb2-443c-b551-dbad6c85524d	create	{"product_id":1,"company_id":1,"order_id":2,"menu_item_id":null,"name_snapshot":"Empanadas de Pino (3un)","unit_price_snapshot":3500,"quantity":1,"notes":null,"subtotal":3500,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"42cd2483-adb2-443c-b551-dbad6c85524d","version":1,"sync_status":"pending","updated_at":"2026-10-16 15:20:13","created_at":"2026-10-16 15:20:13","id":2}	1	0	pending	\N	\N	\N	2026-10-16 15:20:13	2026-10-16 15:20:13	\N	\N	\N	\N	\N
41	db0557db-0fc0-4061-a8da-07ab0045ba53	1	1	Modules\\Orders\\Domain\\Entities\\Order	2	d975b860-a5e5-46ce-be54-793a8a592152	update	{"id":2,"uuid":"d975b860-a5e5-46ce-be54-793a8a592152","company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0002","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":3500,"tax_amount":559,"discount_amount":0,"total":3500,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16 15:20:12","updated_at":"2026-10-16 15:20:13","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":3500,"net_amount":2941,"tip_amount":0,"amount_due":3500}	2	0	pending	\N	\N	\N	2026-10-16 15:20:13	2026-10-16 15:20:13	\N	\N	\N	\N	\N
42	ff3466df-27bd-4477-bbf9-71fdab2a8d54	1	1	Modules\\Orders\\Domain\\Entities\\Order	2	d975b860-a5e5-46ce-be54-793a8a592152	update	{"id":2,"uuid":"d975b860-a5e5-46ce-be54-793a8a592152","company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0002","type":"takeout","status":"confirmed","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":3500,"tax_amount":559,"discount_amount":0,"total":3500,"notes":null,"confirmed_at":"2026-10-16 15:20:13","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16 15:20:12","updated_at":"2026-10-16 15:20:13","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":3500,"net_amount":2941,"tip_amount":0,"amount_due":3500}	3	0	pending	\N	\N	\N	2026-10-16 15:20:13	2026-10-16 15:20:13	\N	\N	\N	\N	\N
43	27266128-5931-4bf4-98b7-f28620a08966	1	1	Modules\\Orders\\Domain\\Entities\\Order	3	f9ebfec7-6701-4c4b-a933-c50d409490f1	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0003","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"f9ebfec7-6701-4c4b-a933-c50d409490f1","version":1,"sync_status":"pending","updated_at":"2026-10-16 15:50:52","created_at":"2026-10-16 15:50:52","id":3}	1	0	pending	\N	\N	\N	2026-10-16 15:50:53	2026-10-16 15:50:53	\N	\N	\N	\N	\N
44	0c283c1e-7aa2-46f1-98d2-8f5d33cc8f5e	1	1	Modules\\Orders\\Domain\\Entities\\Order	4	a1ffa7b8-86fe-48c1-aa94-bb4aaa188467	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0004","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"a1ffa7b8-86fe-48c1-aa94-bb4aaa188467","version":1,"sync_status":"pending","updated_at":"2026-10-16 15:51:07","created_at":"2026-10-16 15:51:07","id":4}	1	0	pending	\N	\N	\N	2026-10-16 15:51:08	2026-10-16 15:51:08	\N	\N	\N	\N	\N
45	0d7ee156-8402-43b4-ac85-cd2667103eaa	1	1	Modules\\Orders\\Domain\\Entities\\Order	5	6f423e88-4b75-4360-a207-4fab123e7c96	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0005","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"6f423e88-4b75-4360-a207-4fab123e7c96","version":1,"sync_status":"pending","updated_at":"2026-10-16 15:51:23","created_at":"2026-10-16 15:51:23","id":5}	1	0	pending	\N	\N	\N	2026-10-16 15:51:23	2026-10-16 15:51:23	\N	\N	\N	\N	\N
46	801104d8-c92f-455f-b5d3-137963b96d2b	1	1	Modules\\Orders\\Domain\\Entities\\Order	6	6235e9fa-a1c6-4485-94f4-a2f9c1f3e097	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0006","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"6235e9fa-a1c6-4485-94f4-a2f9c1f3e097","version":1,"sync_status":"pending","updated_at":"2026-10-16 15:52:08","created_at":"2026-10-16 15:52:08","id":6}	1	0	pending	\N	\N	\N	2026-10-16 15:52:08	2026-10-16 15:52:08	\N	\N	\N	\N	\N
47	7bd525a6-46d2-418a-9e3c-38dfd0313394	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	3	56c569b0-8c9f-4a4b-a98a-a6978e2f2ce6	create	{"product_id":15,"company_id":1,"order_id":6,"menu_item_id":null,"name_snapshot":"Agua Mineral","unit_price_snapshot":1000,"quantity":1,"notes":null,"subtotal":1000,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"56c569b0-8c9f-4a4b-a98a-a6978e2f2ce6","version":1,"sync_status":"pending","updated_at":"2026-10-16 15:52:08","created_at":"2026-10-16 15:52:08","id":3}	1	0	pending	\N	\N	\N	2026-10-16 15:52:08	2026-10-16 15:52:08	\N	\N	\N	\N	\N
48	2042631a-d3a9-4aea-8c31-99a5247c0001	1	1	Modules\\Orders\\Domain\\Entities\\Order	6	6235e9fa-a1c6-4485-94f4-a2f9c1f3e097	update	{"id":6,"uuid":"6235e9fa-a1c6-4485-94f4-a2f9c1f3e097","company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0006","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":1000,"tax_amount":160,"discount_amount":0,"total":1000,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16 15:52:08","updated_at":"2026-10-16 15:52:08","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":1000,"net_amount":840,"tip_amount":0,"amount_due":1000}	2	0	pending	\N	\N	\N	2026-10-16 15:52:08	2026-10-16 15:52:08	\N	\N	\N	\N	\N
49	1092a5c9-88ba-416c-9e61-d8a53f6193b4	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	4	9cd3d388-04b1-44a3-aee7-c81f9d898c5e	create	{"product_id":12,"company_id":1,"order_id":6,"menu_item_id":null,"name_snapshot":"Coca-Cola 500ml","unit_price_snapshot":1500,"quantity":1,"notes":null,"subtotal":1500,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"9cd3d388-04b1-44a3-aee7-c81f9d898c5e","version":1,"sync_status":"pending","updated_at":"2026-10-16 15:52:08","created_at":"2026-10-16 15:52:08","id":4}	1	0	pending	\N	\N	\N	2026-10-16 15:52:08	2026-10-16 15:52:08	\N	\N	\N	\N	\N
50	35cb9fe8-cc5e-4b5c-b389-911198c93a4a	1	1	Modules\\Orders\\Domain\\Entities\\Order	6	6235e9fa-a1c6-4485-94f4-a2f9c1f3e097	update	{"id":6,"uuid":"6235e9fa-a1c6-4485-94f4-a2f9c1f3e097","company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0006","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":2500,"tax_amount":399,"discount_amount":0,"total":2500,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16 15:52:08","updated_at":"2026-10-16 15:52:08","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":2500,"net_amount":2101,"tip_amount":0,"amount_due":2500}	3	0	pending	\N	\N	\N	2026-10-16 15:52:08	2026-10-16 15:52:08	\N	\N	\N	\N	\N
51	4ab84250-789a-418f-8621-fd5624b54082	1	1	Modules\\Orders\\Domain\\Entities\\Order	6	6235e9fa-a1c6-4485-94f4-a2f9c1f3e097	update	{"id":6,"uuid":"6235e9fa-a1c6-4485-94f4-a2f9c1f3e097","company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0006","type":"takeout","status":"confirmed","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":2500,"tax_amount":399,"discount_amount":0,"total":2500,"notes":null,"confirmed_at":"2026-10-16 15:52:08","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16 15:52:08","updated_at":"2026-10-16 15:52:08","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":4,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":2500,"net_amount":2101,"tip_amount":0,"amount_due":2500}	4	0	pending	\N	\N	\N	2026-10-16 15:52:08	2026-10-16 15:52:08	\N	\N	\N	\N	\N
52	77249852-9b73-47ec-b11d-398afa086263	1	1	Modules\\Orders\\Domain\\Entities\\Order	7	ab321bf9-19aa-4689-9dbb-52706407d4a7	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0007","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"ab321bf9-19aa-4689-9dbb-52706407d4a7","version":1,"sync_status":"pending","updated_at":"2026-10-16 15:55:31","created_at":"2026-10-16 15:55:31","id":7}	1	0	pending	\N	\N	\N	2026-10-16 15:55:32	2026-10-16 15:55:32	\N	\N	\N	\N	\N
53	788c6569-87f9-4067-b105-752fa85484d6	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	5	a90f7639-303b-4eca-b1e1-bd729ec4ea90	create	{"product_id":5,"company_id":1,"order_id":7,"menu_item_id":null,"name_snapshot":"Caldillo de Congrio","unit_price_snapshot":8900,"quantity":1,"notes":null,"subtotal":8900,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"a90f7639-303b-4eca-b1e1-bd729ec4ea90","version":1,"sync_status":"pending","updated_at":"2026-10-16 15:55:32","created_at":"2026-10-16 15:55:32","id":5}	1	0	pending	\N	\N	\N	2026-10-16 15:55:32	2026-10-16 15:55:32	\N	\N	\N	\N	\N
54	474c68e7-417c-444a-8fa2-931c54cab053	1	1	Modules\\Orders\\Domain\\Entities\\Order	7	ab321bf9-19aa-4689-9dbb-52706407d4a7	update	{"id":7,"uuid":"ab321bf9-19aa-4689-9dbb-52706407d4a7","company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0007","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":8900,"tax_amount":1421,"discount_amount":0,"total":8900,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16 15:55:31","updated_at":"2026-10-16 15:55:32","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":8900,"net_amount":7479,"tip_amount":0,"amount_due":8900}	2	0	pending	\N	\N	\N	2026-10-16 15:55:32	2026-10-16 15:55:32	\N	\N	\N	\N	\N
55	9c831ccc-f255-442c-bf5b-b63a7137c113	1	1	Modules\\Orders\\Domain\\Entities\\Order	7	ab321bf9-19aa-4689-9dbb-52706407d4a7	update	{"id":7,"uuid":"ab321bf9-19aa-4689-9dbb-52706407d4a7","company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0007","type":"takeout","status":"confirmed","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":8900,"tax_amount":1421,"discount_amount":0,"total":8900,"notes":null,"confirmed_at":"2026-10-16 15:55:32","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16 15:55:31","updated_at":"2026-10-16 15:55:32","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":8900,"net_amount":7479,"tip_amount":0,"amount_due":8900}	3	0	pending	\N	\N	\N	2026-10-16 15:55:32	2026-10-16 15:55:32	\N	\N	\N	\N	\N
56	5026c2e4-8627-4f0a-8c3c-e9c4853f67d8	1	1	Modules\\Orders\\Domain\\Entities\\Order	8	f9fb9996-b05e-458d-a737-c53a196f67c8	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0008","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"f9fb9996-b05e-458d-a737-c53a196f67c8","version":1,"sync_status":"pending","updated_at":"2026-10-16 16:21:26","created_at":"2026-10-16 16:21:26","id":8}	1	0	pending	\N	\N	\N	2026-10-16 16:21:26	2026-10-16 16:21:26	\N	\N	\N	\N	\N
57	2dbf82e9-feea-4dd2-ac5d-803f5d66d7cb	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	6	945fefa8-a514-43ef-9251-2e7fd2a74170	create	{"product_id":11,"company_id":1,"order_id":8,"menu_item_id":null,"name_snapshot":"Ensalada Mixta","unit_price_snapshot":3200,"quantity":1,"notes":null,"subtotal":3200,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"945fefa8-a514-43ef-9251-2e7fd2a74170","version":1,"sync_status":"pending","updated_at":"2026-10-16 16:21:27","created_at":"2026-10-16 16:21:27","id":6}	1	0	pending	\N	\N	\N	2026-10-16 16:21:27	2026-10-16 16:21:27	\N	\N	\N	\N	\N
58	ea18b4e9-49a2-4c58-8efe-88d5532703ee	1	1	Modules\\Orders\\Domain\\Entities\\Order	8	f9fb9996-b05e-458d-a737-c53a196f67c8	update	{"id":8,"uuid":"f9fb9996-b05e-458d-a737-c53a196f67c8","company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0008","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":3200,"tax_amount":511,"discount_amount":0,"total":3200,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16 16:21:26","updated_at":"2026-10-16 16:21:27","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":3200,"net_amount":2689,"tip_amount":0,"amount_due":3200}	2	0	pending	\N	\N	\N	2026-10-16 16:21:27	2026-10-16 16:21:27	\N	\N	\N	\N	\N
59	6557c5ad-e462-41fa-82a1-4a19d2f390e0	1	1	Modules\\Orders\\Domain\\Entities\\Order	8	f9fb9996-b05e-458d-a737-c53a196f67c8	update	{"id":8,"uuid":"f9fb9996-b05e-458d-a737-c53a196f67c8","company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0008","type":"takeout","status":"confirmed","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":3200,"tax_amount":511,"discount_amount":0,"total":3200,"notes":null,"confirmed_at":"2026-10-16 16:21:27","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16 16:21:26","updated_at":"2026-10-16 16:21:27","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":3200,"net_amount":2689,"tip_amount":0,"amount_due":3200}	3	0	pending	\N	\N	\N	2026-10-16 16:21:27	2026-10-16 16:21:27	\N	\N	\N	\N	\N
60	d904bf45-982d-4260-8547-0687db9a6718	1	1	Modules\\Orders\\Domain\\Entities\\Order	9	76e29d27-9058-4622-a734-bfc3a044423b	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0009","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"76e29d27-9058-4622-a734-bfc3a044423b","version":1,"sync_status":"pending","updated_at":"2026-10-16 16:30:18","created_at":"2026-10-16 16:30:18","id":9}	1	0	pending	\N	\N	\N	2026-10-16 16:30:18	2026-10-16 16:30:18	\N	\N	\N	\N	\N
61	0202661d-af65-4921-ab5e-a30702c6148f	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	7	163e19cb-1bf3-408d-8d6d-0c98c360551c	create	{"product_id":7,"company_id":1,"order_id":9,"menu_item_id":null,"name_snapshot":"Hamburguesa Doble","unit_price_snapshot":7200,"quantity":1,"notes":null,"subtotal":7200,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"163e19cb-1bf3-408d-8d6d-0c98c360551c","version":1,"sync_status":"pending","updated_at":"2026-10-16 16:30:18","created_at":"2026-10-16 16:30:18","id":7}	1	0	pending	\N	\N	\N	2026-10-16 16:30:18	2026-10-16 16:30:18	\N	\N	\N	\N	\N
62	f26378f7-8f8f-4e3a-8b38-44a2f5f98204	1	1	Modules\\Orders\\Domain\\Entities\\Order	9	76e29d27-9058-4622-a734-bfc3a044423b	update	{"id":9,"uuid":"76e29d27-9058-4622-a734-bfc3a044423b","company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0009","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":7200,"tax_amount":1150,"discount_amount":0,"total":7200,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16 16:30:18","updated_at":"2026-10-16 16:30:18","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":7200,"net_amount":6050,"tip_amount":0,"amount_due":7200}	2	0	pending	\N	\N	\N	2026-10-16 16:30:18	2026-10-16 16:30:18	\N	\N	\N	\N	\N
63	6fe481d3-c435-4f38-80e4-2d3a9ec7a786	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	8	dff551c5-f3b6-4f8a-afa7-3135757df903	create	{"product_id":6,"company_id":1,"order_id":9,"menu_item_id":null,"name_snapshot":"Hamburguesa Cl\\u00e1sica","unit_price_snapshot":5500,"quantity":1,"notes":null,"subtotal":5500,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"dff551c5-f3b6-4f8a-afa7-3135757df903","version":1,"sync_status":"pending","updated_at":"2026-10-16 16:30:18","created_at":"2026-10-16 16:30:18","id":8}	1	0	pending	\N	\N	\N	2026-10-16 16:30:18	2026-10-16 16:30:18	\N	\N	\N	\N	\N
64	b4b1dba3-ee40-41c5-97de-a86e82cddff8	1	1	Modules\\Orders\\Domain\\Entities\\Order	9	76e29d27-9058-4622-a734-bfc3a044423b	update	{"id":9,"uuid":"76e29d27-9058-4622-a734-bfc3a044423b","company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0009","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":12700,"tax_amount":2028,"discount_amount":0,"total":12700,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16 16:30:18","updated_at":"2026-10-16 16:30:18","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":12700,"net_amount":10672,"tip_amount":0,"amount_due":12700}	3	0	pending	\N	\N	\N	2026-10-16 16:30:18	2026-10-16 16:30:18	\N	\N	\N	\N	\N
65	014d00d7-5548-4680-a69d-11047106ca32	1	1	Modules\\Orders\\Domain\\Entities\\Order	9	76e29d27-9058-4622-a734-bfc3a044423b	update	{"id":9,"uuid":"76e29d27-9058-4622-a734-bfc3a044423b","company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0009","type":"takeout","status":"confirmed","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":12700,"tax_amount":2028,"discount_amount":0,"total":12700,"notes":null,"confirmed_at":"2026-10-16 16:30:18","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16 16:30:18","updated_at":"2026-10-16 16:30:18","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":4,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":12700,"net_amount":10672,"tip_amount":0,"amount_due":12700}	4	0	pending	\N	\N	\N	2026-10-16 16:30:18	2026-10-16 16:30:18	\N	\N	\N	\N	\N
66	a3b1f2a9-c6f9-4219-8e55-5ed11c7f974e	1	1	Modules\\Orders\\Domain\\Entities\\Order	10	8025f6bb-3add-42c7-a80f-20145b50f23f	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0001","type":"dine_in","fulfillment_channel":"onsite","status":"draft","table_id":13,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"8025f6bb-3add-42c7-a80f-20145b50f23f","version":1,"sync_status":"pending","updated_at":"2026-10-17 13:59:27","created_at":"2026-10-17 13:59:27","id":10}	1	0	pending	\N	\N	\N	2026-10-17 13:59:27	2026-10-17 13:59:27	\N	\N	\N	\N	\N
67	b8ca077b-392a-4bc0-bdd1-bef4befe1b51	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	9	5e1e8a83-632f-42e7-a96a-e39ac39cfe8f	create	{"product_id":7,"company_id":1,"order_id":10,"menu_item_id":null,"name_snapshot":"Hamburguesa Doble","unit_price_snapshot":7200,"quantity":1,"notes":null,"subtotal":7200,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"5e1e8a83-632f-42e7-a96a-e39ac39cfe8f","version":1,"sync_status":"pending","updated_at":"2026-10-17 13:59:27","created_at":"2026-10-17 13:59:27","id":9}	1	0	pending	\N	\N	\N	2026-10-17 13:59:27	2026-10-17 13:59:27	\N	\N	\N	\N	\N
86	1f96ee75-8a4e-4344-ab6d-a7c735594424	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	15	9e4d8f75-19ce-4711-bdb0-6dead292d400	create	{"product_id":6,"company_id":1,"order_id":13,"menu_item_id":null,"name_snapshot":"Hamburguesa Cl\\u00e1sica","unit_price_snapshot":5500,"quantity":1,"notes":null,"subtotal":5500,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"9e4d8f75-19ce-4711-bdb0-6dead292d400","version":1,"sync_status":"pending","updated_at":"2026-10-17 15:17:29","created_at":"2026-10-17 15:17:29","id":15}	1	0	pending	\N	\N	\N	2026-10-17 15:17:29	2026-10-17 15:17:29	\N	\N	\N	\N	\N
68	5195c2e1-7323-4dec-a78f-40157adc5c15	1	1	Modules\\Orders\\Domain\\Entities\\Order	10	8025f6bb-3add-42c7-a80f-20145b50f23f	update	{"id":10,"uuid":"8025f6bb-3add-42c7-a80f-20145b50f23f","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0001","type":"dine_in","status":"draft","table_id":13,"waiter_id":1,"cashier_id":null,"subtotal":7200,"tax_amount":1150,"discount_amount":0,"total":7200,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 13:59:27","updated_at":"2026-10-17 13:59:27","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"onsite","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":7200,"net_amount":6050,"tip_amount":0,"amount_due":7200}	2	0	pending	\N	\N	\N	2026-10-17 13:59:27	2026-10-17 13:59:27	\N	\N	\N	\N	\N
69	b77ccb3e-d503-476a-ad8b-a0e02f3ea350	1	1	Modules\\Orders\\Domain\\Entities\\Order	10	8025f6bb-3add-42c7-a80f-20145b50f23f	update	{"id":10,"uuid":"8025f6bb-3add-42c7-a80f-20145b50f23f","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0001","type":"dine_in","status":"confirmed","table_id":13,"waiter_id":1,"cashier_id":null,"subtotal":7200,"tax_amount":1150,"discount_amount":0,"total":7200,"notes":null,"confirmed_at":"2026-10-17 13:59:28","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 13:59:27","updated_at":"2026-10-17 13:59:28","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"onsite","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":7200,"net_amount":6050,"tip_amount":0,"amount_due":7200}	3	0	pending	\N	\N	\N	2026-10-17 13:59:28	2026-10-17 13:59:28	\N	\N	\N	\N	\N
70	c044f725-7ac7-49aa-a6df-e1d13b5db123	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	13	36c74c84-bb86-49a9-a2f1-fe8ab7d736b0	update	{"id":13,"uuid":"36c74c84-bb86-49a9-a2f1-fe8ab7d736b0","company_id":1,"branch_id":1,"area_code":"BAR","area_name_translations":"{\\"es\\": \\"Barra\\", \\"zh\\": \\"\\u5427\\u53f0\\"}","table_number":"B-02","capacity":2,"status":"occupied","current_order_id":10,"created_at":"2026-10-12 00:16:46","updated_at":"2026-10-17 13:59:28","deleted_at":null,"sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"zone_id":null,"position_x":0,"position_y":0,"rotation":0,"shape":"square","width":null,"height":null}	2	0	pending	\N	\N	\N	2026-10-17 13:59:28	2026-10-17 13:59:28	\N	\N	\N	\N	\N
71	d2e237d3-b905-433f-99a7-2fed230ec40f	1	1	Modules\\Orders\\Domain\\Entities\\Order	11	dcf06f08-aa7b-41ba-9f47-b55e6e940c17	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0002","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"dcf06f08-aa7b-41ba-9f47-b55e6e940c17","version":1,"sync_status":"pending","updated_at":"2026-10-17 15:13:59","created_at":"2026-10-17 15:13:59","id":11}	1	0	pending	\N	\N	\N	2026-10-17 15:13:59	2026-10-17 15:13:59	\N	\N	\N	\N	\N
72	283079ca-e428-4839-99b9-50abf0ad6ca9	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	10	ebc3a133-dc9b-4cb1-a132-94d008144f56	create	{"product_id":7,"company_id":1,"order_id":11,"menu_item_id":null,"name_snapshot":"Hamburguesa Doble","unit_price_snapshot":7200,"quantity":1,"notes":null,"subtotal":7200,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"ebc3a133-dc9b-4cb1-a132-94d008144f56","version":1,"sync_status":"pending","updated_at":"2026-10-17 15:13:59","created_at":"2026-10-17 15:13:59","id":10}	1	0	pending	\N	\N	\N	2026-10-17 15:13:59	2026-10-17 15:13:59	\N	\N	\N	\N	\N
73	2906ddcd-f169-438b-8d08-f18fe3ee9bdb	1	1	Modules\\Orders\\Domain\\Entities\\Order	11	dcf06f08-aa7b-41ba-9f47-b55e6e940c17	update	{"id":11,"uuid":"dcf06f08-aa7b-41ba-9f47-b55e6e940c17","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0002","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":7200,"tax_amount":1150,"discount_amount":0,"total":7200,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 15:13:59","updated_at":"2026-10-17 15:13:59","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":7200,"net_amount":6050,"tip_amount":0,"amount_due":7200}	2	0	pending	\N	\N	\N	2026-10-17 15:13:59	2026-10-17 15:13:59	\N	\N	\N	\N	\N
74	7058ba7d-2247-421d-a93f-cf55c7ffa8e4	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	11	bd9fabef-a976-4d0d-b726-98930f19eb71	create	{"product_id":6,"company_id":1,"order_id":11,"menu_item_id":null,"name_snapshot":"Hamburguesa Cl\\u00e1sica","unit_price_snapshot":5500,"quantity":1,"notes":null,"subtotal":5500,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"bd9fabef-a976-4d0d-b726-98930f19eb71","version":1,"sync_status":"pending","updated_at":"2026-10-17 15:13:59","created_at":"2026-10-17 15:13:59","id":11}	1	0	pending	\N	\N	\N	2026-10-17 15:13:59	2026-10-17 15:13:59	\N	\N	\N	\N	\N
75	231cee33-427a-4351-a4e1-7a073d1f278c	1	1	Modules\\Orders\\Domain\\Entities\\Order	11	dcf06f08-aa7b-41ba-9f47-b55e6e940c17	update	{"id":11,"uuid":"dcf06f08-aa7b-41ba-9f47-b55e6e940c17","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0002","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":12700,"tax_amount":2028,"discount_amount":0,"total":12700,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 15:13:59","updated_at":"2026-10-17 15:13:59","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":12700,"net_amount":10672,"tip_amount":0,"amount_due":12700}	3	0	pending	\N	\N	\N	2026-10-17 15:13:59	2026-10-17 15:13:59	\N	\N	\N	\N	\N
76	2d278f47-9715-4815-a60f-f5f9e79c0d99	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	12	9bd6e96b-6b51-40cc-9b90-51f271d45e7f	create	{"product_id":15,"company_id":1,"order_id":11,"menu_item_id":null,"name_snapshot":"Agua Mineral","unit_price_snapshot":1000,"quantity":1,"notes":null,"subtotal":1000,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"9bd6e96b-6b51-40cc-9b90-51f271d45e7f","version":1,"sync_status":"pending","updated_at":"2026-10-17 15:13:59","created_at":"2026-10-17 15:13:59","id":12}	1	0	pending	\N	\N	\N	2026-10-17 15:13:59	2026-10-17 15:13:59	\N	\N	\N	\N	\N
96	ce1d4fad-dcfb-4617-be78-0c8a2180078a	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	18	f3e1dfaf-9a9f-4ee0-9aee-acc73b9afe59	create	{"product_id":7,"company_id":1,"order_id":15,"menu_item_id":null,"name_snapshot":"Hamburguesa Doble","unit_price_snapshot":7200,"quantity":2,"notes":null,"subtotal":14400,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"f3e1dfaf-9a9f-4ee0-9aee-acc73b9afe59","version":1,"sync_status":"pending","updated_at":"2026-10-17 15:50:37","created_at":"2026-10-17 15:50:37","id":18}	1	0	pending	\N	\N	\N	2026-10-17 15:50:37	2026-10-17 15:50:37	\N	\N	\N	\N	\N
77	7258b6a2-f07f-4565-9c14-ec387cb32266	1	1	Modules\\Orders\\Domain\\Entities\\Order	11	dcf06f08-aa7b-41ba-9f47-b55e6e940c17	update	{"id":11,"uuid":"dcf06f08-aa7b-41ba-9f47-b55e6e940c17","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0002","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":13700,"tax_amount":2187,"discount_amount":0,"total":13700,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 15:13:59","updated_at":"2026-10-17 15:13:59","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":4,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":13700,"net_amount":11513,"tip_amount":0,"amount_due":13700}	4	0	pending	\N	\N	\N	2026-10-17 15:13:59	2026-10-17 15:13:59	\N	\N	\N	\N	\N
78	b6e6a7cf-c44a-44cc-bc8f-c635507d17ab	1	1	Modules\\Orders\\Domain\\Entities\\Order	11	dcf06f08-aa7b-41ba-9f47-b55e6e940c17	update	{"id":11,"uuid":"dcf06f08-aa7b-41ba-9f47-b55e6e940c17","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0002","type":"takeout","status":"confirmed","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":13700,"tax_amount":2187,"discount_amount":0,"total":13700,"notes":null,"confirmed_at":"2026-10-17 15:13:59","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 15:13:59","updated_at":"2026-10-17 15:13:59","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":5,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":13700,"net_amount":11513,"tip_amount":0,"amount_due":13700}	5	0	pending	\N	\N	\N	2026-10-17 15:13:59	2026-10-17 15:13:59	\N	\N	\N	\N	\N
79	6c495397-3398-4bf6-b0b5-3a4f1426a4eb	1	1	Modules\\Orders\\Domain\\Entities\\Order	12	6cfc9534-bba3-4171-a473-4c4bbb1682c2	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0003","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"6cfc9534-bba3-4171-a473-4c4bbb1682c2","version":1,"sync_status":"pending","updated_at":"2026-10-17 15:16:29","created_at":"2026-10-17 15:16:29","id":12}	1	0	pending	\N	\N	\N	2026-10-17 15:16:29	2026-10-17 15:16:29	\N	\N	\N	\N	\N
80	6df6daf3-9270-4026-99f9-dfe7939e43bb	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	13	7a6d63f5-dd9e-4c5a-bc9d-723096ee379f	create	{"product_id":6,"company_id":1,"order_id":12,"menu_item_id":null,"name_snapshot":"Hamburguesa Cl\\u00e1sica","unit_price_snapshot":5500,"quantity":1,"notes":null,"subtotal":5500,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"7a6d63f5-dd9e-4c5a-bc9d-723096ee379f","version":1,"sync_status":"pending","updated_at":"2026-10-17 15:16:29","created_at":"2026-10-17 15:16:29","id":13}	1	0	pending	\N	\N	\N	2026-10-17 15:16:29	2026-10-17 15:16:29	\N	\N	\N	\N	\N
81	dfd8286d-48ad-4de5-8495-fa1699a2899b	1	1	Modules\\Orders\\Domain\\Entities\\Order	12	6cfc9534-bba3-4171-a473-4c4bbb1682c2	update	{"id":12,"uuid":"6cfc9534-bba3-4171-a473-4c4bbb1682c2","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0003","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":5500,"tax_amount":878,"discount_amount":0,"total":5500,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 15:16:29","updated_at":"2026-10-17 15:16:29","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":5500,"net_amount":4622,"tip_amount":0,"amount_due":5500}	2	0	pending	\N	\N	\N	2026-10-17 15:16:29	2026-10-17 15:16:29	\N	\N	\N	\N	\N
82	d156e7db-7297-4e8d-8a10-c34c0b4a93e4	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	14	86e26acc-ff1d-4a47-b8dd-423396206784	create	{"product_id":15,"company_id":1,"order_id":12,"menu_item_id":null,"name_snapshot":"Agua Mineral","unit_price_snapshot":1000,"quantity":1,"notes":null,"subtotal":1000,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"86e26acc-ff1d-4a47-b8dd-423396206784","version":1,"sync_status":"pending","updated_at":"2026-10-17 15:16:29","created_at":"2026-10-17 15:16:29","id":14}	1	0	pending	\N	\N	\N	2026-10-17 15:16:29	2026-10-17 15:16:29	\N	\N	\N	\N	\N
83	8119045f-76d7-4603-a9cc-dac3273d3c1b	1	1	Modules\\Orders\\Domain\\Entities\\Order	12	6cfc9534-bba3-4171-a473-4c4bbb1682c2	update	{"id":12,"uuid":"6cfc9534-bba3-4171-a473-4c4bbb1682c2","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0003","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":6500,"tax_amount":1038,"discount_amount":0,"total":6500,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 15:16:29","updated_at":"2026-10-17 15:16:29","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":6500,"net_amount":5462,"tip_amount":0,"amount_due":6500}	3	0	pending	\N	\N	\N	2026-10-17 15:16:29	2026-10-17 15:16:29	\N	\N	\N	\N	\N
84	b1b90f18-6861-45a7-8a8a-46853113045c	1	1	Modules\\Orders\\Domain\\Entities\\Order	12	6cfc9534-bba3-4171-a473-4c4bbb1682c2	update	{"id":12,"uuid":"6cfc9534-bba3-4171-a473-4c4bbb1682c2","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0003","type":"takeout","status":"confirmed","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":6500,"tax_amount":1038,"discount_amount":0,"total":6500,"notes":null,"confirmed_at":"2026-10-17 15:16:29","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 15:16:29","updated_at":"2026-10-17 15:16:29","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":4,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":6500,"net_amount":5462,"tip_amount":0,"amount_due":6500}	4	0	pending	\N	\N	\N	2026-10-17 15:16:29	2026-10-17 15:16:29	\N	\N	\N	\N	\N
85	aefe64c4-6fff-41ff-90c0-452efa1e6f81	1	1	Modules\\Orders\\Domain\\Entities\\Order	13	84e19ad2-e4f6-4bd9-86a4-76d8188cf7ab	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0004","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"84e19ad2-e4f6-4bd9-86a4-76d8188cf7ab","version":1,"sync_status":"pending","updated_at":"2026-10-17 15:17:29","created_at":"2026-10-17 15:17:29","id":13}	1	0	pending	\N	\N	\N	2026-10-17 15:17:29	2026-10-17 15:17:29	\N	\N	\N	\N	\N
87	7c7db9e1-de2d-4bf5-b074-837b20e91b3c	1	1	Modules\\Orders\\Domain\\Entities\\Order	13	84e19ad2-e4f6-4bd9-86a4-76d8188cf7ab	update	{"id":13,"uuid":"84e19ad2-e4f6-4bd9-86a4-76d8188cf7ab","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0004","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":5500,"tax_amount":878,"discount_amount":0,"total":5500,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 15:17:29","updated_at":"2026-10-17 15:17:29","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":5500,"net_amount":4622,"tip_amount":0,"amount_due":5500}	2	0	pending	\N	\N	\N	2026-10-17 15:17:29	2026-10-17 15:17:29	\N	\N	\N	\N	\N
88	a54019fb-7501-4d52-805a-be867d50a6a2	1	1	Modules\\Orders\\Domain\\Entities\\Order	13	84e19ad2-e4f6-4bd9-86a4-76d8188cf7ab	update	{"id":13,"uuid":"84e19ad2-e4f6-4bd9-86a4-76d8188cf7ab","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0004","type":"takeout","status":"confirmed","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":5500,"tax_amount":878,"discount_amount":0,"total":5500,"notes":null,"confirmed_at":"2026-10-17 15:17:29","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 15:17:29","updated_at":"2026-10-17 15:17:29","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":5500,"net_amount":4622,"tip_amount":0,"amount_due":5500}	3	0	pending	\N	\N	\N	2026-10-17 15:17:29	2026-10-17 15:17:29	\N	\N	\N	\N	\N
89	f3484626-25a2-44fd-a52b-178301724293	1	1	Modules\\Orders\\Domain\\Entities\\Order	14	15129255-33d6-4d66-b7fd-a266bbe1f860	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0005","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"15129255-33d6-4d66-b7fd-a266bbe1f860","version":1,"sync_status":"pending","updated_at":"2026-10-17 15:19:14","created_at":"2026-10-17 15:19:14","id":14}	1	0	pending	\N	\N	\N	2026-10-17 15:19:14	2026-10-17 15:19:14	\N	\N	\N	\N	\N
90	6271d710-8ced-45a3-8365-0d17bff6bf17	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	16	32e67eae-8807-45e6-a857-ca70347e325b	create	{"product_id":6,"company_id":1,"order_id":14,"menu_item_id":null,"name_snapshot":"Hamburguesa Cl\\u00e1sica","unit_price_snapshot":5500,"quantity":1,"notes":null,"subtotal":5500,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"32e67eae-8807-45e6-a857-ca70347e325b","version":1,"sync_status":"pending","updated_at":"2026-10-17 15:19:14","created_at":"2026-10-17 15:19:14","id":16}	1	0	pending	\N	\N	\N	2026-10-17 15:19:14	2026-10-17 15:19:14	\N	\N	\N	\N	\N
91	7977f10c-0ca9-4fa6-8d72-ea2547a17734	1	1	Modules\\Orders\\Domain\\Entities\\Order	14	15129255-33d6-4d66-b7fd-a266bbe1f860	update	{"id":14,"uuid":"15129255-33d6-4d66-b7fd-a266bbe1f860","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0005","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":5500,"tax_amount":878,"discount_amount":0,"total":5500,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 15:19:14","updated_at":"2026-10-17 15:19:14","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":5500,"net_amount":4622,"tip_amount":0,"amount_due":5500}	2	0	pending	\N	\N	\N	2026-10-17 15:19:14	2026-10-17 15:19:14	\N	\N	\N	\N	\N
92	1821cdfc-bf51-4408-927c-e0a14a0b49de	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	17	e876192a-2dac-402b-85e2-1458b7fe1cde	create	{"product_id":7,"company_id":1,"order_id":14,"menu_item_id":null,"name_snapshot":"Hamburguesa Doble","unit_price_snapshot":7200,"quantity":1,"notes":null,"subtotal":7200,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"e876192a-2dac-402b-85e2-1458b7fe1cde","version":1,"sync_status":"pending","updated_at":"2026-10-17 15:19:14","created_at":"2026-10-17 15:19:14","id":17}	1	0	pending	\N	\N	\N	2026-10-17 15:19:14	2026-10-17 15:19:14	\N	\N	\N	\N	\N
93	8a184b42-d7d7-4ca8-aacf-a8458b7bbfb6	1	1	Modules\\Orders\\Domain\\Entities\\Order	14	15129255-33d6-4d66-b7fd-a266bbe1f860	update	{"id":14,"uuid":"15129255-33d6-4d66-b7fd-a266bbe1f860","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0005","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":12700,"tax_amount":2028,"discount_amount":0,"total":12700,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 15:19:14","updated_at":"2026-10-17 15:19:14","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":12700,"net_amount":10672,"tip_amount":0,"amount_due":12700}	3	0	pending	\N	\N	\N	2026-10-17 15:19:14	2026-10-17 15:19:14	\N	\N	\N	\N	\N
94	eae636cd-ecf1-46a2-a341-bda02402b78f	1	1	Modules\\Orders\\Domain\\Entities\\Order	14	15129255-33d6-4d66-b7fd-a266bbe1f860	update	{"id":14,"uuid":"15129255-33d6-4d66-b7fd-a266bbe1f860","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0005","type":"takeout","status":"confirmed","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":12700,"tax_amount":2028,"discount_amount":0,"total":12700,"notes":null,"confirmed_at":"2026-10-17 15:19:14","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 15:19:14","updated_at":"2026-10-17 15:19:14","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":4,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":12700,"net_amount":10672,"tip_amount":0,"amount_due":12700}	4	0	pending	\N	\N	\N	2026-10-17 15:19:14	2026-10-17 15:19:14	\N	\N	\N	\N	\N
95	26613dff-2d4f-4811-b951-3ba25bc0015d	1	1	Modules\\Orders\\Domain\\Entities\\Order	15	953a0899-7047-41a4-a386-9032159c99a8	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0006","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"953a0899-7047-41a4-a386-9032159c99a8","version":1,"sync_status":"pending","updated_at":"2026-10-17 15:50:36","created_at":"2026-10-17 15:50:36","id":15}	1	0	pending	\N	\N	\N	2026-10-17 15:50:36	2026-10-17 15:50:36	\N	\N	\N	\N	\N
97	225e5ec9-510e-40c5-a405-214ed088a601	1	1	Modules\\Orders\\Domain\\Entities\\Order	15	953a0899-7047-41a4-a386-9032159c99a8	update	{"id":15,"uuid":"953a0899-7047-41a4-a386-9032159c99a8","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0006","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":14400,"tax_amount":2299,"discount_amount":0,"total":14400,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 15:50:36","updated_at":"2026-10-17 15:50:37","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":14400,"net_amount":12101,"tip_amount":0,"amount_due":14400}	2	0	pending	\N	\N	\N	2026-10-17 15:50:37	2026-10-17 15:50:37	\N	\N	\N	\N	\N
98	33f81b4c-c589-4659-9f8f-ec297f8bf8ae	1	1	Modules\\Orders\\Domain\\Entities\\Order	15	953a0899-7047-41a4-a386-9032159c99a8	update	{"id":15,"uuid":"953a0899-7047-41a4-a386-9032159c99a8","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0006","type":"takeout","status":"confirmed","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":14400,"tax_amount":2299,"discount_amount":0,"total":14400,"notes":null,"confirmed_at":"2026-10-17 15:50:37","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 15:50:36","updated_at":"2026-10-17 15:50:37","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":14400,"net_amount":12101,"tip_amount":0,"amount_due":14400}	3	0	pending	\N	\N	\N	2026-10-17 15:50:37	2026-10-17 15:50:37	\N	\N	\N	\N	\N
99	e81b6803-8a2b-488a-82fe-a432298a5043	1	1	Modules\\Orders\\Domain\\Entities\\Order	16	6c66e010-b93b-4c1d-8f21-b3d0f5e9babe	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0007","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"6c66e010-b93b-4c1d-8f21-b3d0f5e9babe","version":1,"sync_status":"pending","updated_at":"2026-10-17 15:54:51","created_at":"2026-10-17 15:54:51","id":16}	1	0	pending	\N	\N	\N	2026-10-17 15:54:51	2026-10-17 15:54:51	\N	\N	\N	\N	\N
100	d069ff88-4972-4022-9e72-5069ccfd17ce	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	19	b5bd218a-4b78-4bab-84ea-f50208f8370e	create	{"product_id":7,"company_id":1,"order_id":16,"menu_item_id":null,"name_snapshot":"Hamburguesa Doble","unit_price_snapshot":7200,"quantity":1,"notes":null,"subtotal":7200,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"b5bd218a-4b78-4bab-84ea-f50208f8370e","version":1,"sync_status":"pending","updated_at":"2026-10-17 15:54:51","created_at":"2026-10-17 15:54:51","id":19}	1	0	pending	\N	\N	\N	2026-10-17 15:54:51	2026-10-17 15:54:51	\N	\N	\N	\N	\N
101	affc037d-dddc-4876-a1fc-25cd9886bb29	1	1	Modules\\Orders\\Domain\\Entities\\Order	16	6c66e010-b93b-4c1d-8f21-b3d0f5e9babe	update	{"id":16,"uuid":"6c66e010-b93b-4c1d-8f21-b3d0f5e9babe","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0007","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":7200,"tax_amount":1150,"discount_amount":0,"total":7200,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 15:54:51","updated_at":"2026-10-17 15:54:51","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":7200,"net_amount":6050,"tip_amount":0,"amount_due":7200}	2	0	pending	\N	\N	\N	2026-10-17 15:54:51	2026-10-17 15:54:51	\N	\N	\N	\N	\N
102	fca5532e-62e8-4c7c-aa3b-f59c09de8f23	1	1	Modules\\Orders\\Domain\\Entities\\Order	16	6c66e010-b93b-4c1d-8f21-b3d0f5e9babe	update	{"id":16,"uuid":"6c66e010-b93b-4c1d-8f21-b3d0f5e9babe","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0007","type":"takeout","status":"confirmed","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":7200,"tax_amount":1150,"discount_amount":0,"total":7200,"notes":null,"confirmed_at":"2026-10-17 15:54:52","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 15:54:51","updated_at":"2026-10-17 15:54:52","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":7200,"net_amount":6050,"tip_amount":0,"amount_due":7200}	3	0	pending	\N	\N	\N	2026-10-17 15:54:52	2026-10-17 15:54:52	\N	\N	\N	\N	\N
103	997c18ce-e228-4981-9d34-3bde84416985	1	1	Modules\\Orders\\Domain\\Entities\\Order	17	5326a415-d42f-4d41-81cf-f4d6ca584a89	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0008","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"5326a415-d42f-4d41-81cf-f4d6ca584a89","version":1,"sync_status":"pending","updated_at":"2026-10-17 16:51:33","created_at":"2026-10-17 16:51:33","id":17}	1	0	pending	\N	\N	\N	2026-10-17 16:51:33	2026-10-17 16:51:33	\N	\N	\N	\N	\N
104	891c6e0c-3bab-4ef9-8678-ee3f7cf96472	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	20	02143e39-cb2e-4737-8b60-8ad852ec5d2f	create	{"product_id":15,"company_id":1,"order_id":17,"menu_item_id":null,"name_snapshot":"Agua Mineral","unit_price_snapshot":1000,"quantity":1,"notes":null,"subtotal":1000,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"02143e39-cb2e-4737-8b60-8ad852ec5d2f","version":1,"sync_status":"pending","updated_at":"2026-10-17 16:51:33","created_at":"2026-10-17 16:51:33","id":20}	1	0	pending	\N	\N	\N	2026-10-17 16:51:33	2026-10-17 16:51:33	\N	\N	\N	\N	\N
105	5dcf5f7b-8d6f-4075-b380-ee45c96764c8	1	1	Modules\\Orders\\Domain\\Entities\\Order	17	5326a415-d42f-4d41-81cf-f4d6ca584a89	update	{"id":17,"uuid":"5326a415-d42f-4d41-81cf-f4d6ca584a89","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0008","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":1000,"tax_amount":160,"discount_amount":0,"total":1000,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 16:51:33","updated_at":"2026-10-17 16:51:33","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":1000,"net_amount":840,"tip_amount":0,"amount_due":1000}	2	0	pending	\N	\N	\N	2026-10-17 16:51:33	2026-10-17 16:51:33	\N	\N	\N	\N	\N
106	e30369ca-2d4f-4ead-8378-5f5321b24605	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	21	df963b27-9061-4dbc-b6bf-d4e2740a1703	create	{"product_id":10,"company_id":1,"order_id":17,"menu_item_id":null,"name_snapshot":"Aros de Cebolla","unit_price_snapshot":2800,"quantity":1,"notes":null,"subtotal":2800,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"df963b27-9061-4dbc-b6bf-d4e2740a1703","version":1,"sync_status":"pending","updated_at":"2026-10-17 16:51:33","created_at":"2026-10-17 16:51:33","id":21}	1	0	pending	\N	\N	\N	2026-10-17 16:51:33	2026-10-17 16:51:33	\N	\N	\N	\N	\N
107	abd13f05-57a8-435d-80a8-0e9d662c04c4	1	1	Modules\\Orders\\Domain\\Entities\\Order	17	5326a415-d42f-4d41-81cf-f4d6ca584a89	update	{"id":17,"uuid":"5326a415-d42f-4d41-81cf-f4d6ca584a89","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0008","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":3800,"tax_amount":607,"discount_amount":0,"total":3800,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 16:51:33","updated_at":"2026-10-17 16:51:33","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":3800,"net_amount":3193,"tip_amount":0,"amount_due":3800}	3	0	pending	\N	\N	\N	2026-10-17 16:51:33	2026-10-17 16:51:33	\N	\N	\N	\N	\N
108	a7b525b7-47b0-4bf5-97f2-9e09b9646fef	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	22	5b8c8563-6150-4831-a82f-8ca4bfe9deeb	create	{"product_id":5,"company_id":1,"order_id":17,"menu_item_id":null,"name_snapshot":"Caldillo de Congrio","unit_price_snapshot":8900,"quantity":1,"notes":null,"subtotal":8900,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"5b8c8563-6150-4831-a82f-8ca4bfe9deeb","version":1,"sync_status":"pending","updated_at":"2026-10-17 16:51:33","created_at":"2026-10-17 16:51:33","id":22}	1	0	pending	\N	\N	\N	2026-10-17 16:51:33	2026-10-17 16:51:33	\N	\N	\N	\N	\N
109	4360a017-e9f5-4750-9e53-8317fb763d4c	1	1	Modules\\Orders\\Domain\\Entities\\Order	17	5326a415-d42f-4d41-81cf-f4d6ca584a89	update	{"id":17,"uuid":"5326a415-d42f-4d41-81cf-f4d6ca584a89","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0008","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":12700,"tax_amount":2028,"discount_amount":0,"total":12700,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 16:51:33","updated_at":"2026-10-17 16:51:33","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":4,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":12700,"net_amount":10672,"tip_amount":0,"amount_due":12700}	4	0	pending	\N	\N	\N	2026-10-17 16:51:33	2026-10-17 16:51:33	\N	\N	\N	\N	\N
110	105f3c32-700f-462c-9e3f-96709746087e	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	23	85ad35be-5de0-4dba-b0e6-1defbe673164	create	{"product_id":16,"company_id":1,"order_id":17,"menu_item_id":null,"name_snapshot":"Cerveza Artesanal","unit_price_snapshot":3800,"quantity":1,"notes":null,"subtotal":3800,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"85ad35be-5de0-4dba-b0e6-1defbe673164","version":1,"sync_status":"pending","updated_at":"2026-10-17 16:51:33","created_at":"2026-10-17 16:51:33","id":23}	1	0	pending	\N	\N	\N	2026-10-17 16:51:33	2026-10-17 16:51:33	\N	\N	\N	\N	\N
111	e851e4b0-5b59-4a02-becb-1d985ec1b529	1	1	Modules\\Orders\\Domain\\Entities\\Order	17	5326a415-d42f-4d41-81cf-f4d6ca584a89	update	{"id":17,"uuid":"5326a415-d42f-4d41-81cf-f4d6ca584a89","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0008","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":16500,"tax_amount":2634,"discount_amount":0,"total":16500,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 16:51:33","updated_at":"2026-10-17 16:51:33","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":5,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":16500,"net_amount":13866,"tip_amount":0,"amount_due":16500}	5	0	pending	\N	\N	\N	2026-10-17 16:51:33	2026-10-17 16:51:33	\N	\N	\N	\N	\N
112	8e6705c9-1767-4bc3-98e8-5ca91c7553f4	1	1	Modules\\Orders\\Domain\\Entities\\Order	17	5326a415-d42f-4d41-81cf-f4d6ca584a89	update	{"id":17,"uuid":"5326a415-d42f-4d41-81cf-f4d6ca584a89","company_id":1,"branch_id":1,"order_number":"ORD-001-20261017-0008","type":"takeout","status":"confirmed","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":16500,"tax_amount":2634,"discount_amount":0,"total":16500,"notes":null,"confirmed_at":"2026-10-17 16:51:33","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-17 16:51:33","updated_at":"2026-10-17 16:51:33","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":6,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":16500,"net_amount":13866,"tip_amount":0,"amount_due":16500}	6	0	pending	\N	\N	\N	2026-10-17 16:51:33	2026-10-17 16:51:33	\N	\N	\N	\N	\N
113	a37cfebc-cdb8-419a-a9ad-825e561629c9	1	1	Modules\\Orders\\Domain\\Entities\\Order	18	246376f8-6d91-4963-90ee-d7c6c15603cf	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0001","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","version":1,"sync_status":"pending","updated_at":"2026-10-19 12:35:05","created_at":"2026-10-19 12:35:05","id":18}	1	0	pending	\N	\N	\N	2026-10-19 12:35:05	2026-10-19 12:35:05	\N	\N	\N	\N	\N
114	8a1f7139-b747-4385-9907-55574abfe710	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	24	8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad	create	{"product_id":15,"company_id":1,"order_id":18,"menu_item_id":null,"name_snapshot":"Agua Mineral","unit_price_snapshot":1000,"quantity":1,"notes":null,"subtotal":1000,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"8065ec05-b4fa-4fda-a3e1-1351f9e8c3ad","version":1,"sync_status":"pending","updated_at":"2026-10-19 12:35:05","created_at":"2026-10-19 12:35:05","id":24}	1	0	pending	\N	\N	\N	2026-10-19 12:35:05	2026-10-19 12:35:05	\N	\N	\N	\N	\N
134	0dce99db-ed95-4368-9058-a5ba3dbfbc24	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	33	0b685d54-bcda-4848-84a4-343086387c1f	create	{"product_id":12,"company_id":1,"order_id":19,"menu_item_id":null,"name_snapshot":"Coca-Cola 500ml","unit_price_snapshot":1500,"quantity":1,"notes":null,"subtotal":1500,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"0b685d54-bcda-4848-84a4-343086387c1f","version":1,"sync_status":"pending","updated_at":"2026-10-19 12:36:05","created_at":"2026-10-19 12:36:05","id":33}	1	0	pending	\N	\N	\N	2026-10-19 12:36:05	2026-10-19 12:36:05	\N	\N	\N	\N	\N
115	17a86926-797c-438d-8c29-2b683cf11a8e	1	1	Modules\\Orders\\Domain\\Entities\\Order	18	246376f8-6d91-4963-90ee-d7c6c15603cf	update	{"id":18,"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0001","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":1000,"tax_amount":160,"discount_amount":0,"total":1000,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19 12:35:05","updated_at":"2026-10-19 12:35:05","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":1000,"net_amount":840,"tip_amount":0,"amount_due":1000}	2	0	pending	\N	\N	\N	2026-10-19 12:35:05	2026-10-19 12:35:05	\N	\N	\N	\N	\N
116	903b3d01-578b-4f4d-9243-35e77cc702e4	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	25	b52f7738-a75c-40a2-b6c4-598eaa6947cf	create	{"product_id":10,"company_id":1,"order_id":18,"menu_item_id":null,"name_snapshot":"Aros de Cebolla","unit_price_snapshot":2800,"quantity":1,"notes":null,"subtotal":2800,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"b52f7738-a75c-40a2-b6c4-598eaa6947cf","version":1,"sync_status":"pending","updated_at":"2026-10-19 12:35:05","created_at":"2026-10-19 12:35:05","id":25}	1	0	pending	\N	\N	\N	2026-10-19 12:35:05	2026-10-19 12:35:05	\N	\N	\N	\N	\N
117	ccd8ce76-0b5f-42c3-a96d-31843b949f12	1	1	Modules\\Orders\\Domain\\Entities\\Order	18	246376f8-6d91-4963-90ee-d7c6c15603cf	update	{"id":18,"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0001","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":3800,"tax_amount":607,"discount_amount":0,"total":3800,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19 12:35:05","updated_at":"2026-10-19 12:35:05","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":3800,"net_amount":3193,"tip_amount":0,"amount_due":3800}	3	0	pending	\N	\N	\N	2026-10-19 12:35:05	2026-10-19 12:35:05	\N	\N	\N	\N	\N
118	faa6b7b8-24b3-4e02-876f-c5f37e93ba29	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	26	57940fcf-0400-4ddb-a1ec-8dce90a98bb2	create	{"product_id":5,"company_id":1,"order_id":18,"menu_item_id":null,"name_snapshot":"Caldillo de Congrio","unit_price_snapshot":8900,"quantity":1,"notes":null,"subtotal":8900,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"57940fcf-0400-4ddb-a1ec-8dce90a98bb2","version":1,"sync_status":"pending","updated_at":"2026-10-19 12:35:06","created_at":"2026-10-19 12:35:06","id":26}	1	0	pending	\N	\N	\N	2026-10-19 12:35:06	2026-10-19 12:35:06	\N	\N	\N	\N	\N
119	49f55946-b958-44e5-8789-4d59809638f3	1	1	Modules\\Orders\\Domain\\Entities\\Order	18	246376f8-6d91-4963-90ee-d7c6c15603cf	update	{"id":18,"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0001","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":12700,"tax_amount":2028,"discount_amount":0,"total":12700,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19 12:35:05","updated_at":"2026-10-19 12:35:06","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":4,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":12700,"net_amount":10672,"tip_amount":0,"amount_due":12700}	4	0	pending	\N	\N	\N	2026-10-19 12:35:06	2026-10-19 12:35:06	\N	\N	\N	\N	\N
120	e8ffc305-8e64-4bef-b290-1baa74c21ada	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	27	7b8fabec-b9c4-408f-9b24-651c178e8f9d	create	{"product_id":16,"company_id":1,"order_id":18,"menu_item_id":null,"name_snapshot":"Cerveza Artesanal","unit_price_snapshot":3800,"quantity":1,"notes":null,"subtotal":3800,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"7b8fabec-b9c4-408f-9b24-651c178e8f9d","version":1,"sync_status":"pending","updated_at":"2026-10-19 12:35:06","created_at":"2026-10-19 12:35:06","id":27}	1	0	pending	\N	\N	\N	2026-10-19 12:35:06	2026-10-19 12:35:06	\N	\N	\N	\N	\N
121	757910e4-d6cf-4d36-94b1-957d084453f0	1	1	Modules\\Orders\\Domain\\Entities\\Order	18	246376f8-6d91-4963-90ee-d7c6c15603cf	update	{"id":18,"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0001","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":16500,"tax_amount":2634,"discount_amount":0,"total":16500,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19 12:35:05","updated_at":"2026-10-19 12:35:06","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":5,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":16500,"net_amount":13866,"tip_amount":0,"amount_due":16500}	5	0	pending	\N	\N	\N	2026-10-19 12:35:06	2026-10-19 12:35:06	\N	\N	\N	\N	\N
122	6763d881-e4c5-4495-bf78-2233c7f2d41a	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	28	3768949b-51a3-4fab-a948-7d55c10be99b	create	{"product_id":7,"company_id":1,"order_id":18,"menu_item_id":null,"name_snapshot":"Hamburguesa Doble","unit_price_snapshot":7200,"quantity":1,"notes":null,"subtotal":7200,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"3768949b-51a3-4fab-a948-7d55c10be99b","version":1,"sync_status":"pending","updated_at":"2026-10-19 12:35:06","created_at":"2026-10-19 12:35:06","id":28}	1	0	pending	\N	\N	\N	2026-10-19 12:35:06	2026-10-19 12:35:06	\N	\N	\N	\N	\N
123	17745123-1aa5-4eab-8df4-280a0e11f34d	1	1	Modules\\Orders\\Domain\\Entities\\Order	18	246376f8-6d91-4963-90ee-d7c6c15603cf	update	{"id":18,"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0001","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":23700,"tax_amount":3784,"discount_amount":0,"total":23700,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19 12:35:05","updated_at":"2026-10-19 12:35:06","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":6,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":23700,"net_amount":19916,"tip_amount":0,"amount_due":23700}	6	0	pending	\N	\N	\N	2026-10-19 12:35:06	2026-10-19 12:35:06	\N	\N	\N	\N	\N
124	8c91fce3-5eae-4b8e-801a-dd0be7da458e	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	29	2e16d908-2651-4250-99c1-ab0b016f6075	create	{"product_id":6,"company_id":1,"order_id":18,"menu_item_id":null,"name_snapshot":"Hamburguesa Cl\\u00e1sica","unit_price_snapshot":5500,"quantity":1,"notes":null,"subtotal":5500,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"2e16d908-2651-4250-99c1-ab0b016f6075","version":1,"sync_status":"pending","updated_at":"2026-10-19 12:35:06","created_at":"2026-10-19 12:35:06","id":29}	1	0	pending	\N	\N	\N	2026-10-19 12:35:06	2026-10-19 12:35:06	\N	\N	\N	\N	\N
125	e9a24b14-209b-4aba-864b-c64aea397399	1	1	Modules\\Orders\\Domain\\Entities\\Order	18	246376f8-6d91-4963-90ee-d7c6c15603cf	update	{"id":18,"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0001","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":29200,"tax_amount":4662,"discount_amount":0,"total":29200,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19 12:35:05","updated_at":"2026-10-19 12:35:06","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":7,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":29200,"net_amount":24538,"tip_amount":0,"amount_due":29200}	7	0	pending	\N	\N	\N	2026-10-19 12:35:06	2026-10-19 12:35:06	\N	\N	\N	\N	\N
126	f5c8ef8b-39a0-4692-9cca-28660bf3e7c2	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	30	92f5ac8f-940a-4485-8cc1-66d7dc7312e0	create	{"product_id":11,"company_id":1,"order_id":18,"menu_item_id":null,"name_snapshot":"Ensalada Mixta","unit_price_snapshot":3200,"quantity":1,"notes":null,"subtotal":3200,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"92f5ac8f-940a-4485-8cc1-66d7dc7312e0","version":1,"sync_status":"pending","updated_at":"2026-10-19 12:35:06","created_at":"2026-10-19 12:35:06","id":30}	1	0	pending	\N	\N	\N	2026-10-19 12:35:06	2026-10-19 12:35:06	\N	\N	\N	\N	\N
127	6d3cdddc-a149-481c-be70-a65e41d10b7c	1	1	Modules\\Orders\\Domain\\Entities\\Order	18	246376f8-6d91-4963-90ee-d7c6c15603cf	update	{"id":18,"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0001","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":32400,"tax_amount":5173,"discount_amount":0,"total":32400,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19 12:35:05","updated_at":"2026-10-19 12:35:07","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":8,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":32400,"net_amount":27227,"tip_amount":0,"amount_due":32400}	8	0	pending	\N	\N	\N	2026-10-19 12:35:07	2026-10-19 12:35:07	\N	\N	\N	\N	\N
128	33648e58-7fc6-4a37-a6b8-edc7183468c3	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	31	1710bb9a-3340-40b6-b54d-35f117dd47e2	create	{"product_id":1,"company_id":1,"order_id":18,"menu_item_id":null,"name_snapshot":"Empanadas de Pino (3un)","unit_price_snapshot":3500,"quantity":1,"notes":null,"subtotal":3500,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"1710bb9a-3340-40b6-b54d-35f117dd47e2","version":1,"sync_status":"pending","updated_at":"2026-10-19 12:35:07","created_at":"2026-10-19 12:35:07","id":31}	1	0	pending	\N	\N	\N	2026-10-19 12:35:07	2026-10-19 12:35:07	\N	\N	\N	\N	\N
129	d8b9e6df-876d-40d3-a995-d601b3f2dc27	1	1	Modules\\Orders\\Domain\\Entities\\Order	18	246376f8-6d91-4963-90ee-d7c6c15603cf	update	{"id":18,"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0001","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":35900,"tax_amount":5732,"discount_amount":0,"total":35900,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19 12:35:05","updated_at":"2026-10-19 12:35:07","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":9,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":35900,"net_amount":30168,"tip_amount":0,"amount_due":35900}	9	0	pending	\N	\N	\N	2026-10-19 12:35:07	2026-10-19 12:35:07	\N	\N	\N	\N	\N
130	39821ccc-fd22-4cd0-9959-f48472c09aab	1	1	Modules\\Orders\\Domain\\Entities\\Order	18	246376f8-6d91-4963-90ee-d7c6c15603cf	update	{"id":18,"uuid":"246376f8-6d91-4963-90ee-d7c6c15603cf","company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0001","type":"takeout","status":"confirmed","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":35900,"tax_amount":5732,"discount_amount":0,"total":35900,"notes":null,"confirmed_at":"2026-10-19 12:35:07","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19 12:35:05","updated_at":"2026-10-19 12:35:07","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":10,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":35900,"net_amount":30168,"tip_amount":0,"amount_due":35900}	10	0	pending	\N	\N	\N	2026-10-19 12:35:07	2026-10-19 12:35:07	\N	\N	\N	\N	\N
131	f12cae01-f5cb-4008-9220-de73b839086b	1	1	Modules\\Orders\\Domain\\Entities\\Order	19	c08fab23-31b1-433a-993c-aa8348c6fe63	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0002","type":"dine_in","fulfillment_channel":"onsite","status":"draft","table_id":14,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"c08fab23-31b1-433a-993c-aa8348c6fe63","version":1,"sync_status":"pending","updated_at":"2026-10-19 12:36:05","created_at":"2026-10-19 12:36:05","id":19}	1	0	pending	\N	\N	\N	2026-10-19 12:36:05	2026-10-19 12:36:05	\N	\N	\N	\N	\N
132	a2bba9e2-ecde-477f-94ea-e1ab169276b4	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	32	34384e04-f4dc-4442-ad98-32a927689982	create	{"product_id":15,"company_id":1,"order_id":19,"menu_item_id":null,"name_snapshot":"Agua Mineral","unit_price_snapshot":1000,"quantity":1,"notes":null,"subtotal":1000,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"34384e04-f4dc-4442-ad98-32a927689982","version":1,"sync_status":"pending","updated_at":"2026-10-19 12:36:05","created_at":"2026-10-19 12:36:05","id":32}	1	0	pending	\N	\N	\N	2026-10-19 12:36:05	2026-10-19 12:36:05	\N	\N	\N	\N	\N
133	ab81bc41-536c-4b25-a8af-c27b3faebb20	1	1	Modules\\Orders\\Domain\\Entities\\Order	19	c08fab23-31b1-433a-993c-aa8348c6fe63	update	{"id":19,"uuid":"c08fab23-31b1-433a-993c-aa8348c6fe63","company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0002","type":"dine_in","status":"draft","table_id":14,"waiter_id":1,"cashier_id":null,"subtotal":1000,"tax_amount":160,"discount_amount":0,"total":1000,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19 12:36:05","updated_at":"2026-10-19 12:36:05","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"onsite","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":1000,"net_amount":840,"tip_amount":0,"amount_due":1000}	2	0	pending	\N	\N	\N	2026-10-19 12:36:05	2026-10-19 12:36:05	\N	\N	\N	\N	\N
135	13ba39a8-1ee4-4a9b-b0b0-477e16c416b6	1	1	Modules\\Orders\\Domain\\Entities\\Order	19	c08fab23-31b1-433a-993c-aa8348c6fe63	update	{"id":19,"uuid":"c08fab23-31b1-433a-993c-aa8348c6fe63","company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0002","type":"dine_in","status":"draft","table_id":14,"waiter_id":1,"cashier_id":null,"subtotal":2500,"tax_amount":399,"discount_amount":0,"total":2500,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19 12:36:05","updated_at":"2026-10-19 12:36:05","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"onsite","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":2500,"net_amount":2101,"tip_amount":0,"amount_due":2500}	3	0	pending	\N	\N	\N	2026-10-19 12:36:06	2026-10-19 12:36:06	\N	\N	\N	\N	\N
136	304e6d08-56ad-44a4-a7ee-85f009988007	1	1	Modules\\Orders\\Domain\\Entities\\Order	19	c08fab23-31b1-433a-993c-aa8348c6fe63	update	{"id":19,"uuid":"c08fab23-31b1-433a-993c-aa8348c6fe63","company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0002","type":"dine_in","status":"confirmed","table_id":14,"waiter_id":1,"cashier_id":null,"subtotal":2500,"tax_amount":399,"discount_amount":0,"total":2500,"notes":null,"confirmed_at":"2026-10-19 12:36:06","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19 12:36:05","updated_at":"2026-10-19 12:36:06","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":4,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"onsite","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":2500,"net_amount":2101,"tip_amount":0,"amount_due":2500}	4	0	pending	\N	\N	\N	2026-10-19 12:36:06	2026-10-19 12:36:06	\N	\N	\N	\N	\N
137	a2f21660-bdd0-4c14-b797-98422268eacb	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	14	4ce6978a-4c19-4f52-bce0-d2e45b92d0c1	update	{"id":14,"uuid":"4ce6978a-4c19-4f52-bce0-d2e45b92d0c1","company_id":1,"branch_id":1,"area_code":"BAR","area_name_translations":"{\\"es\\": \\"Barra\\", \\"zh\\": \\"\\u5427\\u53f0\\"}","table_number":"B-03","capacity":2,"status":"occupied","current_order_id":19,"created_at":"2026-10-12 00:16:46","updated_at":"2026-10-19 12:36:06","deleted_at":null,"sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"zone_id":null,"position_x":0,"position_y":0,"rotation":0,"shape":"square","width":null,"height":null}	2	0	pending	\N	\N	\N	2026-10-19 12:36:06	2026-10-19 12:36:06	\N	\N	\N	\N	\N
138	5fba1bac-bb6c-41c6-970f-9d62fd7c3469	1	1	Modules\\Payments\\Domain\\Entities\\Bill	1	522b5b55-93c9-49cd-adda-98846aa007ec	create	{"company_id":1,"branch_id":1,"order_id":10,"bill_number":"ORD-001-20261017-0001-1","type":"single","subtotal":7200,"tax_amount":1150,"discount_amount":0,"tip_amount":0,"total":7200,"paid_amount":0,"remaining_amount":7200,"status":"open","guest_count":1,"uuid":"522b5b55-93c9-49cd-adda-98846aa007ec","version":1,"sync_status":"pending","updated_at":"2026-10-19 18:07:49","created_at":"2026-10-19 18:07:49","id":1}	1	0	pending	\N	\N	\N	2026-10-19 18:07:49	2026-10-19 18:07:49	\N	\N	\N	\N	\N
139	92ed7641-a9dd-4da8-bce7-84372ee93e67	1	1	Modules\\Payments\\Domain\\Entities\\Bill	1	522b5b55-93c9-49cd-adda-98846aa007ec	update	{"company_id":1,"branch_id":1,"order_id":10,"bill_number":"ORD-001-20261017-0001-1","type":"single","subtotal":7200,"tax_amount":1150,"discount_amount":0,"tip_amount":0,"total":7200,"paid_amount":0,"remaining_amount":7200,"status":"open","guest_count":1,"uuid":"522b5b55-93c9-49cd-adda-98846aa007ec","version":2,"sync_status":"pending","updated_at":"2026-10-19 18:07:49","created_at":"2026-10-19 18:07:49","id":1,"idempotency_key":"32e1b173-4f22-4bde-b538-2045c55cb1c6","payload_hash":"6a56d7cabb9eacb50ec883d02755a388712ea3ada6f8ebdaf0d55b0f04f76de2"}	2	0	pending	\N	\N	\N	2026-10-19 18:07:49	2026-10-19 18:07:49	\N	\N	\N	\N	\N
140	5d8f61f8-293a-44a0-9150-8f1442bb24d8	1	1	Modules\\Payments\\Domain\\Entities\\Bill	2	cae81774-4a91-421e-a422-5fd75b524a69	create	{"company_id":1,"branch_id":1,"order_id":19,"bill_number":"ORD-001-20261019-0002-1","type":"single","subtotal":2500,"tax_amount":399,"discount_amount":0,"tip_amount":0,"total":2500,"paid_amount":0,"remaining_amount":2500,"status":"open","guest_count":1,"uuid":"cae81774-4a91-421e-a422-5fd75b524a69","version":1,"sync_status":"pending","updated_at":"2026-10-19 18:12:04","created_at":"2026-10-19 18:12:04","id":2}	1	0	pending	\N	\N	\N	2026-10-19 18:12:04	2026-10-19 18:12:04	\N	\N	\N	\N	\N
141	b2fbde0d-ef66-4c2a-880c-1ad755404e76	1	1	Modules\\Payments\\Domain\\Entities\\Bill	2	cae81774-4a91-421e-a422-5fd75b524a69	update	{"company_id":1,"branch_id":1,"order_id":19,"bill_number":"ORD-001-20261019-0002-1","type":"single","subtotal":2500,"tax_amount":399,"discount_amount":0,"tip_amount":0,"total":2500,"paid_amount":0,"remaining_amount":2500,"status":"open","guest_count":1,"uuid":"cae81774-4a91-421e-a422-5fd75b524a69","version":2,"sync_status":"pending","updated_at":"2026-10-19 18:12:04","created_at":"2026-10-19 18:12:04","id":2,"idempotency_key":"1d059462-6a69-4862-a699-7d30e2fb13e6","payload_hash":"7c49ebf771a890f9835c05a2bd773b1e6a1de1618b4c068015da42801f1e05ee"}	2	0	pending	\N	\N	\N	2026-10-19 18:12:04	2026-10-19 18:12:04	\N	\N	\N	\N	\N
142	b67fc27e-c9c1-4a31-89ac-832d6f16b09f	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	14	4ce6978a-4c19-4f52-bce0-d2e45b92d0c1	update	{"id":14,"uuid":"4ce6978a-4c19-4f52-bce0-d2e45b92d0c1","company_id":1,"branch_id":1,"area_code":"BAR","area_name_translations":"{\\"es\\": \\"Barra\\", \\"zh\\": \\"\\u5427\\u53f0\\"}","table_number":"B-03","capacity":2,"status":"available","current_order_id":null,"created_at":"2026-10-12 00:16:46","updated_at":"2026-10-19 18:29:34","deleted_at":null,"sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"zone_id":null,"position_x":0,"position_y":0,"rotation":0,"shape":"square","width":null,"height":null}	3	0	pending	\N	\N	\N	2026-10-19 18:29:34	2026-10-19 18:29:34	\N	\N	\N	\N	\N
143	a5ea13df-30ed-4398-8202-abe6732a09e4	1	1	Modules\\Orders\\Domain\\Entities\\Order	20	a90dc353-c147-46d2-abe7-3335439d33df	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0003","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"a90dc353-c147-46d2-abe7-3335439d33df","version":1,"sync_status":"pending","updated_at":"2026-10-19 18:40:19","created_at":"2026-10-19 18:40:19","id":20}	1	0	pending	\N	\N	\N	2026-10-19 18:40:19	2026-10-19 18:40:19	\N	\N	\N	\N	\N
144	9f0fc202-f81c-4da8-b045-4c55f1a52a6e	1	1	Modules\\Orders\\Domain\\Entities\\Order	21	d4e05f80-ad67-4784-8b88-fcbaab3c5675	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0004","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"d4e05f80-ad67-4784-8b88-fcbaab3c5675","version":1,"sync_status":"pending","updated_at":"2026-10-19 18:40:20","created_at":"2026-10-19 18:40:20","id":21}	1	0	pending	\N	\N	\N	2026-10-19 18:40:20	2026-10-19 18:40:20	\N	\N	\N	\N	\N
145	aa6b5025-4a1f-44b1-a656-9983609fbbec	1	1	Modules\\Orders\\Domain\\Entities\\Order	22	d1fc6d28-f7c3-44d4-8bed-120740597fd8	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0005","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"d1fc6d28-f7c3-44d4-8bed-120740597fd8","version":1,"sync_status":"pending","updated_at":"2026-10-19 18:40:20","created_at":"2026-10-19 18:40:20","id":22}	1	0	pending	\N	\N	\N	2026-10-19 18:40:20	2026-10-19 18:40:20	\N	\N	\N	\N	\N
146	b9193e17-e474-4b64-a52c-ca6135742975	1	1	Modules\\Payments\\Domain\\Entities\\Bill	3	46b4085a-7fab-458c-8e18-52adaf7023de	create	{"company_id":1,"branch_id":1,"order_id":2,"bill_number":"ORD-001-20261016-0002-1","type":"single","subtotal":3500,"tax_amount":559,"discount_amount":0,"tip_amount":0,"total":3500,"paid_amount":0,"remaining_amount":3500,"status":"open","guest_count":1,"uuid":"46b4085a-7fab-458c-8e18-52adaf7023de","version":1,"sync_status":"pending","updated_at":"2026-10-19 18:41:05","created_at":"2026-10-19 18:41:05","id":3}	1	0	pending	\N	\N	\N	2026-10-19 18:41:05	2026-10-19 18:41:05	\N	\N	\N	\N	\N
147	54cfa8a8-3eb0-4415-b961-9dfb43b6e1b8	1	1	Modules\\Payments\\Domain\\Entities\\Bill	3	46b4085a-7fab-458c-8e18-52adaf7023de	update	{"company_id":1,"branch_id":1,"order_id":2,"bill_number":"ORD-001-20261016-0002-1","type":"single","subtotal":3500,"tax_amount":559,"discount_amount":0,"tip_amount":0,"total":3500,"paid_amount":0,"remaining_amount":3500,"status":"open","guest_count":1,"uuid":"46b4085a-7fab-458c-8e18-52adaf7023de","version":2,"sync_status":"pending","updated_at":"2026-10-19 18:41:05","created_at":"2026-10-19 18:41:05","id":3,"idempotency_key":"a9a9333a-9c51-4f0f-9083-8072683653aa","payload_hash":"d9901440427351834ac4c8981014560f36433288f1efa84a977d00e9a4db29e8"}	2	0	pending	\N	\N	\N	2026-10-19 18:41:05	2026-10-19 18:41:05	\N	\N	\N	\N	\N
148	f20badbf-ddc7-4f19-aaa2-18a0f1cff822	1	1	Modules\\Payments\\Domain\\Entities\\Bill	4	736047ba-c23a-4b37-bec2-676d5192f06f	create	{"company_id":1,"branch_id":1,"order_id":1,"bill_number":"ORD-001-20261016-0001-1","type":"single","subtotal":8900,"tax_amount":1421,"discount_amount":0,"tip_amount":0,"total":8900,"paid_amount":0,"remaining_amount":8900,"status":"open","guest_count":1,"uuid":"736047ba-c23a-4b37-bec2-676d5192f06f","version":1,"sync_status":"pending","updated_at":"2026-10-19 19:11:49","created_at":"2026-10-19 19:11:49","id":4}	1	0	pending	\N	\N	\N	2026-10-19 19:11:49	2026-10-19 19:11:49	\N	\N	\N	\N	\N
149	9e1e19d5-b2ec-4b90-946d-b06e23c17754	1	1	Modules\\Payments\\Domain\\Entities\\Bill	4	736047ba-c23a-4b37-bec2-676d5192f06f	update	{"company_id":1,"branch_id":1,"order_id":1,"bill_number":"ORD-001-20261016-0001-1","type":"single","subtotal":8900,"tax_amount":1421,"discount_amount":0,"tip_amount":0,"total":8900,"paid_amount":0,"remaining_amount":8900,"status":"open","guest_count":1,"uuid":"736047ba-c23a-4b37-bec2-676d5192f06f","version":2,"sync_status":"pending","updated_at":"2026-10-19 19:11:49","created_at":"2026-10-19 19:11:49","id":4,"idempotency_key":"7fb778f9-f48c-4138-a95b-ad3bb6782cdf","payload_hash":"7386031797d5096d22397144aca77c45a510d865f503719efad38b62430aec24"}	2	0	pending	\N	\N	\N	2026-10-19 19:11:49	2026-10-19 19:11:49	\N	\N	\N	\N	\N
150	f27083bc-7038-4d54-b242-fcddcba94dc0	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	12	f8c4272e-f51c-485f-9c1f-2fc14dd7744d	update	{"id":12,"uuid":"f8c4272e-f51c-485f-9c1f-2fc14dd7744d","company_id":1,"branch_id":1,"area_code":"BAR","area_name_translations":"{\\"es\\": \\"Barra\\", \\"zh\\": \\"\\u5427\\u53f0\\"}","table_number":"B-01","capacity":2,"status":"available","current_order_id":null,"created_at":"2026-10-12 00:16:46","updated_at":"2026-10-19 19:37:38","deleted_at":null,"sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"zone_id":null,"position_x":0,"position_y":0,"rotation":0,"shape":"square","width":null,"height":null}	3	0	pending	\N	\N	\N	2026-10-19 19:37:38	2026-10-19 19:37:38	\N	\N	\N	\N	\N
151	92b5d2b2-533b-4c5c-8762-69561f3ab247	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	13	36c74c84-bb86-49a9-a2f1-fe8ab7d736b0	update	{"id":13,"uuid":"36c74c84-bb86-49a9-a2f1-fe8ab7d736b0","company_id":1,"branch_id":1,"area_code":"BAR","area_name_translations":"{\\"es\\": \\"Barra\\", \\"zh\\": \\"\\u5427\\u53f0\\"}","table_number":"B-02","capacity":2,"status":"available","current_order_id":null,"created_at":"2026-10-12 00:16:46","updated_at":"2026-10-19 19:47:23","deleted_at":null,"sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"zone_id":null,"position_x":0,"position_y":0,"rotation":0,"shape":"square","width":null,"height":null}	3	0	pending	\N	\N	\N	2026-10-19 19:47:23	2026-10-19 19:47:23	\N	\N	\N	\N	\N
152	8b52fc6b-8f00-4937-807a-0840667a8026	1	1	Modules\\Payments\\Domain\\Entities\\Bill	5	ba52ffaa-7c10-42ab-8274-088f540d2b5d	create	{"company_id":1,"branch_id":1,"order_id":18,"bill_number":"ORD-001-20261019-0001-1","type":"single","subtotal":35900,"tax_amount":5732,"discount_amount":0,"tip_amount":0,"total":35900,"paid_amount":0,"remaining_amount":35900,"status":"open","guest_count":1,"uuid":"ba52ffaa-7c10-42ab-8274-088f540d2b5d","version":1,"sync_status":"pending","updated_at":"2026-10-19 19:49:53","created_at":"2026-10-19 19:49:53","id":5}	1	0	pending	\N	\N	\N	2026-10-19 19:49:53	2026-10-19 19:49:53	\N	\N	\N	\N	\N
153	8378bb5a-03e3-4319-a8e2-ce1edb6d902e	1	1	Modules\\Payments\\Domain\\Entities\\Bill	5	ba52ffaa-7c10-42ab-8274-088f540d2b5d	update	{"company_id":1,"branch_id":1,"order_id":18,"bill_number":"ORD-001-20261019-0001-1","type":"single","subtotal":35900,"tax_amount":5732,"discount_amount":0,"tip_amount":0,"total":35900,"paid_amount":0,"remaining_amount":35900,"status":"open","guest_count":1,"uuid":"ba52ffaa-7c10-42ab-8274-088f540d2b5d","version":2,"sync_status":"pending","updated_at":"2026-10-19 19:49:53","created_at":"2026-10-19 19:49:53","id":5,"idempotency_key":"7e369704-0dc8-491a-a841-99ff59676593","payload_hash":"97a6ae27a370d59ef9cb4bdc454baecc2f895a18dc8bb807d5e8a2973b90195e"}	2	0	pending	\N	\N	\N	2026-10-19 19:49:53	2026-10-19 19:49:53	\N	\N	\N	\N	\N
154	1c749164-fba7-4a15-bde3-f4e8213cb2d1	1	1	Modules\\Payments\\Domain\\Entities\\Payment	1	f70b2e8b-851d-429b-a0d9-ad7c6a11d589	create	{"company_id":1,"branch_id":1,"order_id":2,"bill_id":3,"cash_session_id":1,"payment_method_id":1,"user_id":1,"payment_number":"PAY-SCL-001-20261019-C9188A","method_code":"CASH","amount":3500,"tip_amount":0,"total_amount":3500,"reference_code":null,"status":"completed","idempotency_key":"bf516d54-15e4-44ce-9d04-5c745fb386b7","payload_hash":"35e2d6414d2194c32fbd06128eec10fdd5f97cc120de10622707db4dc7b1c4cc","notes":null,"paid_at":"2026-10-19 19:59:08","uuid":"f70b2e8b-851d-429b-a0d9-ad7c6a11d589","version":1,"sync_status":"pending","updated_at":"2026-10-19 19:59:08","created_at":"2026-10-19 19:59:08","id":1}	1	0	pending	\N	\N	\N	2026-10-19 19:59:08	2026-10-19 19:59:08	\N	\N	\N	\N	\N
155	543f91c4-5097-4aa9-9e9e-9e41b0cedd6e	1	1	Modules\\Payments\\Domain\\Entities\\Bill	3	46b4085a-7fab-458c-8e18-52adaf7023de	update	{"id":3,"uuid":"46b4085a-7fab-458c-8e18-52adaf7023de","company_id":1,"branch_id":1,"order_id":2,"bill_number":"ORD-001-20261016-0002-1","type":"single","subtotal":3500,"tax_amount":559,"discount_amount":0,"tip_amount":0,"total":3500,"paid_amount":3500,"remaining_amount":0,"status":"paid","guest_count":1,"item_ids":null,"created_at":"2026-10-19 18:41:05","updated_at":"2026-10-19 19:59:08","deleted_at":null,"sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"idempotency_key":"a9a9333a-9c51-4f0f-9083-8072683653aa","payload_hash":"d9901440427351834ac4c8981014560f36433288f1efa84a977d00e9a4db29e8"}	3	0	pending	\N	\N	\N	2026-10-19 19:59:08	2026-10-19 19:59:08	\N	\N	\N	\N	\N
156	568619ce-e970-443e-9b68-a335746a9fba	1	1	Modules\\Orders\\Domain\\Entities\\Order	2	d975b860-a5e5-46ce-be54-793a8a592152	update	{"id":2,"uuid":"d975b860-a5e5-46ce-be54-793a8a592152","company_id":1,"branch_id":1,"order_number":"ORD-001-20261016-0002","type":"takeout","status":"paid","table_id":null,"waiter_id":1,"cashier_id":1,"subtotal":3500,"tax_amount":559,"discount_amount":0,"total":3500,"notes":null,"confirmed_at":"2026-10-16 15:20:13","served_at":null,"paid_at":"2026-10-19 19:59:08","closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-16 15:20:12","updated_at":"2026-10-19 19:59:08","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":4,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":3500,"net_amount":2941,"tip_amount":0,"amount_due":3500}	4	0	pending	\N	\N	\N	2026-10-19 19:59:08	2026-10-19 19:59:08	\N	\N	\N	\N	\N
157	349af295-2ed0-422a-a2e4-4698f096780c	1	1	Modules\\Orders\\Domain\\Entities\\Order	23	3d37c1e5-79bb-481a-8dbc-1540c0ec805b	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0006","type":"takeout","fulfillment_channel":"pickup","status":"draft","table_id":null,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"3d37c1e5-79bb-481a-8dbc-1540c0ec805b","version":1,"sync_status":"pending","updated_at":"2026-10-19 20:45:31","created_at":"2026-10-19 20:45:31","id":23}	1	0	pending	\N	\N	\N	2026-10-19 20:45:31	2026-10-19 20:45:31	\N	\N	\N	\N	\N
158	43b52606-d8ee-4376-a652-9f0007093c71	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	34	7ebd55c0-dd9d-412b-9da9-2928a05ba6dd	create	{"product_id":5,"company_id":1,"order_id":23,"menu_item_id":null,"name_snapshot":"Caldillo de Congrio","unit_price_snapshot":8900,"quantity":1,"notes":null,"subtotal":8900,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"7ebd55c0-dd9d-412b-9da9-2928a05ba6dd","version":1,"sync_status":"pending","updated_at":"2026-10-19 20:45:32","created_at":"2026-10-19 20:45:32","id":34}	1	0	pending	\N	\N	\N	2026-10-19 20:45:32	2026-10-19 20:45:32	\N	\N	\N	\N	\N
159	6a27dd4b-3182-46a4-82c5-34e9341d6dc5	1	1	Modules\\Orders\\Domain\\Entities\\Order	23	3d37c1e5-79bb-481a-8dbc-1540c0ec805b	update	{"id":23,"uuid":"3d37c1e5-79bb-481a-8dbc-1540c0ec805b","company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0006","type":"takeout","status":"draft","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":8900,"tax_amount":1421,"discount_amount":0,"total":8900,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19 20:45:31","updated_at":"2026-10-19 20:45:32","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":8900,"net_amount":7479,"tip_amount":0,"amount_due":8900}	2	0	pending	\N	\N	\N	2026-10-19 20:45:32	2026-10-19 20:45:32	\N	\N	\N	\N	\N
160	a44289a7-a45f-4ba2-a905-dcb2643e02cb	1	1	Modules\\Orders\\Domain\\Entities\\Order	23	3d37c1e5-79bb-481a-8dbc-1540c0ec805b	update	{"id":23,"uuid":"3d37c1e5-79bb-481a-8dbc-1540c0ec805b","company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0006","type":"takeout","status":"confirmed","table_id":null,"waiter_id":1,"cashier_id":null,"subtotal":8900,"tax_amount":1421,"discount_amount":0,"total":8900,"notes":null,"confirmed_at":"2026-10-19 20:45:32","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19 20:45:31","updated_at":"2026-10-19 20:45:32","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":8900,"net_amount":7479,"tip_amount":0,"amount_due":8900}	3	0	pending	\N	\N	\N	2026-10-19 20:45:32	2026-10-19 20:45:32	\N	\N	\N	\N	\N
161	22ae732e-7fd0-46a7-842b-c59f125b58ec	1	1	Modules\\Payments\\Domain\\Entities\\Bill	6	66295a36-1d41-4af2-a3fa-3a3b2c1f6234	create	{"company_id":1,"branch_id":1,"order_id":23,"bill_number":"ORD-001-20261019-0006-1","type":"single","subtotal":8900,"tax_amount":1421,"discount_amount":0,"tip_amount":0,"total":8900,"paid_amount":0,"remaining_amount":8900,"status":"open","guest_count":1,"uuid":"66295a36-1d41-4af2-a3fa-3a3b2c1f6234","version":1,"sync_status":"pending","updated_at":"2026-10-19 20:46:16","created_at":"2026-10-19 20:46:16","id":6}	1	0	pending	\N	\N	\N	2026-10-19 20:46:16	2026-10-19 20:46:16	\N	\N	\N	\N	\N
162	d8ae4da6-c921-4db9-aa27-567e0d5e763b	1	1	Modules\\Payments\\Domain\\Entities\\Bill	6	66295a36-1d41-4af2-a3fa-3a3b2c1f6234	update	{"company_id":1,"branch_id":1,"order_id":23,"bill_number":"ORD-001-20261019-0006-1","type":"single","subtotal":8900,"tax_amount":1421,"discount_amount":0,"tip_amount":0,"total":8900,"paid_amount":0,"remaining_amount":8900,"status":"open","guest_count":1,"uuid":"66295a36-1d41-4af2-a3fa-3a3b2c1f6234","version":2,"sync_status":"pending","updated_at":"2026-10-19 20:46:16","created_at":"2026-10-19 20:46:16","id":6,"idempotency_key":"51fa7bef-e771-4e04-8dbb-f3b0ef981b06","payload_hash":"9aedf7555326413d2c363d19e4911ff5507afdbb8fa8795dfeae8f2db7c4b33d"}	2	0	pending	\N	\N	\N	2026-10-19 20:46:16	2026-10-19 20:46:16	\N	\N	\N	\N	\N
163	f11d65ad-9c10-4c70-bca4-809dc9dab187	1	1	Modules\\Payments\\Domain\\Entities\\Payment	2	d9e2d64e-5761-4ada-a32b-a991d87bea4e	create	{"company_id":1,"branch_id":1,"order_id":23,"bill_id":6,"cash_session_id":1,"payment_method_id":1,"user_id":1,"payment_number":"PAY-SCL-001-20261019-702D95","method_code":"CASH","amount":1000,"tip_amount":0,"total_amount":1000,"reference_code":null,"status":"completed","idempotency_key":"c6157b5b-8604-44bd-a25d-48c37d7bbf43","payload_hash":"50fdb109903e6390d929809aa30945a7730deac27d166c4788030ef47413060b","notes":null,"paid_at":"2026-10-19 20:46:47","uuid":"d9e2d64e-5761-4ada-a32b-a991d87bea4e","version":1,"sync_status":"pending","updated_at":"2026-10-19 20:46:47","created_at":"2026-10-19 20:46:47","id":2}	1	0	pending	\N	\N	\N	2026-10-19 20:46:47	2026-10-19 20:46:47	\N	\N	\N	\N	\N
164	2c245e05-707e-4790-ab71-f80a1f41f75b	1	1	Modules\\Payments\\Domain\\Entities\\Bill	6	66295a36-1d41-4af2-a3fa-3a3b2c1f6234	update	{"id":6,"uuid":"66295a36-1d41-4af2-a3fa-3a3b2c1f6234","company_id":1,"branch_id":1,"order_id":23,"bill_number":"ORD-001-20261019-0006-1","type":"single","subtotal":8900,"tax_amount":1421,"discount_amount":0,"tip_amount":0,"total":8900,"paid_amount":1000,"remaining_amount":7900,"status":"partial","guest_count":1,"item_ids":null,"created_at":"2026-10-19 20:46:16","updated_at":"2026-10-19 20:46:47","deleted_at":null,"sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"idempotency_key":"51fa7bef-e771-4e04-8dbb-f3b0ef981b06","payload_hash":"9aedf7555326413d2c363d19e4911ff5507afdbb8fa8795dfeae8f2db7c4b33d"}	3	0	pending	\N	\N	\N	2026-10-19 20:46:47	2026-10-19 20:46:47	\N	\N	\N	\N	\N
165	4275a7c4-a437-47e2-8fb4-6042c7e5270f	1	1	Modules\\Payments\\Domain\\Entities\\Payment	3	664ef440-e013-40ef-9e89-faf78655487c	create	{"company_id":1,"branch_id":1,"order_id":23,"bill_id":6,"cash_session_id":1,"payment_method_id":1,"user_id":1,"payment_number":"PAY-SCL-001-20261019-757636","method_code":"CASH","amount":7900,"tip_amount":0,"total_amount":7900,"reference_code":null,"status":"completed","idempotency_key":"dfd0ec82-79f4-49f0-95e6-d0199609aeec","payload_hash":"28dc4c5adabeb3ec09ce48261073e7b362524c028efcd54151474db6f803052d","notes":null,"paid_at":"2026-10-19 20:46:47","uuid":"664ef440-e013-40ef-9e89-faf78655487c","version":1,"sync_status":"pending","updated_at":"2026-10-19 20:46:47","created_at":"2026-10-19 20:46:47","id":3}	1	0	pending	\N	\N	\N	2026-10-19 20:46:47	2026-10-19 20:46:47	\N	\N	\N	\N	\N
166	d0f8c7f9-9da3-4a6d-9280-c9a23daa5bf4	1	1	Modules\\Payments\\Domain\\Entities\\Bill	6	66295a36-1d41-4af2-a3fa-3a3b2c1f6234	update	{"id":6,"uuid":"66295a36-1d41-4af2-a3fa-3a3b2c1f6234","company_id":1,"branch_id":1,"order_id":23,"bill_number":"ORD-001-20261019-0006-1","type":"single","subtotal":8900,"tax_amount":1421,"discount_amount":0,"tip_amount":0,"total":8900,"paid_amount":8900,"remaining_amount":0,"status":"paid","guest_count":1,"item_ids":null,"created_at":"2026-10-19 20:46:16","updated_at":"2026-10-19 20:46:47","deleted_at":null,"sync_status":"pending","version":4,"last_synced_at":null,"offline_id":null,"idempotency_key":"51fa7bef-e771-4e04-8dbb-f3b0ef981b06","payload_hash":"9aedf7555326413d2c363d19e4911ff5507afdbb8fa8795dfeae8f2db7c4b33d"}	4	0	pending	\N	\N	\N	2026-10-19 20:46:47	2026-10-19 20:46:47	\N	\N	\N	\N	\N
167	40dedce6-4ef4-4c1d-820c-3c4ce9ac4b17	1	1	Modules\\Orders\\Domain\\Entities\\Order	23	3d37c1e5-79bb-481a-8dbc-1540c0ec805b	update	{"id":23,"uuid":"3d37c1e5-79bb-481a-8dbc-1540c0ec805b","company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0006","type":"takeout","status":"paid","table_id":null,"waiter_id":1,"cashier_id":1,"subtotal":8900,"tax_amount":1421,"discount_amount":0,"total":8900,"notes":null,"confirmed_at":"2026-10-19 20:45:32","served_at":null,"paid_at":"2026-10-19 20:46:47","closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19 20:45:31","updated_at":"2026-10-19 20:46:47","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":4,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"pickup","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":8900,"net_amount":7479,"tip_amount":0,"amount_due":8900}	4	0	pending	\N	\N	\N	2026-10-19 20:46:47	2026-10-19 20:46:47	\N	\N	\N	\N	\N
168	5c48bda4-4117-4939-953d-12facd2cf541	1	1	Modules\\Orders\\Domain\\Entities\\Order	24	dba8663e-5e80-4275-8c35-d74cb983fa7c	create	{"company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0007","type":"dine_in","fulfillment_channel":"onsite","status":"draft","table_id":1,"waiter_id":1,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"notes":null,"subtotal":0,"tax_amount":0,"discount_amount":0,"total":0,"uuid":"dba8663e-5e80-4275-8c35-d74cb983fa7c","version":1,"sync_status":"pending","updated_at":"2026-10-19 20:48:31","created_at":"2026-10-19 20:48:31","id":24}	1	0	pending	\N	\N	\N	2026-10-19 20:48:31	2026-10-19 20:48:31	\N	\N	\N	\N	\N
169	75d88fae-a583-4324-a0db-6010576265df	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	35	6d95cf6c-b25f-4acc-bf2f-ec42f431a4f6	create	{"product_id":15,"company_id":1,"order_id":24,"menu_item_id":null,"name_snapshot":"Agua Mineral","unit_price_snapshot":1000,"quantity":1,"notes":null,"subtotal":1000,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"6d95cf6c-b25f-4acc-bf2f-ec42f431a4f6","version":1,"sync_status":"pending","updated_at":"2026-10-19 20:48:32","created_at":"2026-10-19 20:48:32","id":35}	1	0	pending	\N	\N	\N	2026-10-19 20:48:32	2026-10-19 20:48:32	\N	\N	\N	\N	\N
170	98711b68-88e6-41c2-b47c-f432fe40afb0	1	1	Modules\\Orders\\Domain\\Entities\\Order	24	dba8663e-5e80-4275-8c35-d74cb983fa7c	update	{"id":24,"uuid":"dba8663e-5e80-4275-8c35-d74cb983fa7c","company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0007","type":"dine_in","status":"draft","table_id":1,"waiter_id":1,"cashier_id":null,"subtotal":1000,"tax_amount":160,"discount_amount":0,"total":1000,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19 20:48:31","updated_at":"2026-10-19 20:48:32","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"onsite","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":1000,"net_amount":840,"tip_amount":0,"amount_due":1000}	2	0	pending	\N	\N	\N	2026-10-19 20:48:32	2026-10-19 20:48:32	\N	\N	\N	\N	\N
171	dd735957-2846-4255-b627-0a83fa34f427	1	\N	Modules\\Orders\\Domain\\Entities\\OrderItem	36	d4e3ab42-3ef7-47d0-961e-0d7eb9322088	create	{"product_id":12,"company_id":1,"order_id":24,"menu_item_id":null,"name_snapshot":"Coca-Cola 500ml","unit_price_snapshot":1500,"quantity":1,"notes":null,"subtotal":1500,"tax_rate_snapshot":19,"tax_name_snapshot":null,"tax_amount":0,"uuid":"d4e3ab42-3ef7-47d0-961e-0d7eb9322088","version":1,"sync_status":"pending","updated_at":"2026-10-19 20:48:32","created_at":"2026-10-19 20:48:32","id":36}	1	0	pending	\N	\N	\N	2026-10-19 20:48:32	2026-10-19 20:48:32	\N	\N	\N	\N	\N
172	88a2eb21-341e-43fd-b9c3-1a9211919cca	1	1	Modules\\Orders\\Domain\\Entities\\Order	24	dba8663e-5e80-4275-8c35-d74cb983fa7c	update	{"id":24,"uuid":"dba8663e-5e80-4275-8c35-d74cb983fa7c","company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0007","type":"dine_in","status":"draft","table_id":1,"waiter_id":1,"cashier_id":null,"subtotal":2500,"tax_amount":399,"discount_amount":0,"total":2500,"notes":null,"confirmed_at":null,"served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19 20:48:31","updated_at":"2026-10-19 20:48:32","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":3,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"onsite","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":2500,"net_amount":2101,"tip_amount":0,"amount_due":2500}	3	0	pending	\N	\N	\N	2026-10-19 20:48:32	2026-10-19 20:48:32	\N	\N	\N	\N	\N
173	5444e38d-27d8-45b2-ba4f-7298ad995713	1	1	Modules\\Orders\\Domain\\Entities\\Order	24	dba8663e-5e80-4275-8c35-d74cb983fa7c	update	{"id":24,"uuid":"dba8663e-5e80-4275-8c35-d74cb983fa7c","company_id":1,"branch_id":1,"order_number":"ORD-001-20261019-0007","type":"dine_in","status":"confirmed","table_id":1,"waiter_id":1,"cashier_id":null,"subtotal":2500,"tax_amount":399,"discount_amount":0,"total":2500,"notes":null,"confirmed_at":"2026-10-19 20:48:32","served_at":null,"paid_at":null,"closed_at":null,"cancelled_at":null,"cancellation_reason":null,"created_at":"2026-10-19 20:48:31","updated_at":"2026-10-19 20:48:32","assigned_cook_id":null,"priority":"normal","sync_status":"pending","version":4,"last_synced_at":null,"offline_id":null,"customer_name":null,"customer_phone":null,"pickup_at":null,"delivery_address":null,"delivery_notes":null,"fulfillment_channel":"onsite","picked_up_at":null,"dispatched_at":null,"delivered_at":null,"subtotal_gross":2500,"net_amount":2101,"tip_amount":0,"amount_due":2500}	4	0	pending	\N	\N	\N	2026-10-19 20:48:32	2026-10-19 20:48:32	\N	\N	\N	\N	\N
174	e996d60a-f3b7-462c-b8e1-d9f5a5a3baa5	1	1	Modules\\Tables\\Domain\\Entities\\RestaurantTable	1	7185d16c-26b2-4806-91a9-3ecf6fde7abb	update	{"id":1,"uuid":"7185d16c-26b2-4806-91a9-3ecf6fde7abb","company_id":1,"branch_id":1,"area_code":"MAIN","area_name_translations":"{\\"es\\": \\"Sal\\u00f3n Principal\\", \\"zh\\": \\"\\u4e3b\\u5385\\"}","table_number":"M-01","capacity":4,"status":"occupied","current_order_id":24,"created_at":"2026-10-12 00:16:46","updated_at":"2026-10-19 20:48:32","deleted_at":null,"sync_status":"pending","version":2,"last_synced_at":null,"offline_id":null,"zone_id":null,"position_x":0,"position_y":0,"rotation":0,"shape":"square","width":null,"height":null}	2	0	pending	\N	\N	\N	2026-10-19 20:48:32	2026-10-19 20:48:32	\N	\N	\N	\N	\N
\.


--
-- Data for Name: taxes; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.taxes (id, uuid, company_id, name, code, type, rate, is_default, is_active, description, sort_order, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: terminals; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.terminals (id, uuid, company_id, branch_id, code, name, locale, mac_address, is_kds, is_pos, is_active, created_at, updated_at, deleted_at) FROM stdin;
1	3ba171e2-a1e3-4347-a1fa-84c9713429eb	1	1	POS-001	Caja Principal Centro	es-CL	\N	f	t	t	2026-10-12 00:16:42	2026-10-12 00:16:42	\N
2	a80ef045-0fbe-4706-8840-1fb7edc8190d	1	1	KDS-001	Cocina Centro	zh-CN	\N	t	f	t	2026-10-12 00:16:42	2026-10-12 00:16:42	\N
3	c91ad32c-6ed7-4da0-836d-04ba8dba34c8	1	2	POS-002	Caja Principal Providencia	es-CL	\N	f	t	t	2026-10-12 00:16:42	2026-10-12 00:16:42	\N
4	af0d05df-8b35-4c3d-b6d7-cc6a10b71e4c	1	2	KDS-002	Cocina Providencia	zh-CN	\N	t	f	t	2026-10-12 00:16:42	2026-10-12 00:16:42	\N
\.


--
-- Data for Name: tip_payouts; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.tip_payouts (id, uuid, company_id, branch_id, cash_session_id, processed_by, waiter_id, amount, payment_method, policy_type, notes, is_voided, voided_at, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: tip_policies; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.tip_policies (id, uuid, company_id, branch_id, policy_type, card_tip_handling, pool_split_method, waiter_percentage, pool_percentage, is_active, effective_from, effective_to, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, uuid, company_id, branch_id, pos_pin_hash, role, locale, is_active) FROM stdin;
1	Admin Principal	admin@demo.cl	\N	$2y$12$bRyMm5bh5oG9vG1UNHsg2ODHeN66PRRk35qqIWq29U5NZtEz9lOc2	\N	2026-10-12 00:16:43	2026-10-12 00:16:43	51a7cf85-e65c-4f21-9462-cbc0ee15044c	1	1	$2y$12$cGlUJeCKhKq7oNtzKexQmuBBQY69gkvilNjIixo6h7MA2NN6D0ZOi	admin	es-CL	t
2	Encargado Centro	manager.centro@demo.cl	\N	$2y$12$gyQDwx22sN3z.OxnYxDa4uHiSUQat/F4PKRT/mnmN8OxOYzOkaZ7S	\N	2026-10-12 00:16:43	2026-10-12 00:16:43	2905d0f7-46a6-4f9e-b824-cbd98893eec5	1	1	$2y$12$v84qs39yxzgaD4YDLFRr3eq7A4K5FY8TV4Wr9sUuPPdq./3NIGimC	manager	es-CL	t
3	Cajero Centro	cajero.centro@demo.cl	\N	$2y$12$Ak3nDzlbUYsIPSaL7RJojOIFWx8LmGyxdzQn4oq3AcU9r41WDhg3u	\N	2026-10-12 00:16:44	2026-10-12 00:16:44	0c637ad6-6d07-4a0f-bef9-81d66fc75eea	1	1	$2y$12$6ZOZleztmVyf2kd8cgZk8epvC0sON6EQfqBo6ZEyfTgEXB/Jl6q..	cashier	es-CL	t
4	Garzón Centro	garzon.centro@demo.cl	\N	$2y$12$bk5qIyD5BV7s2JuLT2/VMel/7os8tmZDW/EfwgsltCufnS53DmsIO	\N	2026-10-12 00:16:44	2026-10-12 00:16:44	d43007b0-bee2-4337-99b3-6a25e0d38f39	1	1	$2y$12$I7TU7QIK082vpvnMikTawe/KmGmNSmD//zswRA9G7cwlQErEAFqiW	waiter	es-CL	t
5	Cocinero Centro	cocina.centro@demo.cl	\N	$2y$12$ntYuNFQgcFYNhQCehWasIOpGfrXq96w2GQzRH25PFXnoo0lZbGAaO	\N	2026-10-12 00:16:45	2026-10-12 00:16:45	8796de09-902c-4aad-949b-928c009f6ba6	1	1	$2y$12$D/4XnFS4yH3fGTPtNox/zORWYDOoOFQ5KO4.hUg/Dl8LMBRuZXX5u	kitchen	es-CL	t
6	Encargado Providencia	manager.prov@demo.cl	\N	$2y$12$Nwn/rjff/YysfeYRmgI.W.mOhC9EWT7g1cIlaQ0cas18zs2Myb7WG	\N	2026-10-12 00:16:45	2026-10-12 00:16:45	2a0ed0e3-5010-47bc-9bfd-4c98840d96b0	1	2	$2y$12$0zBg0eJg2b065BbBNrTiGuLu/MITpc/kKvX9JaE5Kb1/aDe6TCoUm	manager	es-CL	t
\.


--
-- Name: accounts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.accounts_id_seq', 170, true);


--
-- Name: audit_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.audit_logs_id_seq', 1, true);


--
-- Name: bills_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.bills_id_seq', 1, false);


--
-- Name: branches_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.branches_id_seq', 2, true);


--
-- Name: cash_counts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.cash_counts_id_seq', 1, false);


--
-- Name: cash_movements_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.cash_movements_id_seq', 1, false);


--
-- Name: cash_registers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.cash_registers_id_seq', 1, false);


--
-- Name: cash_sessions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.cash_sessions_id_seq', 1, false);


--
-- Name: categories_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.categories_id_seq', 7, true);


--
-- Name: companies_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.companies_id_seq', 1, true);


--
-- Name: company_capabilities_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.company_capabilities_id_seq', 8, true);


--
-- Name: default_notes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.default_notes_id_seq', 1, false);


--
-- Name: dining_zones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.dining_zones_id_seq', 1, true);


--
-- Name: dte_certificates_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.dte_certificates_id_seq', 1, false);


--
-- Name: dte_documents_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.dte_documents_id_seq', 1, false);


--
-- Name: dte_folio_ranges_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.dte_folio_ranges_id_seq', 1, false);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: idempotency_keys_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.idempotency_keys_id_seq', 246, true);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: journal_entries_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.journal_entries_id_seq', 3, true);


--
-- Name: ledger_entries_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.ledger_entries_id_seq', 9, true);


--
-- Name: menu_activations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.menu_activations_id_seq', 1, false);


--
-- Name: menu_item_products_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.menu_item_products_id_seq', 9, true);


--
-- Name: menu_item_replacement_rules_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.menu_item_replacement_rules_id_seq', 2, true);


--
-- Name: menu_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.menu_items_id_seq', 3, true);


--
-- Name: menu_products_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.menu_products_id_seq', 1, false);


--
-- Name: menus_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.menus_id_seq', 2, true);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.migrations_id_seq', 106, true);


--
-- Name: order_item_modifiers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.order_item_modifiers_id_seq', 1, false);


--
-- Name: order_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.order_items_id_seq', 1, false);


--
-- Name: orders_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.orders_id_seq', 1, false);


--
-- Name: payment_methods_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.payment_methods_id_seq', 4, true);


--
-- Name: payments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.payments_id_seq', 1, false);


--
-- Name: permissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.permissions_id_seq', 1, false);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 1, false);


--
-- Name: price_lists_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.price_lists_id_seq', 2, true);


--
-- Name: print_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.print_jobs_id_seq', 1, false);


--
-- Name: printer_station_mappings_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.printer_station_mappings_id_seq', 1, false);


--
-- Name: printers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.printers_id_seq', 1, false);


--
-- Name: product_prices_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.product_prices_id_seq', 1, false);


--
-- Name: product_recipes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.product_recipes_id_seq', 1, false);


--
-- Name: products_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.products_id_seq', 22, true);


--
-- Name: raw_ingredient_movements_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.raw_ingredient_movements_id_seq', 1, false);


--
-- Name: raw_ingredient_purchases_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.raw_ingredient_purchases_id_seq', 1, false);


--
-- Name: raw_ingredients_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.raw_ingredients_id_seq', 1, false);


--
-- Name: recipe_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.recipe_items_id_seq', 1, false);


--
-- Name: refunds_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.refunds_id_seq', 1, false);


--
-- Name: restaurant_tables_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.restaurant_tables_id_seq', 28, true);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.roles_id_seq', 1, false);


--
-- Name: sync_log_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.sync_log_id_seq', 1, false);


--
-- Name: sync_queue_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.sync_queue_id_seq', 174, true);


--
-- Name: taxes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.taxes_id_seq', 1, false);


--
-- Name: terminals_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.terminals_id_seq', 4, true);


--
-- Name: tip_payouts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.tip_payouts_id_seq', 1, false);


--
-- Name: tip_policies_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.tip_policies_id_seq', 1, false);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.users_id_seq', 6, true);


--
-- Name: accounts accounts_company_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.accounts
    ADD CONSTRAINT accounts_company_id_code_unique UNIQUE (company_id, code);


--
-- Name: accounts accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.accounts
    ADD CONSTRAINT accounts_pkey PRIMARY KEY (id);


--
-- Name: accounts accounts_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.accounts
    ADD CONSTRAINT accounts_uuid_unique UNIQUE (uuid);


--
-- Name: audit_logs audit_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_pkey PRIMARY KEY (id);


--
-- Name: audit_logs audit_logs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_uuid_unique UNIQUE (uuid);


--
-- Name: bills bills_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.bills
    ADD CONSTRAINT bills_pkey PRIMARY KEY (id);


--
-- Name: bills bills_tenant_branch_idempotency_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.bills
    ADD CONSTRAINT bills_tenant_branch_idempotency_unique UNIQUE (company_id, branch_id, idempotency_key);


--
-- Name: bills bills_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.bills
    ADD CONSTRAINT bills_uuid_unique UNIQUE (uuid);


--
-- Name: branches branches_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_pkey PRIMARY KEY (id);


--
-- Name: branches branches_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_uuid_unique UNIQUE (uuid);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: cash_counts cash_counts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_counts
    ADD CONSTRAINT cash_counts_pkey PRIMARY KEY (id);


--
-- Name: cash_counts cash_counts_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_counts
    ADD CONSTRAINT cash_counts_uuid_unique UNIQUE (uuid);


--
-- Name: cash_movements cash_movements_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_movements
    ADD CONSTRAINT cash_movements_pkey PRIMARY KEY (id);


--
-- Name: cash_movements cash_movements_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_movements
    ADD CONSTRAINT cash_movements_uuid_unique UNIQUE (uuid);


--
-- Name: cash_registers cash_registers_company_id_branch_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_registers
    ADD CONSTRAINT cash_registers_company_id_branch_id_code_unique UNIQUE (company_id, branch_id, code);


--
-- Name: cash_registers cash_registers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_registers
    ADD CONSTRAINT cash_registers_pkey PRIMARY KEY (id);


--
-- Name: cash_registers cash_registers_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_registers
    ADD CONSTRAINT cash_registers_uuid_unique UNIQUE (uuid);


--
-- Name: cash_sessions cash_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_sessions
    ADD CONSTRAINT cash_sessions_pkey PRIMARY KEY (id);


--
-- Name: cash_sessions cash_sessions_session_number_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_sessions
    ADD CONSTRAINT cash_sessions_session_number_unique UNIQUE (session_number);


--
-- Name: cash_sessions cash_sessions_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_sessions
    ADD CONSTRAINT cash_sessions_uuid_unique UNIQUE (uuid);


--
-- Name: categories categories_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_pkey PRIMARY KEY (id);


--
-- Name: categories categories_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_uuid_unique UNIQUE (uuid);


--
-- Name: companies companies_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_pkey PRIMARY KEY (id);


--
-- Name: companies companies_tax_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_tax_id_unique UNIQUE (tax_id);


--
-- Name: companies companies_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_uuid_unique UNIQUE (uuid);


--
-- Name: company_capabilities company_capabilities_company_id_capability_key_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.company_capabilities
    ADD CONSTRAINT company_capabilities_company_id_capability_key_unique UNIQUE (company_id, capability_key);


--
-- Name: company_capabilities company_capabilities_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.company_capabilities
    ADD CONSTRAINT company_capabilities_pkey PRIMARY KEY (id);


--
-- Name: default_notes default_notes_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.default_notes
    ADD CONSTRAINT default_notes_pkey PRIMARY KEY (id);


--
-- Name: default_notes default_notes_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.default_notes
    ADD CONSTRAINT default_notes_uuid_unique UNIQUE (uuid);


--
-- Name: dining_zones dining_zones_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dining_zones
    ADD CONSTRAINT dining_zones_pkey PRIMARY KEY (id);


--
-- Name: dining_zones dining_zones_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dining_zones
    ADD CONSTRAINT dining_zones_uuid_unique UNIQUE (uuid);


--
-- Name: dte_certificates dte_certificates_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dte_certificates
    ADD CONSTRAINT dte_certificates_pkey PRIMARY KEY (id);


--
-- Name: dte_certificates dte_certificates_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dte_certificates
    ADD CONSTRAINT dte_certificates_uuid_unique UNIQUE (uuid);


--
-- Name: dte_documents dte_documents_company_id_branch_id_dte_type_folio_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dte_documents
    ADD CONSTRAINT dte_documents_company_id_branch_id_dte_type_folio_unique UNIQUE (company_id, branch_id, dte_type, folio);


--
-- Name: dte_documents dte_documents_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dte_documents
    ADD CONSTRAINT dte_documents_pkey PRIMARY KEY (id);


--
-- Name: dte_documents dte_documents_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dte_documents
    ADD CONSTRAINT dte_documents_uuid_unique UNIQUE (uuid);


--
-- Name: dte_folio_ranges dte_folio_ranges_company_id_branch_id_dte_type_folio_initial_un; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dte_folio_ranges
    ADD CONSTRAINT dte_folio_ranges_company_id_branch_id_dte_type_folio_initial_un UNIQUE (company_id, branch_id, dte_type, folio_initial);


--
-- Name: dte_folio_ranges dte_folio_ranges_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dte_folio_ranges
    ADD CONSTRAINT dte_folio_ranges_pkey PRIMARY KEY (id);


--
-- Name: dte_folio_ranges dte_folio_ranges_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dte_folio_ranges
    ADD CONSTRAINT dte_folio_ranges_uuid_unique UNIQUE (uuid);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: idempotency_keys idempotency_keys_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.idempotency_keys
    ADD CONSTRAINT idempotency_keys_pkey PRIMARY KEY (id);


--
-- Name: idempotency_keys idempotency_tenant_branch_key_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.idempotency_keys
    ADD CONSTRAINT idempotency_tenant_branch_key_unique UNIQUE (company_id, branch_id, key);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: journal_entries journal_entries_company_number_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.journal_entries
    ADD CONSTRAINT journal_entries_company_number_unique UNIQUE (company_id, journal_entry_number);


--
-- Name: journal_entries journal_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.journal_entries
    ADD CONSTRAINT journal_entries_pkey PRIMARY KEY (id);


--
-- Name: journal_entries journal_entries_reference_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.journal_entries
    ADD CONSTRAINT journal_entries_reference_unique UNIQUE (reference_type, reference_id);


--
-- Name: journal_entries journal_entries_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.journal_entries
    ADD CONSTRAINT journal_entries_uuid_unique UNIQUE (uuid);


--
-- Name: ledger_entries ledger_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ledger_entries
    ADD CONSTRAINT ledger_entries_pkey PRIMARY KEY (id);


--
-- Name: ledger_entries ledger_entries_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ledger_entries
    ADD CONSTRAINT ledger_entries_uuid_unique UNIQUE (uuid);


--
-- Name: menu_activations menu_activations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_activations
    ADD CONSTRAINT menu_activations_pkey PRIMARY KEY (id);


--
-- Name: menu_activations menu_activations_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_activations
    ADD CONSTRAINT menu_activations_uuid_unique UNIQUE (uuid);


--
-- Name: menu_item_products menu_item_products_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_item_products
    ADD CONSTRAINT menu_item_products_pkey PRIMARY KEY (id);


--
-- Name: menu_item_products menu_item_products_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_item_products
    ADD CONSTRAINT menu_item_products_uuid_unique UNIQUE (uuid);


--
-- Name: menu_item_replacement_rules menu_item_replacement_rules_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_item_replacement_rules
    ADD CONSTRAINT menu_item_replacement_rules_pkey PRIMARY KEY (id);


--
-- Name: menu_item_replacement_rules menu_item_replacement_rules_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_item_replacement_rules
    ADD CONSTRAINT menu_item_replacement_rules_uuid_unique UNIQUE (uuid);


--
-- Name: menu_items menu_items_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_items
    ADD CONSTRAINT menu_items_pkey PRIMARY KEY (id);


--
-- Name: menu_items menu_items_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_items
    ADD CONSTRAINT menu_items_uuid_unique UNIQUE (uuid);


--
-- Name: menu_products menu_products_menu_id_product_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_products
    ADD CONSTRAINT menu_products_menu_id_product_id_unique UNIQUE (menu_id, product_id);


--
-- Name: menu_products menu_products_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_products
    ADD CONSTRAINT menu_products_pkey PRIMARY KEY (id);


--
-- Name: menu_products menu_products_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_products
    ADD CONSTRAINT menu_products_uuid_unique UNIQUE (uuid);


--
-- Name: menus menus_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menus
    ADD CONSTRAINT menus_pkey PRIMARY KEY (id);


--
-- Name: menus menus_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menus
    ADD CONSTRAINT menus_uuid_unique UNIQUE (uuid);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: model_has_permissions model_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_pkey PRIMARY KEY (permission_id, model_id, model_type);


--
-- Name: model_has_roles model_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_pkey PRIMARY KEY (role_id, model_id, model_type);


--
-- Name: order_item_modifiers order_item_modifiers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.order_item_modifiers
    ADD CONSTRAINT order_item_modifiers_pkey PRIMARY KEY (id);


--
-- Name: order_items order_items_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.order_items
    ADD CONSTRAINT order_items_pkey PRIMARY KEY (id);


--
-- Name: order_items order_items_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.order_items
    ADD CONSTRAINT order_items_uuid_unique UNIQUE (uuid);


--
-- Name: orders orders_order_number_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_order_number_unique UNIQUE (order_number);


--
-- Name: orders orders_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_pkey PRIMARY KEY (id);


--
-- Name: orders orders_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_uuid_unique UNIQUE (uuid);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: payment_methods payment_methods_company_id_branch_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payment_methods
    ADD CONSTRAINT payment_methods_company_id_branch_id_code_unique UNIQUE (company_id, branch_id, code);


--
-- Name: payment_methods payment_methods_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payment_methods
    ADD CONSTRAINT payment_methods_pkey PRIMARY KEY (id);


--
-- Name: payment_methods payment_methods_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payment_methods
    ADD CONSTRAINT payment_methods_uuid_unique UNIQUE (uuid);


--
-- Name: payments payments_payment_number_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_payment_number_unique UNIQUE (payment_number);


--
-- Name: payments payments_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_pkey PRIMARY KEY (id);


--
-- Name: payments payments_tenant_idempotency_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_tenant_idempotency_unique UNIQUE (company_id, branch_id, idempotency_key);


--
-- Name: payments payments_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_uuid_unique UNIQUE (uuid);


--
-- Name: permissions permissions_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: price_lists price_lists_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.price_lists
    ADD CONSTRAINT price_lists_pkey PRIMARY KEY (id);


--
-- Name: price_lists price_lists_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.price_lists
    ADD CONSTRAINT price_lists_uuid_unique UNIQUE (uuid);


--
-- Name: print_jobs print_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.print_jobs
    ADD CONSTRAINT print_jobs_pkey PRIMARY KEY (id);


--
-- Name: print_jobs print_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.print_jobs
    ADD CONSTRAINT print_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: printer_station_mappings printer_station_mappings_branch_id_category_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.printer_station_mappings
    ADD CONSTRAINT printer_station_mappings_branch_id_category_id_unique UNIQUE (branch_id, category_id);


--
-- Name: printer_station_mappings printer_station_mappings_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.printer_station_mappings
    ADD CONSTRAINT printer_station_mappings_pkey PRIMARY KEY (id);


--
-- Name: printer_station_mappings printer_station_mappings_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.printer_station_mappings
    ADD CONSTRAINT printer_station_mappings_uuid_unique UNIQUE (uuid);


--
-- Name: printers printers_company_id_branch_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.printers
    ADD CONSTRAINT printers_company_id_branch_id_name_unique UNIQUE (company_id, branch_id, name);


--
-- Name: printers printers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.printers
    ADD CONSTRAINT printers_pkey PRIMARY KEY (id);


--
-- Name: printers printers_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.printers
    ADD CONSTRAINT printers_uuid_unique UNIQUE (uuid);


--
-- Name: product_prices product_prices_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.product_prices
    ADD CONSTRAINT product_prices_pkey PRIMARY KEY (id);


--
-- Name: product_prices product_prices_product_id_price_list_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.product_prices
    ADD CONSTRAINT product_prices_product_id_price_list_id_unique UNIQUE (product_id, price_list_id);


--
-- Name: product_prices product_prices_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.product_prices
    ADD CONSTRAINT product_prices_uuid_unique UNIQUE (uuid);


--
-- Name: product_recipes product_recipes_company_id_product_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.product_recipes
    ADD CONSTRAINT product_recipes_company_id_product_id_unique UNIQUE (company_id, product_id);


--
-- Name: product_recipes product_recipes_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.product_recipes
    ADD CONSTRAINT product_recipes_pkey PRIMARY KEY (id);


--
-- Name: product_recipes product_recipes_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.product_recipes
    ADD CONSTRAINT product_recipes_uuid_unique UNIQUE (uuid);


--
-- Name: products products_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_pkey PRIMARY KEY (id);


--
-- Name: products products_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_uuid_unique UNIQUE (uuid);


--
-- Name: raw_ingredient_movements raw_ingredient_movements_idempotency_key_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.raw_ingredient_movements
    ADD CONSTRAINT raw_ingredient_movements_idempotency_key_unique UNIQUE (idempotency_key);


--
-- Name: raw_ingredient_movements raw_ingredient_movements_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.raw_ingredient_movements
    ADD CONSTRAINT raw_ingredient_movements_pkey PRIMARY KEY (id);


--
-- Name: raw_ingredient_movements raw_ingredient_movements_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.raw_ingredient_movements
    ADD CONSTRAINT raw_ingredient_movements_uuid_unique UNIQUE (uuid);


--
-- Name: raw_ingredient_purchases raw_ingredient_purchases_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.raw_ingredient_purchases
    ADD CONSTRAINT raw_ingredient_purchases_pkey PRIMARY KEY (id);


--
-- Name: raw_ingredient_purchases raw_ingredient_purchases_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.raw_ingredient_purchases
    ADD CONSTRAINT raw_ingredient_purchases_uuid_unique UNIQUE (uuid);


--
-- Name: raw_ingredients raw_ingredients_branch_id_sku_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.raw_ingredients
    ADD CONSTRAINT raw_ingredients_branch_id_sku_unique UNIQUE (branch_id, sku);


--
-- Name: raw_ingredients raw_ingredients_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.raw_ingredients
    ADD CONSTRAINT raw_ingredients_pkey PRIMARY KEY (id);


--
-- Name: raw_ingredients raw_ingredients_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.raw_ingredients
    ADD CONSTRAINT raw_ingredients_uuid_unique UNIQUE (uuid);


--
-- Name: recipe_items recipe_items_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.recipe_items
    ADD CONSTRAINT recipe_items_pkey PRIMARY KEY (id);


--
-- Name: recipe_items recipe_items_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.recipe_items
    ADD CONSTRAINT recipe_items_uuid_unique UNIQUE (uuid);


--
-- Name: refunds refunds_company_id_idempotency_key_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.refunds
    ADD CONSTRAINT refunds_company_id_idempotency_key_unique UNIQUE (company_id, idempotency_key);


--
-- Name: refunds refunds_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.refunds
    ADD CONSTRAINT refunds_pkey PRIMARY KEY (id);


--
-- Name: refunds refunds_refund_number_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.refunds
    ADD CONSTRAINT refunds_refund_number_unique UNIQUE (refund_number);


--
-- Name: refunds refunds_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.refunds
    ADD CONSTRAINT refunds_uuid_unique UNIQUE (uuid);


--
-- Name: restaurant_tables restaurant_tables_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.restaurant_tables
    ADD CONSTRAINT restaurant_tables_pkey PRIMARY KEY (id);


--
-- Name: restaurant_tables restaurant_tables_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.restaurant_tables
    ADD CONSTRAINT restaurant_tables_uuid_unique UNIQUE (uuid);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: roles roles_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: sync_log sync_log_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sync_log
    ADD CONSTRAINT sync_log_pkey PRIMARY KEY (id);


--
-- Name: sync_log sync_log_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sync_log
    ADD CONSTRAINT sync_log_uuid_unique UNIQUE (uuid);


--
-- Name: sync_queue sync_queue_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sync_queue
    ADD CONSTRAINT sync_queue_pkey PRIMARY KEY (id);


--
-- Name: sync_queue sync_queue_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sync_queue
    ADD CONSTRAINT sync_queue_uuid_unique UNIQUE (uuid);


--
-- Name: taxes taxes_company_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.taxes
    ADD CONSTRAINT taxes_company_id_code_unique UNIQUE (company_id, code);


--
-- Name: taxes taxes_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.taxes
    ADD CONSTRAINT taxes_pkey PRIMARY KEY (id);


--
-- Name: taxes taxes_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.taxes
    ADD CONSTRAINT taxes_uuid_unique UNIQUE (uuid);


--
-- Name: terminals terminals_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.terminals
    ADD CONSTRAINT terminals_pkey PRIMARY KEY (id);


--
-- Name: terminals terminals_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.terminals
    ADD CONSTRAINT terminals_uuid_unique UNIQUE (uuid);


--
-- Name: tip_payouts tip_payouts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tip_payouts
    ADD CONSTRAINT tip_payouts_pkey PRIMARY KEY (id);


--
-- Name: tip_payouts tip_payouts_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tip_payouts
    ADD CONSTRAINT tip_payouts_uuid_unique UNIQUE (uuid);


--
-- Name: tip_policies tip_policies_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tip_policies
    ADD CONSTRAINT tip_policies_pkey PRIMARY KEY (id);


--
-- Name: tip_policies tip_policies_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tip_policies
    ADD CONSTRAINT tip_policies_uuid_unique UNIQUE (uuid);


--
-- Name: tip_policies tip_policy_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tip_policies
    ADD CONSTRAINT tip_policy_unique UNIQUE (company_id, branch_id, effective_from);


--
-- Name: branches uk_branch_code; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT uk_branch_code UNIQUE (company_id, code);


--
-- Name: dining_zones uk_dining_zone_branch_code; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dining_zones
    ADD CONSTRAINT uk_dining_zone_branch_code UNIQUE (branch_id, code);


--
-- Name: menu_items uk_menu_item_product; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_items
    ADD CONSTRAINT uk_menu_item_product UNIQUE (company_id, branch_id, product_id);


--
-- Name: menu_item_products uk_menu_item_product_composition; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_item_products
    ADD CONSTRAINT uk_menu_item_product_composition UNIQUE (menu_item_id, product_id);


--
-- Name: products uk_product_sku; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT uk_product_sku UNIQUE (company_id, sku);


--
-- Name: restaurant_tables uk_table_number; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.restaurant_tables
    ADD CONSTRAINT uk_table_number UNIQUE (branch_id, table_number);


--
-- Name: terminals uk_terminal_code; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.terminals
    ADD CONSTRAINT uk_terminal_code UNIQUE (branch_id, code);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: users users_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_uuid_unique UNIQUE (uuid);


--
-- Name: accounts_branch_id_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX accounts_branch_id_type_index ON public.accounts USING btree (branch_id, type);


--
-- Name: accounts_company_id_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX accounts_company_id_type_index ON public.accounts USING btree (company_id, type);


--
-- Name: audit_logs_action_occurred_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX audit_logs_action_occurred_at_index ON public.audit_logs USING btree (action, occurred_at);


--
-- Name: audit_logs_company_id_branch_id_occurred_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX audit_logs_company_id_branch_id_occurred_at_index ON public.audit_logs USING btree (company_id, branch_id, occurred_at);


--
-- Name: audit_logs_entity_type_entity_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX audit_logs_entity_type_entity_id_index ON public.audit_logs USING btree (entity_type, entity_id);


--
-- Name: audit_logs_user_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX audit_logs_user_id_index ON public.audit_logs USING btree (user_id);


--
-- Name: bills_company_id_branch_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX bills_company_id_branch_id_index ON public.bills USING btree (company_id, branch_id);


--
-- Name: bills_sync_status_branch_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX bills_sync_status_branch_id_index ON public.bills USING btree (sync_status, branch_id);


--
-- Name: branches_company_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX branches_company_id_is_active_index ON public.branches USING btree (company_id, is_active);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: cash_counts_cash_session_id_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX cash_counts_cash_session_id_type_index ON public.cash_counts USING btree (cash_session_id, type);


--
-- Name: cash_counts_company_id_branch_id_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX cash_counts_company_id_branch_id_created_at_index ON public.cash_counts USING btree (company_id, branch_id, created_at);


--
-- Name: cash_counts_has_discrepancy_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX cash_counts_has_discrepancy_created_at_index ON public.cash_counts USING btree (has_discrepancy, created_at);


--
-- Name: cash_movements_cash_session_id_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX cash_movements_cash_session_id_type_index ON public.cash_movements USING btree (cash_session_id, type);


--
-- Name: cash_movements_company_id_branch_id_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX cash_movements_company_id_branch_id_created_at_index ON public.cash_movements USING btree (company_id, branch_id, created_at);


--
-- Name: cash_movements_user_id_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX cash_movements_user_id_created_at_index ON public.cash_movements USING btree (user_id, created_at);


--
-- Name: cash_registers_company_id_branch_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX cash_registers_company_id_branch_id_is_active_index ON public.cash_registers USING btree (company_id, branch_id, is_active);


--
-- Name: cash_sessions_branch_id_register_id_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX cash_sessions_branch_id_register_id_status_index ON public.cash_sessions USING btree (branch_id, register_id, status);


--
-- Name: cash_sessions_company_id_branch_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX cash_sessions_company_id_branch_id_index ON public.cash_sessions USING btree (company_id, branch_id);


--
-- Name: categories_company_id_branch_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX categories_company_id_branch_id_index ON public.categories USING btree (company_id, branch_id);


--
-- Name: categories_company_id_branch_id_parent_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX categories_company_id_branch_id_parent_id_index ON public.categories USING btree (company_id, branch_id, parent_id);


--
-- Name: categories_company_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX categories_company_id_is_active_index ON public.categories USING btree (company_id, is_active);


--
-- Name: categories_company_id_tax_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX categories_company_id_tax_id_index ON public.categories USING btree (company_id, tax_id);


--
-- Name: companies_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX companies_is_active_index ON public.companies USING btree (is_active);


--
-- Name: companies_tax_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX companies_tax_id_index ON public.companies USING btree (tax_id);


--
-- Name: company_capabilities_capability_key_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX company_capabilities_capability_key_index ON public.company_capabilities USING btree (capability_key);


--
-- Name: company_capabilities_company_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX company_capabilities_company_id_index ON public.company_capabilities USING btree (company_id);


--
-- Name: default_notes_company_id_branch_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX default_notes_company_id_branch_id_is_active_index ON public.default_notes USING btree (company_id, branch_id, is_active);


--
-- Name: dining_zones_name_translations_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX dining_zones_name_translations_index ON public.dining_zones USING btree (name_translations);


--
-- Name: dte_certificates_company_id_environment_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX dte_certificates_company_id_environment_is_active_index ON public.dte_certificates USING btree (company_id, environment, is_active);


--
-- Name: dte_certificates_valid_until_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX dte_certificates_valid_until_index ON public.dte_certificates USING btree (valid_until);


--
-- Name: dte_documents_company_id_branch_id_issue_date_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX dte_documents_company_id_branch_id_issue_date_index ON public.dte_documents USING btree (company_id, branch_id, issue_date);


--
-- Name: dte_documents_company_id_dte_type_sii_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX dte_documents_company_id_dte_type_sii_status_index ON public.dte_documents USING btree (company_id, dte_type, sii_status);


--
-- Name: dte_documents_order_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX dte_documents_order_id_index ON public.dte_documents USING btree (order_id);


--
-- Name: dte_documents_track_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX dte_documents_track_id_index ON public.dte_documents USING btree (track_id);


--
-- Name: dte_folio_ranges_company_id_branch_id_dte_type_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX dte_folio_ranges_company_id_branch_id_dte_type_is_active_index ON public.dte_folio_ranges USING btree (company_id, branch_id, dte_type, is_active);


--
-- Name: idempotency_keys_company_id_branch_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idempotency_keys_company_id_branch_id_index ON public.idempotency_keys USING btree (company_id, branch_id);


--
-- Name: idempotency_keys_expires_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idempotency_keys_expires_at_index ON public.idempotency_keys USING btree (expires_at);


--
-- Name: idempotency_keys_key_expires_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idempotency_keys_key_expires_at_index ON public.idempotency_keys USING btree (key, expires_at);


--
-- Name: idempotency_keys_lock_token_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idempotency_keys_lock_token_index ON public.idempotency_keys USING btree (lock_token);


--
-- Name: idx_categories_name_translations; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_categories_name_translations ON public.categories USING gin (name_translations);


--
-- Name: idx_movements_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_movements_date ON public.raw_ingredient_movements USING btree (company_id, created_at);


--
-- Name: idx_movements_idempotency; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_movements_idempotency ON public.raw_ingredient_movements USING btree (company_id, branch_id, idempotency_key);


--
-- Name: idx_movements_ingredient; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_movements_ingredient ON public.raw_ingredient_movements USING btree (company_id, branch_id, raw_ingredient_id);


--
-- Name: idx_movements_order_item; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_movements_order_item ON public.raw_ingredient_movements USING btree (company_id, branch_id, order_item_uuid);


--
-- Name: idx_movements_reference; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_movements_reference ON public.raw_ingredient_movements USING btree (reference_type, reference_id);


--
-- Name: idx_orders_kitchen_queue; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_orders_kitchen_queue ON public.orders USING btree (branch_id, priority, status);


--
-- Name: idx_products_description_translations; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_products_description_translations ON public.products USING gin (description_translations);


--
-- Name: idx_products_name_translations; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_products_name_translations ON public.products USING gin (name_translations);


--
-- Name: idx_restaurant_tables_area_name_translations; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_restaurant_tables_area_name_translations ON public.restaurant_tables USING gin (area_name_translations);


--
-- Name: idx_tables_branch_area; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tables_branch_area ON public.restaurant_tables USING btree (branch_id, area_code);


--
-- Name: idx_tables_branch_zone; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tables_branch_zone ON public.restaurant_tables USING btree (branch_id, zone_id);


--
-- Name: idx_tables_zone_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tables_zone_status ON public.restaurant_tables USING btree (zone_id, status);


--
-- Name: idx_zones_tenant_active; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_zones_tenant_active ON public.dining_zones USING btree (company_id, branch_id, is_active);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: journal_entries_branch_id_entry_date_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX journal_entries_branch_id_entry_date_index ON public.journal_entries USING btree (branch_id, entry_date);


--
-- Name: journal_entries_company_id_entry_date_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX journal_entries_company_id_entry_date_index ON public.journal_entries USING btree (company_id, entry_date);


--
-- Name: ledger_entries_account_id_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX ledger_entries_account_id_created_at_index ON public.ledger_entries USING btree (account_id, created_at);


--
-- Name: ledger_entries_company_id_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX ledger_entries_company_id_created_at_index ON public.ledger_entries USING btree (company_id, created_at);


--
-- Name: ledger_entries_journal_entry_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX ledger_entries_journal_entry_id_index ON public.ledger_entries USING btree (journal_entry_id);


--
-- Name: menu_activations_menu_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX menu_activations_menu_id_is_active_index ON public.menu_activations USING btree (menu_id, is_active);


--
-- Name: menu_item_products_product_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX menu_item_products_product_id_index ON public.menu_item_products USING btree (product_id);


--
-- Name: menu_item_replacement_rules_company_id_branch_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX menu_item_replacement_rules_company_id_branch_id_index ON public.menu_item_replacement_rules USING btree (company_id, branch_id);


--
-- Name: menu_item_replacement_rules_company_id_menu_item_id_target_prod; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX menu_item_replacement_rules_company_id_menu_item_id_target_prod ON public.menu_item_replacement_rules USING btree (company_id, menu_item_id, target_product_id);


--
-- Name: menu_item_replacement_rules_menu_item_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX menu_item_replacement_rules_menu_item_id_is_active_index ON public.menu_item_replacement_rules USING btree (menu_item_id, is_active);


--
-- Name: menu_items_company_id_branch_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX menu_items_company_id_branch_id_index ON public.menu_items USING btree (company_id, branch_id);


--
-- Name: menu_items_company_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX menu_items_company_id_is_active_index ON public.menu_items USING btree (company_id, is_active);


--
-- Name: menu_products_menu_id_is_available_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX menu_products_menu_id_is_available_index ON public.menu_products USING btree (menu_id, is_available);


--
-- Name: menus_company_id_branch_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX menus_company_id_branch_id_is_active_index ON public.menus USING btree (company_id, branch_id, is_active);


--
-- Name: model_has_permissions_model_id_model_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_id, model_type);


--
-- Name: model_has_roles_model_id_model_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_id, model_type);


--
-- Name: order_item_modifiers_order_item_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX order_item_modifiers_order_item_id_index ON public.order_item_modifiers USING btree (order_item_id);


--
-- Name: order_item_modifiers_sync_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX order_item_modifiers_sync_status_index ON public.order_item_modifiers USING btree (sync_status);


--
-- Name: order_items_order_id_company_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX order_items_order_id_company_id_index ON public.order_items USING btree (order_id, company_id);


--
-- Name: order_items_order_id_product_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX order_items_order_id_product_id_index ON public.order_items USING btree (order_id, product_id);


--
-- Name: order_items_sync_status_company_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX order_items_sync_status_company_id_index ON public.order_items USING btree (sync_status, company_id);


--
-- Name: orders_branch_id_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX orders_branch_id_status_index ON public.orders USING btree (branch_id, status);


--
-- Name: orders_company_id_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX orders_company_id_status_index ON public.orders USING btree (company_id, status);


--
-- Name: orders_sync_status_branch_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX orders_sync_status_branch_id_index ON public.orders USING btree (sync_status, branch_id);


--
-- Name: orders_table_id_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX orders_table_id_status_index ON public.orders USING btree (table_id, status);


--
-- Name: orders_waiter_id_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX orders_waiter_id_status_index ON public.orders USING btree (waiter_id, status);


--
-- Name: payments_company_id_branch_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX payments_company_id_branch_id_index ON public.payments USING btree (company_id, branch_id);


--
-- Name: payments_order_id_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX payments_order_id_status_index ON public.payments USING btree (order_id, status);


--
-- Name: payments_sync_status_branch_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX payments_sync_status_branch_id_index ON public.payments USING btree (sync_status, branch_id);


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: price_lists_company_id_branch_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX price_lists_company_id_branch_id_is_active_index ON public.price_lists USING btree (company_id, branch_id, is_active);


--
-- Name: print_jobs_company_id_branch_id_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX print_jobs_company_id_branch_id_status_index ON public.print_jobs USING btree (company_id, branch_id, status);


--
-- Name: print_jobs_order_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX print_jobs_order_id_index ON public.print_jobs USING btree (order_id);


--
-- Name: print_jobs_printer_id_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX print_jobs_printer_id_status_index ON public.print_jobs USING btree (printer_id, status);


--
-- Name: print_jobs_status_claimed_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX print_jobs_status_claimed_at_index ON public.print_jobs USING btree (status, claimed_at);


--
-- Name: printer_station_mappings_company_id_branch_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX printer_station_mappings_company_id_branch_id_index ON public.printer_station_mappings USING btree (company_id, branch_id);


--
-- Name: printers_company_id_branch_id_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX printers_company_id_branch_id_type_index ON public.printers USING btree (company_id, branch_id, type);


--
-- Name: product_recipes_company_id_version_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX product_recipes_company_id_version_index ON public.product_recipes USING btree (company_id, version);


--
-- Name: products_company_id_branch_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX products_company_id_branch_id_index ON public.products USING btree (company_id, branch_id);


--
-- Name: products_company_id_category_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX products_company_id_category_id_index ON public.products USING btree (company_id, category_id);


--
-- Name: products_company_id_is_combo_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX products_company_id_is_combo_is_active_index ON public.products USING btree (company_id, is_combo, is_active);


--
-- Name: products_company_id_tax_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX products_company_id_tax_id_index ON public.products USING btree (company_id, tax_id);


--
-- Name: purchases_document_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX purchases_document_index ON public.raw_ingredient_purchases USING btree (document_type, document_number);


--
-- Name: raw_ingredient_movements_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX raw_ingredient_movements_type_index ON public.raw_ingredient_movements USING btree (type);


--
-- Name: raw_ingredient_purchases_raw_ingredient_id_purchase_date_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX raw_ingredient_purchases_raw_ingredient_id_purchase_date_index ON public.raw_ingredient_purchases USING btree (raw_ingredient_id, purchase_date);


--
-- Name: raw_ingredients_company_id_branch_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX raw_ingredients_company_id_branch_id_index ON public.raw_ingredients USING btree (company_id, branch_id);


--
-- Name: recipe_items_raw_ingredient_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX recipe_items_raw_ingredient_id_index ON public.recipe_items USING btree (raw_ingredient_id);


--
-- Name: recipe_items_recipe_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX recipe_items_recipe_id_index ON public.recipe_items USING btree (recipe_id);


--
-- Name: refunds_company_id_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX refunds_company_id_created_at_index ON public.refunds USING btree (company_id, created_at);


--
-- Name: refunds_payment_id_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX refunds_payment_id_status_index ON public.refunds USING btree (payment_id, status);


--
-- Name: restaurant_tables_branch_id_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX restaurant_tables_branch_id_status_index ON public.restaurant_tables USING btree (branch_id, status);


--
-- Name: restaurant_tables_company_id_branch_id_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX restaurant_tables_company_id_branch_id_status_index ON public.restaurant_tables USING btree (company_id, branch_id, status);


--
-- Name: restaurant_tables_current_order_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX restaurant_tables_current_order_id_index ON public.restaurant_tables USING btree (current_order_id);


--
-- Name: restaurant_tables_sync_status_branch_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX restaurant_tables_sync_status_branch_id_index ON public.restaurant_tables USING btree (sync_status, branch_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: sync_log_company_id_branch_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sync_log_company_id_branch_id_index ON public.sync_log USING btree (company_id, branch_id);


--
-- Name: sync_log_entity_type_entity_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sync_log_entity_type_entity_id_index ON public.sync_log USING btree (entity_type, entity_id);


--
-- Name: sync_log_sync_session_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sync_log_sync_session_id_index ON public.sync_log USING btree (sync_session_id);


--
-- Name: sync_log_synced_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sync_log_synced_at_index ON public.sync_log USING btree (synced_at);


--
-- Name: sync_queue_company_id_branch_id_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sync_queue_company_id_branch_id_status_index ON public.sync_queue USING btree (company_id, branch_id, status);


--
-- Name: sync_queue_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sync_queue_created_at_index ON public.sync_queue USING btree (created_at);


--
-- Name: sync_queue_entity_type_entity_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sync_queue_entity_type_entity_id_index ON public.sync_queue USING btree (entity_type, entity_id);


--
-- Name: sync_queue_status_next_attempt_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sync_queue_status_next_attempt_at_index ON public.sync_queue USING btree (status, next_attempt_at);


--
-- Name: taxes_company_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX taxes_company_id_is_active_index ON public.taxes USING btree (company_id, is_active);


--
-- Name: taxes_company_id_is_default_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX taxes_company_id_is_default_index ON public.taxes USING btree (company_id, is_default);


--
-- Name: terminals_branch_id_is_kds_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX terminals_branch_id_is_kds_index ON public.terminals USING btree (branch_id, is_kds);


--
-- Name: terminals_branch_id_is_pos_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX terminals_branch_id_is_pos_index ON public.terminals USING btree (branch_id, is_pos);


--
-- Name: terminals_company_id_branch_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX terminals_company_id_branch_id_is_active_index ON public.terminals USING btree (company_id, branch_id, is_active);


--
-- Name: tip_payouts_company_id_branch_id_cash_session_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX tip_payouts_company_id_branch_id_cash_session_id_index ON public.tip_payouts USING btree (company_id, branch_id, cash_session_id);


--
-- Name: tip_payouts_waiter_id_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX tip_payouts_waiter_id_created_at_index ON public.tip_payouts USING btree (waiter_id, created_at);


--
-- Name: tip_policies_company_id_branch_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX tip_policies_company_id_branch_id_index ON public.tip_policies USING btree (company_id, branch_id);


--
-- Name: users_company_id_branch_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX users_company_id_branch_id_index ON public.users USING btree (company_id, branch_id);


--
-- Name: users_company_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX users_company_id_is_active_index ON public.users USING btree (company_id, is_active);


--
-- Name: users_role_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX users_role_index ON public.users USING btree (role);


--
-- Name: accounts accounts_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.accounts
    ADD CONSTRAINT accounts_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: accounts accounts_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.accounts
    ADD CONSTRAINT accounts_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: accounts accounts_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.accounts
    ADD CONSTRAINT accounts_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.accounts(id) ON DELETE SET NULL;


--
-- Name: audit_logs audit_logs_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;


--
-- Name: audit_logs audit_logs_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE SET NULL;


--
-- Name: audit_logs audit_logs_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: bills bills_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.bills
    ADD CONSTRAINT bills_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: bills bills_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.bills
    ADD CONSTRAINT bills_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: bills bills_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.bills
    ADD CONSTRAINT bills_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.orders(id) ON DELETE CASCADE;


--
-- Name: branches branches_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: cash_counts cash_counts_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_counts
    ADD CONSTRAINT cash_counts_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: cash_counts cash_counts_cash_session_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_counts
    ADD CONSTRAINT cash_counts_cash_session_id_foreign FOREIGN KEY (cash_session_id) REFERENCES public.cash_sessions(id) ON DELETE CASCADE;


--
-- Name: cash_counts cash_counts_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_counts
    ADD CONSTRAINT cash_counts_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: cash_counts cash_counts_supervised_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_counts
    ADD CONSTRAINT cash_counts_supervised_by_foreign FOREIGN KEY (supervised_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: cash_counts cash_counts_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_counts
    ADD CONSTRAINT cash_counts_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: cash_movements cash_movements_authorized_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_movements
    ADD CONSTRAINT cash_movements_authorized_by_foreign FOREIGN KEY (authorized_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: cash_movements cash_movements_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_movements
    ADD CONSTRAINT cash_movements_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: cash_movements cash_movements_cash_session_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_movements
    ADD CONSTRAINT cash_movements_cash_session_id_foreign FOREIGN KEY (cash_session_id) REFERENCES public.cash_sessions(id) ON DELETE CASCADE;


--
-- Name: cash_movements cash_movements_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_movements
    ADD CONSTRAINT cash_movements_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: cash_movements cash_movements_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_movements
    ADD CONSTRAINT cash_movements_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: cash_registers cash_registers_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_registers
    ADD CONSTRAINT cash_registers_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: cash_registers cash_registers_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_registers
    ADD CONSTRAINT cash_registers_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: cash_sessions cash_sessions_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_sessions
    ADD CONSTRAINT cash_sessions_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: cash_sessions cash_sessions_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_sessions
    ADD CONSTRAINT cash_sessions_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: cash_sessions cash_sessions_register_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_sessions
    ADD CONSTRAINT cash_sessions_register_id_foreign FOREIGN KEY (register_id) REFERENCES public.cash_registers(id) ON DELETE SET NULL;


--
-- Name: cash_sessions cash_sessions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_sessions
    ADD CONSTRAINT cash_sessions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: categories categories_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: categories categories_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: categories categories_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.categories(id) ON DELETE SET NULL;


--
-- Name: categories categories_tax_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_tax_id_foreign FOREIGN KEY (tax_id) REFERENCES public.taxes(id) ON DELETE SET NULL;


--
-- Name: company_capabilities company_capabilities_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.company_capabilities
    ADD CONSTRAINT company_capabilities_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: default_notes default_notes_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.default_notes
    ADD CONSTRAINT default_notes_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: default_notes default_notes_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.default_notes
    ADD CONSTRAINT default_notes_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: dining_zones dining_zones_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dining_zones
    ADD CONSTRAINT dining_zones_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: dining_zones dining_zones_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dining_zones
    ADD CONSTRAINT dining_zones_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: dte_certificates dte_certificates_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dte_certificates
    ADD CONSTRAINT dte_certificates_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: dte_documents dte_documents_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dte_documents
    ADD CONSTRAINT dte_documents_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: dte_documents dte_documents_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dte_documents
    ADD CONSTRAINT dte_documents_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: dte_documents dte_documents_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dte_documents
    ADD CONSTRAINT dte_documents_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.orders(id) ON DELETE SET NULL;


--
-- Name: dte_documents dte_documents_referenced_dte_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dte_documents
    ADD CONSTRAINT dte_documents_referenced_dte_id_foreign FOREIGN KEY (referenced_dte_id) REFERENCES public.dte_documents(id) ON DELETE SET NULL;


--
-- Name: dte_folio_ranges dte_folio_ranges_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dte_folio_ranges
    ADD CONSTRAINT dte_folio_ranges_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: dte_folio_ranges dte_folio_ranges_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.dte_folio_ranges
    ADD CONSTRAINT dte_folio_ranges_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: idempotency_keys idempotency_keys_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.idempotency_keys
    ADD CONSTRAINT idempotency_keys_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: journal_entries journal_entries_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.journal_entries
    ADD CONSTRAINT journal_entries_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: journal_entries journal_entries_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.journal_entries
    ADD CONSTRAINT journal_entries_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: journal_entries journal_entries_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.journal_entries
    ADD CONSTRAINT journal_entries_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ledger_entries ledger_entries_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ledger_entries
    ADD CONSTRAINT ledger_entries_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.accounts(id) ON DELETE CASCADE;


--
-- Name: ledger_entries ledger_entries_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ledger_entries
    ADD CONSTRAINT ledger_entries_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: ledger_entries ledger_entries_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ledger_entries
    ADD CONSTRAINT ledger_entries_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: ledger_entries ledger_entries_journal_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ledger_entries
    ADD CONSTRAINT ledger_entries_journal_entry_id_foreign FOREIGN KEY (journal_entry_id) REFERENCES public.journal_entries(id) ON DELETE CASCADE;


--
-- Name: menu_activations menu_activations_menu_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_activations
    ADD CONSTRAINT menu_activations_menu_id_foreign FOREIGN KEY (menu_id) REFERENCES public.menus(id) ON DELETE CASCADE;


--
-- Name: menu_item_products menu_item_products_menu_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_item_products
    ADD CONSTRAINT menu_item_products_menu_item_id_foreign FOREIGN KEY (menu_item_id) REFERENCES public.menu_items(id) ON DELETE CASCADE;


--
-- Name: menu_item_products menu_item_products_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_item_products
    ADD CONSTRAINT menu_item_products_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: menu_item_replacement_rules menu_item_replacement_rules_allowed_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_item_replacement_rules
    ADD CONSTRAINT menu_item_replacement_rules_allowed_category_id_foreign FOREIGN KEY (allowed_category_id) REFERENCES public.categories(id) ON DELETE CASCADE;


--
-- Name: menu_item_replacement_rules menu_item_replacement_rules_allowed_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_item_replacement_rules
    ADD CONSTRAINT menu_item_replacement_rules_allowed_product_id_foreign FOREIGN KEY (allowed_product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: menu_item_replacement_rules menu_item_replacement_rules_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_item_replacement_rules
    ADD CONSTRAINT menu_item_replacement_rules_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: menu_item_replacement_rules menu_item_replacement_rules_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_item_replacement_rules
    ADD CONSTRAINT menu_item_replacement_rules_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: menu_item_replacement_rules menu_item_replacement_rules_menu_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_item_replacement_rules
    ADD CONSTRAINT menu_item_replacement_rules_menu_item_id_foreign FOREIGN KEY (menu_item_id) REFERENCES public.menu_items(id) ON DELETE CASCADE;


--
-- Name: menu_item_replacement_rules menu_item_replacement_rules_target_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_item_replacement_rules
    ADD CONSTRAINT menu_item_replacement_rules_target_product_id_foreign FOREIGN KEY (target_product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: menu_items menu_items_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_items
    ADD CONSTRAINT menu_items_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: menu_items menu_items_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_items
    ADD CONSTRAINT menu_items_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: menu_items menu_items_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_items
    ADD CONSTRAINT menu_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: menu_products menu_products_menu_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_products
    ADD CONSTRAINT menu_products_menu_id_foreign FOREIGN KEY (menu_id) REFERENCES public.menus(id) ON DELETE CASCADE;


--
-- Name: menu_products menu_products_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menu_products
    ADD CONSTRAINT menu_products_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id);


--
-- Name: menus menus_price_list_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.menus
    ADD CONSTRAINT menus_price_list_id_foreign FOREIGN KEY (price_list_id) REFERENCES public.price_lists(id);


--
-- Name: model_has_permissions model_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: model_has_roles model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: order_item_modifiers order_item_modifiers_added_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.order_item_modifiers
    ADD CONSTRAINT order_item_modifiers_added_product_id_foreign FOREIGN KEY (added_product_id) REFERENCES public.products(id) ON DELETE SET NULL;


--
-- Name: order_item_modifiers order_item_modifiers_authorized_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.order_item_modifiers
    ADD CONSTRAINT order_item_modifiers_authorized_by_foreign FOREIGN KEY (authorized_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: order_item_modifiers order_item_modifiers_order_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.order_item_modifiers
    ADD CONSTRAINT order_item_modifiers_order_item_id_foreign FOREIGN KEY (order_item_id) REFERENCES public.order_items(id) ON DELETE CASCADE;


--
-- Name: order_item_modifiers order_item_modifiers_original_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.order_item_modifiers
    ADD CONSTRAINT order_item_modifiers_original_product_id_foreign FOREIGN KEY (original_product_id) REFERENCES public.products(id) ON DELETE SET NULL;


--
-- Name: order_item_modifiers order_item_modifiers_substitute_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.order_item_modifiers
    ADD CONSTRAINT order_item_modifiers_substitute_product_id_foreign FOREIGN KEY (substitute_product_id) REFERENCES public.products(id) ON DELETE SET NULL;


--
-- Name: order_items order_items_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.order_items
    ADD CONSTRAINT order_items_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: order_items order_items_menu_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.order_items
    ADD CONSTRAINT order_items_menu_item_id_foreign FOREIGN KEY (menu_item_id) REFERENCES public.menu_items(id) ON DELETE SET NULL;


--
-- Name: order_items order_items_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.order_items
    ADD CONSTRAINT order_items_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.orders(id) ON DELETE CASCADE;


--
-- Name: order_items order_items_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.order_items
    ADD CONSTRAINT order_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE SET NULL;


--
-- Name: orders orders_assigned_cook_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_assigned_cook_id_foreign FOREIGN KEY (assigned_cook_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: orders orders_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: orders orders_cashier_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_cashier_id_foreign FOREIGN KEY (cashier_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: orders orders_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: orders orders_table_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_table_id_foreign FOREIGN KEY (table_id) REFERENCES public.restaurant_tables(id) ON DELETE SET NULL;


--
-- Name: orders orders_waiter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_waiter_id_foreign FOREIGN KEY (waiter_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: payment_methods payment_methods_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payment_methods
    ADD CONSTRAINT payment_methods_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: payment_methods payment_methods_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payment_methods
    ADD CONSTRAINT payment_methods_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: payments payments_bill_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_bill_id_foreign FOREIGN KEY (bill_id) REFERENCES public.bills(id) ON DELETE SET NULL;


--
-- Name: payments payments_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: payments payments_cash_session_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_cash_session_id_foreign FOREIGN KEY (cash_session_id) REFERENCES public.cash_sessions(id) ON DELETE SET NULL;


--
-- Name: payments payments_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: payments payments_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.orders(id) ON DELETE CASCADE;


--
-- Name: payments payments_payment_method_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_payment_method_id_foreign FOREIGN KEY (payment_method_id) REFERENCES public.payment_methods(id) ON DELETE RESTRICT;


--
-- Name: payments payments_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: print_jobs print_jobs_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.print_jobs
    ADD CONSTRAINT print_jobs_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: print_jobs print_jobs_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.print_jobs
    ADD CONSTRAINT print_jobs_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: print_jobs print_jobs_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.print_jobs
    ADD CONSTRAINT print_jobs_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.orders(id) ON DELETE SET NULL;


--
-- Name: print_jobs print_jobs_printer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.print_jobs
    ADD CONSTRAINT print_jobs_printer_id_foreign FOREIGN KEY (printer_id) REFERENCES public.printers(id) ON DELETE CASCADE;


--
-- Name: printer_station_mappings printer_station_mappings_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.printer_station_mappings
    ADD CONSTRAINT printer_station_mappings_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: printer_station_mappings printer_station_mappings_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.printer_station_mappings
    ADD CONSTRAINT printer_station_mappings_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.categories(id) ON DELETE CASCADE;


--
-- Name: printer_station_mappings printer_station_mappings_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.printer_station_mappings
    ADD CONSTRAINT printer_station_mappings_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: printer_station_mappings printer_station_mappings_printer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.printer_station_mappings
    ADD CONSTRAINT printer_station_mappings_printer_id_foreign FOREIGN KEY (printer_id) REFERENCES public.printers(id) ON DELETE CASCADE;


--
-- Name: printers printers_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.printers
    ADD CONSTRAINT printers_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: printers printers_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.printers
    ADD CONSTRAINT printers_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: product_prices product_prices_price_list_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.product_prices
    ADD CONSTRAINT product_prices_price_list_id_foreign FOREIGN KEY (price_list_id) REFERENCES public.price_lists(id);


--
-- Name: product_prices product_prices_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.product_prices
    ADD CONSTRAINT product_prices_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id);


--
-- Name: product_recipes product_recipes_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.product_recipes
    ADD CONSTRAINT product_recipes_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: product_recipes product_recipes_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.product_recipes
    ADD CONSTRAINT product_recipes_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: products products_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: products products_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.categories(id) ON DELETE SET NULL;


--
-- Name: products products_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: products products_tax_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_tax_id_foreign FOREIGN KEY (tax_id) REFERENCES public.taxes(id) ON DELETE SET NULL;


--
-- Name: raw_ingredient_movements raw_ingredient_movements_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.raw_ingredient_movements
    ADD CONSTRAINT raw_ingredient_movements_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: raw_ingredient_movements raw_ingredient_movements_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.raw_ingredient_movements
    ADD CONSTRAINT raw_ingredient_movements_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: raw_ingredient_movements raw_ingredient_movements_raw_ingredient_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.raw_ingredient_movements
    ADD CONSTRAINT raw_ingredient_movements_raw_ingredient_id_foreign FOREIGN KEY (raw_ingredient_id) REFERENCES public.raw_ingredients(id) ON DELETE CASCADE;


--
-- Name: raw_ingredient_movements raw_ingredient_movements_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.raw_ingredient_movements
    ADD CONSTRAINT raw_ingredient_movements_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: raw_ingredient_purchases raw_ingredient_purchases_raw_ingredient_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.raw_ingredient_purchases
    ADD CONSTRAINT raw_ingredient_purchases_raw_ingredient_id_foreign FOREIGN KEY (raw_ingredient_id) REFERENCES public.raw_ingredients(id) ON DELETE CASCADE;


--
-- Name: raw_ingredient_purchases raw_ingredient_purchases_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.raw_ingredient_purchases
    ADD CONSTRAINT raw_ingredient_purchases_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: raw_ingredients raw_ingredients_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.raw_ingredients
    ADD CONSTRAINT raw_ingredients_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: raw_ingredients raw_ingredients_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.raw_ingredients
    ADD CONSTRAINT raw_ingredients_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: recipe_items recipe_items_raw_ingredient_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.recipe_items
    ADD CONSTRAINT recipe_items_raw_ingredient_id_foreign FOREIGN KEY (raw_ingredient_id) REFERENCES public.raw_ingredients(id) ON DELETE CASCADE;


--
-- Name: recipe_items recipe_items_recipe_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.recipe_items
    ADD CONSTRAINT recipe_items_recipe_id_foreign FOREIGN KEY (recipe_id) REFERENCES public.product_recipes(id) ON DELETE CASCADE;


--
-- Name: refunds refunds_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.refunds
    ADD CONSTRAINT refunds_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: refunds refunds_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.refunds
    ADD CONSTRAINT refunds_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: refunds refunds_journal_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.refunds
    ADD CONSTRAINT refunds_journal_entry_id_foreign FOREIGN KEY (journal_entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;


--
-- Name: refunds refunds_payment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.refunds
    ADD CONSTRAINT refunds_payment_id_foreign FOREIGN KEY (payment_id) REFERENCES public.payments(id) ON DELETE CASCADE;


--
-- Name: refunds refunds_processed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.refunds
    ADD CONSTRAINT refunds_processed_by_foreign FOREIGN KEY (processed_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: restaurant_tables restaurant_tables_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.restaurant_tables
    ADD CONSTRAINT restaurant_tables_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: restaurant_tables restaurant_tables_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.restaurant_tables
    ADD CONSTRAINT restaurant_tables_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: restaurant_tables restaurant_tables_zone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.restaurant_tables
    ADD CONSTRAINT restaurant_tables_zone_id_foreign FOREIGN KEY (zone_id) REFERENCES public.dining_zones(id) ON DELETE SET NULL;


--
-- Name: role_has_permissions role_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: sync_log sync_log_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sync_log
    ADD CONSTRAINT sync_log_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;


--
-- Name: sync_log sync_log_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sync_log
    ADD CONSTRAINT sync_log_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE SET NULL;


--
-- Name: sync_queue sync_queue_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sync_queue
    ADD CONSTRAINT sync_queue_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;


--
-- Name: sync_queue sync_queue_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sync_queue
    ADD CONSTRAINT sync_queue_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE SET NULL;


--
-- Name: taxes taxes_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.taxes
    ADD CONSTRAINT taxes_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: terminals terminals_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.terminals
    ADD CONSTRAINT terminals_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: terminals terminals_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.terminals
    ADD CONSTRAINT terminals_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: users users_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;


--
-- Name: users users_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--

\unrestrict RunJdSqv6rp813mEatcRhDYH0D0TqKNyLBXS3rI9PpNaAABoWDoT9bzk9ZX3GgS

