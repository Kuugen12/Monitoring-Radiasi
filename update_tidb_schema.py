import pymysql
import ssl

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

conn = pymysql.connect(
    host='gateway01.ap-southeast-1.prod.aws.tidbcloud.com',
    port=4000,
    user='4FxUazxpWaqzAS1.root',
    password='Dq37CUJZIRiMM4QG',
    database='magang',
    ssl={'ssl_mode': 'REQUIRED'}
)

with conn.cursor() as cur:
    cur.execute('SHOW COLUMNS FROM scan_matrix;')
    existing = [r[0] for r in cur.fetchall()]
    print('Existing columns:', existing)

    columns_to_add = [
        ('object_name', "VARCHAR(100) DEFAULT 'Gentong'"),
        ('session_id', "VARCHAR(64) DEFAULT NULL"),
        ('loop_index', "INT DEFAULT 1"),
        ('total_loops', "INT DEFAULT 1"),
        ('transition_delay', "FLOAT DEFAULT 1.0"),
    ]

    for col_name, col_type in columns_to_add:
        if col_name not in existing:
            print(f"Adding column {col_name}...")
            cur.execute(f"ALTER TABLE scan_matrix ADD COLUMN {col_name} {col_type};")
            print(f"Added {col_name}.")
        else:
            print(f"Column {col_name} already exists.")
    
    conn.commit()

with conn.cursor() as cur:
    cur.execute('SHOW COLUMNS FROM scan_matrix;')
    print('Final TiDB columns:', [r[0] for r in cur.fetchall()])

conn.close()

import os
import sqlite3
if os.path.exists('arraydata.db'):
    conn_sq = sqlite3.connect('arraydata.db')
    cur_sq = conn_sq.cursor()
    cur_sq.execute('PRAGMA table_info(scan_matrix);')
    sq_cols = [r[1] for r in cur_sq.fetchall()]
    print('Existing SQLite cols:', sq_cols)
    sqlite_cols = [
        ('object_name', "TEXT DEFAULT 'Gentong'"),
        ('session_id', "TEXT"),
        ('loop_index', "INTEGER DEFAULT 1"),
        ('total_loops', "INTEGER DEFAULT 1"),
        ('transition_delay', "REAL DEFAULT 1.0"),
    ]
    for c_name, c_def in sqlite_cols:
        if c_name not in sq_cols:
            cur_sq.execute(f"ALTER TABLE scan_matrix ADD COLUMN {c_name} {c_def};")
            print(f"Added {c_name} to SQLite.")
    conn_sq.commit()
    conn_sq.close()

