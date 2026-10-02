-- =====================================================================
-- Customer inquiries (public "Contact Us" form)
-- =====================================================================
-- Stores every submission from the website Contact Us section. This is the
-- single source of truth shared by the website and the mobile Super Admin
-- Inquiry screen. Contact details live ONLY here and are never exposed
-- publicly.
--
-- status lifecycle:
--   new       -> just submitted, not seen by an admin
--   reviewed  -> an admin has opened / triaged it
--   published -> an admin published it as a public customer review
--                (the public copy lives in customer_reviews)
--   archived  -> closed / not needing action
-- =====================================================================

create table if not exists public.inquiries (
    id           uuid primary key default gen_random_uuid(),
    email        text        not null,
    phone        text        not null,
    subject      text        not null,
    message      text        not null,
    status       text        not null default 'new',
    source       text        not null default 'website',
    locale       text        not null default 'sw',
    ip_address   text,
    user_agent   text,
    reviewed_at  timestamptz,
    reviewed_by  text,
    published_at timestamptz,
    published_by text,
    created_at   timestamptz not null default now(),
    updated_at   timestamptz not null default now(),
    constraint inquiries_status_check
        check (status in ('new', 'reviewed', 'published', 'archived')),
    constraint inquiries_email_not_blank  check (btrim(email)   <> ''),
    constraint inquiries_phone_not_blank  check (btrim(phone)   <> ''),
    constraint inquiries_subject_not_blank check (btrim(subject) <> ''),
    constraint inquiries_message_not_blank check (btrim(message) <> '')
);

create index if not exists inquiries_status_idx       on public.inquiries (status);
create index if not exists inquiries_created_at_idx   on public.inquiries (created_at desc);
create index if not exists inquiries_email_lower_idx  on public.inquiries (lower(email));
create index if not exists inquiries_phone_idx        on public.inquiries (phone);

-- Report progress to the SQL editor.
select 'inquiries ready' as result;
