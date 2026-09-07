PRAGMA journal_mode=WAL;
PRAGMA foreign_keys = ON;
PRAGMA optimize;

create table if not exists delivery (
    id blob primary key,
    feed text,
    kind text,
    generated_at integer,
    updated_at integer
);

create table if not exists iocs (
    id blob primary key,
    delivery_id text,
    ioc text,
    ts integer,
    md5 blob,
    foreign key(delivery_id) references delivery(id)
);

create index if not exists idx_iocs_md5 on iocs(md5);
create index if not exists idx_iocs_delivery_id on iocs(delivery_id);

create table if not exists iocs_meta (
    id blob,
    ioc_id blob,
    primary key(id, ioc_id),
    foreign key(ioc_id) references iocs(id) on delete cascade
);

create table if not exists meta (
    id blob primary key,
    code text,
    main_code text,
    category text,
    payload json
);

begin;
drop view if exists v_ioc_meta;
create view v_ioc_meta as
    select d.kind, i.ioc, m.code
    from delivery d
    inner join iocs i on i.delivery_id = d.id
    inner join iocs_meta im on im.ioc_id = i.id
    inner join meta m on m.id = im.id  ;

commit;
