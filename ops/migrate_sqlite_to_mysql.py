#!/usr/bin/env python3
import os
import sys

# Tambahkan src ke PYTHONPATH agar bisa mengimpor models
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..', 'src')))

from sqlalchemy import create_engine, text
from sqlalchemy.orm import Session
from sqlalchemy.schema import MetaData

from app.db.models import Base

def get_engine_url(arg_name, env_name, default):
    for arg in sys.argv:
        if arg.startswith(f"--{arg_name}="):
            return arg.split("=", 1)[1]
    return os.environ.get(env_name, default)

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

    # Disable foreign key checks di MySQL sementara (karena SQLAlchemy tidak menjamin order of insertion secara detail)
    with target_engine.begin() as conn:
        conn.execute(text("SET FOREIGN_KEY_CHECKS = 0;"))

    try:
        source_meta = MetaData()
        source_meta.reflect(bind=source_engine)

        with target_engine.begin() as target_conn:
            with source_engine.begin() as source_conn:
                # Iterate melalui semua tabel dari model Base agar terurut (meskipun FK checks dinonaktifkan, ini practice baik)
                for table in Base.metadata.sorted_tables:
                    print(f"Migrating table: {table.name}...")
                    
                    # Cek jika tabel kosong di MySQL, jika tidak, kita bisa skip atau delete
                    target_count = target_conn.execute(text(f"SELECT COUNT(*) FROM {table.name}")).scalar()
                    if target_count > 0:
                        print(f"  - Table {table.name} in MySQL already has {target_count} rows. Emptying it first...")
                        target_conn.execute(text(f"TRUNCATE TABLE {table.name}"))
                    
                    # Fetch dari SQLite
                    rows = source_conn.execute(text(f"SELECT * FROM {table.name}")).fetchall()
                    if not rows:
                        print(f"  - No rows to migrate for {table.name}.")
                        continue
                    
                    # Ambil nama kolom
                    columns = rows[0]._mapping.keys()
                    
                    # Insert batch ke MySQL
                    insert_stmt = text(
                        f"INSERT INTO {table.name} ({', '.join(columns)}) "
                        f"VALUES ({', '.join([':' + c for c in columns])})"
                    )
                    
                    batch_size = 500
                    for i in range(0, len(rows), batch_size):
                        batch = [dict(r._mapping) for r in rows[i:i+batch_size]]
                        target_conn.execute(insert_stmt, batch)
                        
                    print(f"  - Migrated {len(rows)} rows to {table.name}.")

        print("Data migration completed successfully!")
    except Exception as e:
        print(f"Migration failed: {e}", file=sys.stderr)
        raise e
    finally:
        # Re-enable foreign key checks
        with target_engine.begin() as conn:
            conn.execute(text("SET FOREIGN_KEY_CHECKS = 1;"))
            
if __name__ == "__main__":
    migrate_data()
