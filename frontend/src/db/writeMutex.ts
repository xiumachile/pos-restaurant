/**
 * Mutex global para serializar TODAS las escrituras en SQLite a nivel de aplicación.
 * Esto previene que cualquier módulo (PullEngine, SyncEngine, Repositories) 
 * colisione a nivel nativo con el LocalWriteCoordinator.
 */
export class Mutex {
  private queue: Promise<void> = Promise.resolve();

  async acquire(): Promise<() => void> {
    let release!: () => void;
    const nextPromise = new Promise<void>((resolve) => {
      release = resolve;
    });
    
    const currentQueue = this.queue;
    this.queue = currentQueue.then(() => nextPromise).catch(() => nextPromise);
    
    await currentQueue;
    return release;
  }
}

export const writeMutex = new Mutex();
