--
-- PostgreSQL database dump
--

\restrict mcoZeuQUsOnta5gGw4765gqnXNdjc8dwOpJhpqWuCdfZO3h6kHIDeU8S3nxYTpk

-- Dumped from database version 15.18
-- Dumped by pg_dump version 15.18

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


ALTER TABLE public.accounts_id_seq OWNER TO postgres;

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


ALTER TABLE public.audit_logs_id_seq OWNER TO postgres;

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
    subtotal numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    tax_amount numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    discount_amount numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    tip_amount numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    total numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    paid_amount numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    remaining_amount numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    status character varying(30) DEFAULT 'open'::character varying NOT NULL,
    guest_count integer DEFAULT 1 NOT NULL,
    item_ids jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    sync_status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    version integer DEFAULT 1 NOT NULL,
    last_synced_at timestamp(0) without time zone,
    offline_id character varying(64)
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


ALTER TABLE public.bills_id_seq OWNER TO postgres;

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


ALTER TABLE public.branches_id_seq OWNER TO postgres;

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
    expected_amount numeric(14,2) NOT NULL,
    counted_amount numeric(14,2) NOT NULL,
    difference numeric(14,2) NOT NULL,
    denominations jsonb,
    cash_amount numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    card_amount numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    transfer_amount numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    other_amount numeric(14,2) DEFAULT '0'::numeric NOT NULL,
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


ALTER TABLE public.cash_counts_id_seq OWNER TO postgres;

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
    amount numeric(14,2) NOT NULL,
    reason character varying(200) NOT NULL,
    notes text,
    reference_type character varying(50),
    reference_id character varying(100),
    authorized_by bigint,
    authorized_at timestamp(0) without time zone,
    balance_after numeric(14,2) NOT NULL,
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


ALTER TABLE public.cash_movements_id_seq OWNER TO postgres;

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
    max_amount numeric(14,2) DEFAULT '500000'::numeric NOT NULL,
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


ALTER TABLE public.cash_registers_id_seq OWNER TO postgres;

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
    opening_amount numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    closing_amount numeric(14,2),
    expected_amount numeric(14,2),
    difference numeric(14,2),
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


ALTER TABLE public.cash_sessions_id_seq OWNER TO postgres;

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


ALTER TABLE public.categories_id_seq OWNER TO postgres;

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


ALTER TABLE public.companies_id_seq OWNER TO postgres;

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


ALTER TABLE public.company_capabilities_id_seq OWNER TO postgres;

--
-- Name: company_capabilities_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.company_capabilities_id_seq OWNED BY public.company_capabilities.id;


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


ALTER TABLE public.dte_certificates_id_seq OWNER TO postgres;

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


ALTER TABLE public.dte_documents_id_seq OWNER TO postgres;

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


ALTER TABLE public.dte_folio_ranges_id_seq OWNER TO postgres;

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


ALTER TABLE public.failed_jobs_id_seq OWNER TO postgres;

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
    updated_at timestamp(0) without time zone
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


ALTER TABLE public.idempotency_keys_id_seq OWNER TO postgres;

--
-- Name: idempotency_keys_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.idempotency_keys_id_seq OWNED BY public.idempotency_keys.id;


