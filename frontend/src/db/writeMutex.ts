/**
 * Mutex robusto para serializar escrituras en SQLite.
 * Garantiza que solo una operación de escritura se ejecute a la vez a nivel de aplicación.
 */
export class Mutex {
  private mutex = Promise.resolve();

  async lock(): Promise<() => void> {
    let release: () => void = () => {};
    const nextMutex = new Promise<void>((resolve) => {
      release = resolve;
    });
    
    const currentMutex = this.mutex;
    this.mutex = nextMutex;
    
    await currentMutex;
    return release;
  }
}

export const writeMutex = new Mutex();
