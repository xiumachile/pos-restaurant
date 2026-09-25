import Database from "@tauri-apps/plugin-sql";
import { writeMutex } from "./writeMutex";

export class LocalDatabase {
  private db: Database | null = null;
  private initPromise: Promise<Database> | null = null;

  async initialize(): Promise<Database> {
    if (this.db) return this.db;
    
    const db = await Database.load("sqlite:pos_local.db");
    
    await db.execute("PRAGMA journal_mode = WAL;");
    await db.execute("PRAGMA busy_timeout = 5000;");
    await db.execute("PRAGMA synchronous = NORMAL;");
    await db.execute("PRAGMA foreign_keys = ON;");
    
    this.db = db;
    console.log("[LocalDB] SQLite configurado con WAL y busy_timeout");
    return db;
  }

  async getConnection(): Promise<Database> {
    if (!this.db) {
      if (!this.initPromise) {
        this.initPromise = this.initialize();
      }
      this.db = await this.initPromise;
    }
    return this.db;
  }

  async execute(query: string, params?: unknown[]): Promise<any> {
    const db = await this.getConnection();
    
    // Detectar escrituras y serializarlas con mutex
    const isWrite = /\b(INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|BEGIN|COMMIT|ROLLBACK)\b/i.test(query);
    
    if (isWrite) {
      const release = await writeMutex.lock();
      try {
        return await db.execute(query, params as any);
      } finally {
        release();
      }
    }
    
    return await db.execute(query, params as any);
  }

  async select<T = any>(query: string, params?: unknown[]): Promise<T[]> {
    const db = await this.getConnection();
    return await db.select(query, params as any);
  }
}

export const localDb = new LocalDatabase();