--
-- Name: inventory_items; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.inventory_items (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    sku character varying(100),
    name_translations jsonb NOT NULL,
    unit character varying(20) DEFAULT 'unit'::character varying NOT NULL,
    cost_price numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    min_stock numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.inventory_items OWNER TO postgres;

--
-- Name: inventory_items_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.inventory_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.inventory_items_id_seq OWNER TO postgres;

--
-- Name: inventory_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.inventory_items_id_seq OWNED BY public.inventory_items.id;


--
-- Name: inventory_stocks; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.inventory_stocks (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    inventory_item_id bigint NOT NULL,
    quantity numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    last_movement_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.inventory_stocks OWNER TO postgres;

--
-- Name: inventory_stocks_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.inventory_stocks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.inventory_stocks_id_seq OWNER TO postgres;

--
-- Name: inventory_stocks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.inventory_stocks_id_seq OWNED BY public.inventory_stocks.id;


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


ALTER TABLE public.jobs_id_seq OWNER TO postgres;

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


ALTER TABLE public.journal_entries_id_seq OWNER TO postgres;

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
    debit_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    credit_amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
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


ALTER TABLE public.ledger_entries_id_seq OWNER TO postgres;

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


ALTER TABLE public.menu_activations_id_seq OWNER TO postgres;

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


ALTER TABLE public.menu_item_products_id_seq OWNER TO postgres;

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
    max_price_delta numeric(12,2),
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


ALTER TABLE public.menu_item_replacement_rules_id_seq OWNER TO postgres;

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
    base_price numeric(12,2) NOT NULL,
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


ALTER TABLE public.menu_items_id_seq OWNER TO postgres;

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


ALTER TABLE public.menu_products_id_seq OWNER TO postgres;

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


ALTER TABLE public.menus_id_seq OWNER TO postgres;

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


ALTER TABLE public.migrations_id_seq OWNER TO postgres;

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
    price_adjustment numeric(10,2) DEFAULT '0'::numeric NOT NULL,
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


ALTER TABLE public.order_item_modifiers_id_seq OWNER TO postgres;

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
    unit_price_snapshot numeric(10,2) NOT NULL,
    quantity integer DEFAULT 1 NOT NULL,
    notes text,
    subtotal numeric(10,2) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    product_id bigint,
    tax_amount numeric(14,2) DEFAULT '0'::numeric NOT NULL,
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


ALTER TABLE public.order_items_id_seq OWNER TO postgres;

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
    subtotal numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    tax_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    discount_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    total numeric(10,2) DEFAULT '0'::numeric NOT NULL,
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
    subtotal_gross numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    net_amount numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    tip_amount numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    amount_due numeric(14,2) DEFAULT '0'::numeric NOT NULL,
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


ALTER TABLE public.orders_id_seq OWNER TO postgres;

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
    max_amount numeric(14,2),
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


ALTER TABLE public.payment_methods_id_seq OWNER TO postgres;

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
    amount numeric(14,2) NOT NULL,
    tip_amount numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    total_amount numeric(14,2) NOT NULL,
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


ALTER TABLE public.payments_id_seq OWNER TO postgres;

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


ALTER TABLE public.permissions_id_seq OWNER TO postgres;

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


ALTER TABLE public.personal_access_tokens_id_seq OWNER TO postgres;

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


ALTER TABLE public.price_lists_id_seq OWNER TO postgres;

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


ALTER TABLE public.print_jobs_id_seq OWNER TO postgres;

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


ALTER TABLE public.printer_station_mappings_id_seq OWNER TO postgres;

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


ALTER TABLE public.printers_id_seq OWNER TO postgres;

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
    price numeric(10,2) NOT NULL,
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


ALTER TABLE public.product_prices_id_seq OWNER TO postgres;

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
    deleted_at timestamp(0) without time zone
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


ALTER TABLE public.product_recipes_id_seq OWNER TO postgres;

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
    base_price numeric(12,2) DEFAULT '0'::numeric NOT NULL,
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


ALTER TABLE public.products_id_seq OWNER TO postgres;

--
-- Name: products_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.products_id_seq OWNED BY public.products.id;


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
    updated_at timestamp(0) without time zone
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


ALTER TABLE public.raw_ingredient_purchases_id_seq OWNER TO postgres;

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
    deleted_at timestamp(0) without time zone
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


ALTER TABLE public.raw_ingredients_id_seq OWNER TO postgres;

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
    updated_at timestamp(0) without time zone
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


ALTER TABLE public.recipe_items_id_seq OWNER TO postgres;

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
    amount numeric(15,2) NOT NULL,
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


ALTER TABLE public.refunds_id_seq OWNER TO postgres;

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


ALTER TABLE public.restaurant_tables_id_seq OWNER TO postgres;

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


ALTER TABLE public.roles_id_seq OWNER TO postgres;

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
-- Name: stock_movements; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.stock_movements (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    company_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    inventory_item_id bigint NOT NULL,
    type character varying(30) NOT NULL,
    quantity numeric(12,2) NOT NULL,
    balance_after numeric(12,2) NOT NULL,
    reference_type character varying(50),
    reference_id bigint,
    user_id bigint,
    reason text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.stock_movements OWNER TO postgres;

--
-- Name: stock_movements_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.stock_movements_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.stock_movements_id_seq OWNER TO postgres;

--
-- Name: stock_movements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.stock_movements_id_seq OWNED BY public.stock_movements.id;


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


ALTER TABLE public.sync_log_id_seq OWNER TO postgres;

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
    conflict_data json
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


ALTER TABLE public.sync_queue_id_seq OWNER TO postgres;

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


ALTER TABLE public.taxes_id_seq OWNER TO postgres;

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


ALTER TABLE public.terminals_id_seq OWNER TO postgres;

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
    amount numeric(12,2) NOT NULL,
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


ALTER TABLE public.tip_payouts_id_seq OWNER TO postgres;

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


ALTER TABLE public.tip_policies_id_seq OWNER TO postgres;

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


ALTER TABLE public.users_id_seq OWNER TO postgres;

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
-- Name: inventory_items id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inventory_items ALTER COLUMN id SET DEFAULT nextval('public.inventory_items_id_seq'::regclass);


--
-- Name: inventory_stocks id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inventory_stocks ALTER COLUMN id SET DEFAULT nextval('public.inventory_stocks_id_seq'::regclass);


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
-- Name: stock_movements id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_movements ALTER COLUMN id SET DEFAULT nextval('public.stock_movements_id_seq'::regclass);


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
-- Name: idempotency_keys idempotency_keys_key_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.idempotency_keys
    ADD CONSTRAINT idempotency_keys_key_unique UNIQUE (key);


--
-- Name: idempotency_keys idempotency_keys_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.idempotency_keys
    ADD CONSTRAINT idempotency_keys_pkey PRIMARY KEY (id);


--
-- Name: inventory_items inventory_items_company_id_sku_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inventory_items
    ADD CONSTRAINT inventory_items_company_id_sku_unique UNIQUE (company_id, sku);


--
-- Name: inventory_items inventory_items_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inventory_items
    ADD CONSTRAINT inventory_items_pkey PRIMARY KEY (id);


--
-- Name: inventory_items inventory_items_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inventory_items
    ADD CONSTRAINT inventory_items_uuid_unique UNIQUE (uuid);


--
-- Name: inventory_stocks inventory_stocks_branch_id_inventory_item_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inventory_stocks
    ADD CONSTRAINT inventory_stocks_branch_id_inventory_item_id_unique UNIQUE (branch_id, inventory_item_id);


--
-- Name: inventory_stocks inventory_stocks_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inventory_stocks
    ADD CONSTRAINT inventory_stocks_pkey PRIMARY KEY (id);


--
-- Name: inventory_stocks inventory_stocks_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inventory_stocks
    ADD CONSTRAINT inventory_stocks_uuid_unique UNIQUE (uuid);


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
-- Name: journal_entries journal_entries_journal_entry_number_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.journal_entries
    ADD CONSTRAINT journal_entries_journal_entry_number_unique UNIQUE (journal_entry_number);


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
-- Name: payments payments_idempotency_key_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_idempotency_key_unique UNIQUE (idempotency_key);


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
-- Name: stock_movements stock_movements_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT stock_movements_pkey PRIMARY KEY (id);


--
-- Name: stock_movements stock_movements_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT stock_movements_uuid_unique UNIQUE (uuid);


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
-- Name: idempotency_keys_expires_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idempotency_keys_expires_at_index ON public.idempotency_keys USING btree (expires_at);


--
-- Name: idempotency_keys_key_expires_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idempotency_keys_key_expires_at_index ON public.idempotency_keys USING btree (key, expires_at);


--
-- Name: idx_categories_name_translations; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_categories_name_translations ON public.categories USING gin (name_translations);


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
-- Name: inventory_items_company_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX inventory_items_company_id_is_active_index ON public.inventory_items USING btree (company_id, is_active);


--
-- Name: inventory_stocks_company_id_branch_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX inventory_stocks_company_id_branch_id_index ON public.inventory_stocks USING btree (company_id, branch_id);


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
-- Name: stock_movements_company_id_branch_id_inventory_item_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX stock_movements_company_id_branch_id_inventory_item_id_index ON public.stock_movements USING btree (company_id, branch_id, inventory_item_id);


--
-- Name: stock_movements_company_id_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX stock_movements_company_id_created_at_index ON public.stock_movements USING btree (company_id, created_at);


--
-- Name: stock_movements_reference_type_reference_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX stock_movements_reference_type_reference_id_index ON public.stock_movements USING btree (reference_type, reference_id);


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
-- Name: inventory_items inventory_items_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inventory_items
    ADD CONSTRAINT inventory_items_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: inventory_stocks inventory_stocks_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inventory_stocks
    ADD CONSTRAINT inventory_stocks_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: inventory_stocks inventory_stocks_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inventory_stocks
    ADD CONSTRAINT inventory_stocks_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: inventory_stocks inventory_stocks_inventory_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inventory_stocks
    ADD CONSTRAINT inventory_stocks_inventory_item_id_foreign FOREIGN KEY (inventory_item_id) REFERENCES public.inventory_items(id) ON DELETE CASCADE;


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
-- Name: stock_movements stock_movements_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT stock_movements_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: stock_movements stock_movements_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT stock_movements_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: stock_movements stock_movements_inventory_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT stock_movements_inventory_item_id_foreign FOREIGN KEY (inventory_item_id) REFERENCES public.inventory_items(id) ON DELETE CASCADE;


--
-- Name: stock_movements stock_movements_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT stock_movements_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


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

\unrestrict mcoZeuQUsOnta5gGw4765gqnXNdjc8dwOpJhpqWuCdfZO3h6kHIDeU8S3nxYTpk

