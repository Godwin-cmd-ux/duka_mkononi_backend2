-- ============================================================================
-- DUKA MKONONI — SYSTEM_SCHEMA.sql
--
-- The constant database structure. This file is the single source of truth for
-- what a backup contains: DatabaseBackupService reads it to learn the table
-- list and each table's columns, then copies it into every backup folder as
-- schema_<stamp>.sql, unchanged.
--
-- The DATA is different every run; this file is not. "Schema is constant, seed
-- is refetched" is the whole contract:
--
--   schema_<stamp>.sql   this file, verbatim
--   seed_<stamp>.sql     every row of every table at the moment of the backup
--
-- Restore = run schema first (creates the tables), then seed (fills them).
--
-- Tables are in parent-before-child order so a restore never trips a foreign
-- key. Keep that order when adding a table.
-- ============================================================================

CREATE TABLE public.users (
    id UUID PRIMARY KEY,
    email TEXT,
    password TEXT,
    role TEXT DEFAULT 'client',
    status TEXT DEFAULT 'pending',
    full_name TEXT,
    phone TEXT,
    business_name TEXT,
    business_location TEXT,
    business_logo_url TEXT,
    business_latitude DOUBLE PRECISION,
    business_longitude DOUBLE PRECISION,
    business_type TEXT,
    business_description TEXT,
    previous_status TEXT,
    is_online BOOLEAN DEFAULT FALSE,
    last_seen TEXT,
    session_id TEXT,
    connection_count INTEGER DEFAULT 0,
    total_online_time INTEGER DEFAULT 0,
    language TEXT DEFAULT 'sw',
    preferred_theme TEXT DEFAULT 'white-professional',
    last_login TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE public.products (
    id UUID PRIMARY KEY,
    seller_id TEXT,
    name TEXT,
    description TEXT,
    category TEXT,
    price DOUBLE PRECISION,
    cost_price DOUBLE PRECISION,
    stock INTEGER,
    min_stock_level INTEGER DEFAULT 0,
    sku TEXT,
    barcode TEXT,
    unit TEXT,
    weight DOUBLE PRECISION,
    dimensions TEXT,
    image_url TEXT,
    gallery_urls TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    expected_selling_price DOUBLE PRECISION,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE public.sales (
    id UUID PRIMARY KEY,
    invoice_number TEXT,
    seller_id TEXT,
    customer_id TEXT,
    sale_date DATE,
    sale_time TIME,
    total_amount DOUBLE PRECISION,
    discount DOUBLE PRECISION DEFAULT 0,
    tax_amount DOUBLE PRECISION DEFAULT 0,
    payment_method TEXT,
    payment_status TEXT,
    notes TEXT,
    sale_type TEXT,
    shipping_address TEXT,
    shipping_cost DOUBLE PRECISION DEFAULT 0,
    delivery_status TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE public.sale_items (
    id UUID PRIMARY KEY,
    sale_id TEXT,
    product_id TEXT,
    quantity INTEGER,
    unit_price DOUBLE PRECISION,
    total_price DOUBLE PRECISION,
    item_discount DOUBLE PRECISION DEFAULT 0,
    discount_reason TEXT,
    notes TEXT,
    created_at TIMESTAMP
);

CREATE TABLE public.customers (
    id UUID PRIMARY KEY,
    seller_id TEXT,
    name TEXT,
    phone TEXT,
    email TEXT,
    address TEXT,
    customer_type TEXT,
    total_purchases DOUBLE PRECISION DEFAULT 0,
    purchases_count INTEGER DEFAULT 0,
    last_purchase_date TIMESTAMP,
    avg_purchase_amount DOUBLE PRECISION DEFAULT 0,
    loyalty_points INTEGER DEFAULT 0,
    credit_limit DOUBLE PRECISION DEFAULT 0,
    outstanding_balance DOUBLE PRECISION DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    tax_number TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE public.user_logs (
    id UUID PRIMARY KEY,
    user_id TEXT,
    action TEXT,
    endpoint TEXT,
    details TEXT,
    ip_address TEXT,
    status TEXT,
    created_at TIMESTAMP
);

CREATE TABLE public.password_reset_codes (
    id UUID PRIMARY KEY,
    user_id TEXT,
    email TEXT,
    reset_code TEXT,
    is_used BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    used_at TIMESTAMP
);

CREATE TABLE public.pending_emails (
    id UUID PRIMARY KEY,
    "to" TEXT,
    subject TEXT,
    content TEXT,
    status TEXT DEFAULT 'pending',
    error TEXT,
    created_at TIMESTAMP
);

CREATE TABLE public.matangazo (
    id UUID PRIMARY KEY,
    user_id TEXT,
    title TEXT,
    description TEXT,
    media_url TEXT,
    media_type TEXT DEFAULT 'image',
    thumbnail_url TEXT,
    is_free BOOLEAN DEFAULT TRUE,
    price_amount DOUBLE PRECISION,
    payment_status TEXT DEFAULT 'pending',
    order_tracking_id TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    starts_at TIMESTAMP,
    expires_at TIMESTAMP,
    category TEXT,
    tags TEXT,
    views_count INTEGER DEFAULT 0,
    clicks_count INTEGER DEFAULT 0,
    shares_count INTEGER DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE public.reactions (
    id UUID PRIMARY KEY,
    user_id TEXT,
    matangazo_id TEXT,
    reaction_type TEXT,
    report_reason TEXT,
    ip_address TEXT,
    user_agent TEXT,
    created_at TIMESTAMP
);

CREATE TABLE public.notifications (
    id UUID PRIMARY KEY,
    title TEXT,
    message TEXT,
    notification_type TEXT,
    recipients TEXT,
    sender_id TEXT,
    sender_name TEXT,
    sent_at TIMESTAMP,
    status TEXT,
    delivery_method TEXT,
    email_sent BOOLEAN DEFAULT FALSE,
    push_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE public.payments (
    id UUID PRIMARY KEY,
    user_id TEXT,
    business_name TEXT,
    order_tracking_id TEXT,
    merchant_reference TEXT,
    matangazo_id TEXT,
    description TEXT,
    amount DOUBLE PRECISION,
    amount_currency TEXT DEFAULT 'TZS',
    status TEXT DEFAULT 'pending',
    paid_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE public.user_presence_history (
    id UUID PRIMARY KEY,
    user_id TEXT,
    session_id TEXT,
    status TEXT,
    ip_address TEXT,
    user_agent TEXT,
    device_info TEXT,
    created_at TIMESTAMP
);

CREATE TABLE public.active_sessions (
    id UUID PRIMARY KEY,
    user_id TEXT,
    session_id TEXT,
    device_info TEXT,
    ip_address TEXT,
    user_agent TEXT,
    connected_at TIMESTAMP,
    last_activity_at TIMESTAMP,
    disconnected_at TIMESTAMP,
    duration_seconds INTEGER DEFAULT 0
);

CREATE TABLE public.ai_import_verifications (
    id UUID PRIMARY KEY,
    user_id TEXT,
    business_name TEXT,
    total_products INTEGER DEFAULT 0,
    duplicates_skipped INTEGER DEFAULT 0,
    new_products INTEGER DEFAULT 0,
    source_name TEXT,
    created_at TIMESTAMP
);

CREATE TABLE public.office_expenses (
    id UUID PRIMARY KEY,
    user_id TEXT,
    business_name TEXT,
    expense_date TEXT,
    amount DOUBLE PRECISION,
    description TEXT,
    category TEXT,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE public.sessions (
    id TEXT PRIMARY KEY,
    user_id TEXT,
    ip_address TEXT,
    user_agent TEXT,
    payload TEXT,
    last_activity INTEGER
);

CREATE TABLE public.activity_events (
    id UUID PRIMARY KEY,
    user_id TEXT,
    session_ref TEXT,
    correlation_id TEXT,
    category TEXT,
    event_name TEXT,
    description TEXT,
    page TEXT,
    route TEXT,
    element TEXT,
    entity_type TEXT,
    entity_id TEXT,
    source TEXT,
    outcome TEXT,
    severity TEXT DEFAULT 'INFO',
    severity_reason TEXT,
    is_security BOOLEAN DEFAULT FALSE,
    metadata TEXT,
    ip_address TEXT,
    user_agent TEXT,
    device TEXT,
    related_event_id TEXT,
    created_at TIMESTAMP
);

CREATE TABLE public.session_activity (
    id UUID PRIMARY KEY,
    user_id TEXT,
    session_ref TEXT UNIQUE,
    started_at TIMESTAMP,
    last_seen_at TIMESTAMP,
    expires_at TIMESTAMP,
    ended_at TIMESTAMP,
    status TEXT DEFAULT 'active',
    end_reason TEXT,
    ip_address TEXT,
    user_agent TEXT,
    device TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE public.security_alerts (
    id UUID PRIMARY KEY,
    rule TEXT,
    subject TEXT,
    severity TEXT,
    status TEXT DEFAULT 'open',
    summary TEXT,
    context TEXT,
    occurrence_count INTEGER DEFAULT 1,
    first_seen TIMESTAMP,
    last_seen TIMESTAMP,
    notes TEXT,
    acknowledged_by TEXT,
    acknowledged_at TIMESTAMP,
    resolved_by TEXT,
    resolved_at TIMESTAMP,
    resolution TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- The backup log: one row per backup (nightly or induced). This is the record
-- the Backups page reads and the row a delete removes after Cloudinary has
-- confirmed both files are gone.
CREATE TABLE public.backups (
    id BIGSERIAL PRIMARY KEY,
    backup_date DATE NOT NULL,
    kind TEXT DEFAULT 'automatic',
    stamp TEXT UNIQUE NOT NULL,
    folder TEXT NOT NULL,
    schema_url TEXT,
    schema_bytes BIGINT,
    seed_url TEXT,
    seed_bytes BIGINT,
    tables_count INTEGER DEFAULT 0,
    rows_count INTEGER DEFAULT 0,
    seconds NUMERIC(8, 2),
    status TEXT DEFAULT 'complete',
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Huduma (services): a sellable activity whose execution consumes existing
-- inventory products. Kept in parent-before-child order so a restore never
-- trips a foreign key.
CREATE TABLE public.services (
    id UUID PRIMARY KEY,
    business_id UUID,
    name TEXT,
    description TEXT,
    status TEXT DEFAULT 'active',
    created_by UUID,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE public.service_consumables (
    id UUID PRIMARY KEY,
    service_id UUID,
    product_id UUID,
    required_quantity NUMERIC(14, 3) DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Immutable service-sale history: names and prices are snapshots, so later
-- product price changes or service edits never rewrite past profit.
CREATE TABLE public.service_sales (
    id UUID PRIMARY KEY,
    invoice_number TEXT,
    business_id UUID,
    branch_id UUID,
    seller_id UUID,
    service_id UUID,
    service_name TEXT,
    service_quantity NUMERIC(14, 3) DEFAULT 1,
    selling_price NUMERIC(14, 2) DEFAULT 0,
    total_cost NUMERIC(14, 2) DEFAULT 0,
    profit NUMERIC(14, 2) DEFAULT 0,
    customer_id UUID,
    sale_date DATE,
    payment_method TEXT DEFAULT 'cash',
    status TEXT DEFAULT 'completed',
    notes TEXT,
    client_key TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE public.service_sale_items (
    id UUID PRIMARY KEY,
    service_sale_id UUID,
    product_id UUID,
    product_name TEXT,
    quantity NUMERIC(14, 3),
    buying_price NUMERIC(14, 2) DEFAULT 0,
    total_cost NUMERIC(14, 2) DEFAULT 0,
    created_at TIMESTAMP
);

-- ---------------------------------------------------------------------------
-- Indexes (kept outside the CREATE TABLE bodies: the backup service reads
-- columns from those bodies and must not see index syntax as a column).
-- ---------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS users_email_index ON public.users (email);
CREATE INDEX IF NOT EXISTS users_role_index ON public.users (role);
CREATE INDEX IF NOT EXISTS users_status_index ON public.users (status);
CREATE INDEX IF NOT EXISTS users_business_name_index ON public.users (business_name);
CREATE INDEX IF NOT EXISTS products_seller_id_index ON public.products (seller_id);
CREATE INDEX IF NOT EXISTS products_category_index ON public.products (category);
CREATE INDEX IF NOT EXISTS sales_seller_id_index ON public.sales (seller_id);
CREATE INDEX IF NOT EXISTS sales_sale_date_index ON public.sales (sale_date);
CREATE INDEX IF NOT EXISTS sale_items_sale_id_index ON public.sale_items (sale_id);
CREATE INDEX IF NOT EXISTS customers_seller_id_index ON public.customers (seller_id);
CREATE INDEX IF NOT EXISTS user_logs_user_id_index ON public.user_logs (user_id);
CREATE INDEX IF NOT EXISTS user_logs_created_at_index ON public.user_logs (created_at);
CREATE INDEX IF NOT EXISTS matangazo_user_id_index ON public.matangazo (user_id);
CREATE INDEX IF NOT EXISTS matangazo_payment_status_index ON public.matangazo (payment_status);
CREATE INDEX IF NOT EXISTS reactions_user_id_index ON public.reactions (user_id);
CREATE INDEX IF NOT EXISTS reactions_matangazo_id_index ON public.reactions (matangazo_id);
CREATE INDEX IF NOT EXISTS payments_user_id_index ON public.payments (user_id);
CREATE INDEX IF NOT EXISTS payments_order_tracking_id_index ON public.payments (order_tracking_id);
CREATE INDEX IF NOT EXISTS activity_events_user_id_index ON public.activity_events (user_id);
CREATE INDEX IF NOT EXISTS activity_events_created_at_index ON public.activity_events (created_at);
CREATE INDEX IF NOT EXISTS session_activity_user_id_index ON public.session_activity (user_id);
CREATE INDEX IF NOT EXISTS security_alerts_rule_index ON public.security_alerts (rule);
CREATE INDEX IF NOT EXISTS security_alerts_status_index ON public.security_alerts (status);
CREATE INDEX IF NOT EXISTS backups_backup_date_index ON public.backups (backup_date);
CREATE INDEX IF NOT EXISTS services_business_status_index ON public.services (business_id, status);
CREATE UNIQUE INDEX IF NOT EXISTS services_business_name_unique ON public.services (business_id, lower(btrim(name)));
CREATE INDEX IF NOT EXISTS service_consumables_product_index ON public.service_consumables (product_id);
CREATE INDEX IF NOT EXISTS service_sales_business_date_index ON public.service_sales (business_id, sale_date);
CREATE INDEX IF NOT EXISTS service_sales_seller_date_index ON public.service_sales (seller_id, sale_date);
CREATE INDEX IF NOT EXISTS service_sale_items_sale_index ON public.service_sale_items (service_sale_id);
