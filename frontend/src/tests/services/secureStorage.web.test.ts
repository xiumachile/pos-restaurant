import { describe, it, expect, beforeEach } from "vitest";

import {
  getItem,
  setItem,
  removeItem,
  getItemSync,
  updateSyncCache,
  clearSyncCache,
} from "@/services/secureStorage";

describe("secureStorage", () => {
  beforeEach(() => {
    localStorage.clear();
    clearSyncCache();
  });

  describe("API async básica", () => {
    it("debería guardar y recuperar un valor", async () => {
      await setItem("test_key", "test_value");

      const value = await getItem("test_key");

      expect(value).toBe("test_value");
    });

    it("debería retornar null para keys inexistentes", async () => {
      const value = await getItem("non_existent");

      expect(value).toBeNull();
    });

    it("debería eliminar un valor", async () => {
      await setItem("to_remove", "value");

      const before = await getItem("to_remove");
      expect(before).toBe("value");

      await removeItem("to_remove");

      const after = await getItem("to_remove");
      expect(after).toBeNull();
    });

    it("debería sobreescribir valores", async () => {
      await setItem("key", "value1");
      await setItem("key", "value2");

      const value = await getItem("key");

      expect(value).toBe("value2");
    });
  });

  describe("syncCache básica", () => {
    it("debería retornar null cuando no hay valor", () => {
      expect(getItemSync("uncached")).toBeNull();
    });

    it("debería retornar valor desde cache", () => {
      updateSyncCache("cached_key", "cached_value");

      expect(getItemSync("cached_key")).toBe("cached_value");
    });

    it("debería limpiar la cache", () => {
      updateSyncCache("key1", "val1");
      updateSyncCache("key2", "val2");

      clearSyncCache();

      expect(getItemSync("key1")).toBeNull();
      expect(getItemSync("key2")).toBeNull();
    });
  });
});
