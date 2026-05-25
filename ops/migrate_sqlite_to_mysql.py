#!/usr/bin/env python3
"""
Migrasi data dari SQLite ke MySQL.

Menangani MySQL reserved words (key, value, order, dll.) dengan backtick quoting.
Aman untuk dijalankan ulang — akan TRUNCATE tabel target terlebih dahulu.
"""
import os
import sys

# Tambahkan src ke PYTHONPATH agar bisa mengimpor models
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..', 'src')))

from sqlalchemy import create_engine, text, inspect
from sqlalchemy.schema import MetaData

from app.db.models import Base


def get_engine_url(arg_name, env_name, default):
    for arg in sys.argv:
        if arg.startswith(f"--{arg_name}="):
            return arg.split("=", 1)[1]
    return os.environ.get(env_name, default)


def bt(name: str) -> str:
    """Wrap identifier dengan backtick untuk MySQL reserved word safety."""
    return f"`{name}`"


def migrate_data():
    sqlite_url = get_engine_url("sqlite-url", "SQLITE_URL", "sqlite:///../data/bot_jualan.db")
    mysql_url = get_engine_url("mysql-url", "MYSQL_URL", None)

    if not mysql_url:
        print("ERROR: MySQL URL is required. Provide via --mysql-url=... or MYSQL_URL env var.", file=sys.stderr)
        print("Example: --mysql-url=mysql+pymysql://user:pass@localhost:3306/bot_jualan", file=sys.stderr)
        sys.exit(1)

    print(f"Source SQLite: {sqlite_url}")
    print(f"Target MySQL : {mysql_url}")

    source_engine = create_engine(sqlite_url)
    target_engine = create_engine(mysql_url)

    # Pastikan struktur DB MySQL sudah terbuat berdasarkan Base metadata
    print("Creating tables in MySQL if they don't exist...")
    Base.metadata.create_all(target_engine)

    # Cek tabel mana saja yang ada di SQLite
    src_inspector = inspect(source_engine)
    src_tables = set(src_inspector.get_table_names())

    # Disable foreign key checks di MySQL sementara
    with target_engine.begin() as conn:
        conn.execute(text("SET FOREIGN_KEY_CHECKS = 0;"))

    try:
        with target_engine.begin() as target_conn:
            with source_engine.begin() as source_conn:
                for table in Base.metadata.sorted_tables:
                    tname = table.name

                    if tname not in src_tables:
                        print(f"Skipping table: {tname} (not found in SQLite)")
                        continue

                    print(f"Migrating table: {tname}...")

                    # Truncate target terlebih dahulu
                    target_count = target_conn.execute(
                        text(f"SELECT COUNT(*) FROM {bt(tname)}")
                    ).scalar()
                    if target_count > 0:
                        print(f"  - Table {tname} already has {target_count} rows. Truncating...")
                        target_conn.execute(text(f"TRUNCATE TABLE {bt(tname)}"))

                    # Fetch semua baris dari SQLite
                    rows = source_conn.execute(text(f'SELECT * FROM "{tname}"')).fetchall()
                    if not rows:
                        print(f"  - No rows to migrate for {tname}.")
                        continue

                    # Ambil nama kolom dari hasil query SQLite
                    src_columns = list(rows[0]._mapping.keys())

                    # Ambil nama kolom yang ada di target MySQL
                    tgt_inspector = inspect(target_engine)
                    tgt_columns = {c["name"] for c in tgt_inspector.get_columns(tname)}

                    # Hanya insert kolom yang ada di kedua sisi
                    columns = [c for c in src_columns if c in tgt_columns]
                    skipped = [c for c in src_columns if c not in tgt_columns]
                    if skipped:
                        print(f"  - Skipping columns not in MySQL: {skipped}")

                    # Buat INSERT statement dengan backtick-quoted column names
                    cols_bt = ", ".join(bt(c) for c in columns)
                    params = ", ".join(f":{c}" for c in columns)
                    insert_sql = f"INSERT INTO {bt(tname)} ({cols_bt}) VALUES ({params})"

                    batch_size = 200
                    for i in range(0, len(rows), batch_size):
                        batch = [{k: v for k, v in dict(r._mapping).items() if k in tgt_columns}
                                 for r in rows[i:i + batch_size]]
                        target_conn.execute(text(insert_sql), batch)

                    print(f"  - Migrated {len(rows)} rows to {tname}.")

        print("\n✅ Data migration completed successfully!")

    except Exception as e:
        print(f"\n❌ Migration failed: {e}", file=sys.stderr)
        sys.exit(1)

    finally:
        # Re-enable foreign key checks
        with target_engine.begin() as conn:
            conn.execute(text("SET FOREIGN_KEY_CHECKS = 1;"))


if __name__ == "__main__":
    migrate_data()
