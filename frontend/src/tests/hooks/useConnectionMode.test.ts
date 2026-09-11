import { describe, it, expect, beforeEach } from "vitest";
import { renderHook } from "@testing-library/react";
import { useConnectionMode } from "@/hooks/useConnectionMode";
import { useSyncStore } from "@/store/useSyncStore";

describe("useConnectionMode", () => {
  beforeEach(() => {
    // Resetear estado del store
    useSyncStore.setState({
      status: "online",
      simulatedOffline: false,
    });
  });

  it("retorna false cuando está online y no simulado", () => {
    const { result } = renderHook(() => useConnectionMode());
    expect(result.current).toBe(false);
  });

  it("retorna true cuando status es 'offline'", () => {
    useSyncStore.setState({ status: "offline" });
    const { result } = renderHook(() => useConnectionMode());
    expect(result.current).toBe(true);
  });

  it("retorna true cuando simulatedOffline es true (incluso si status es 'online')", () => {
    useSyncStore.setState({
      status: "online",
      simulatedOffline: true,
    });
    const { result } = renderHook(() => useConnectionMode());
    expect(result.current).toBe(true);
  });

  it("retorna true cuando ambos (offline + simulado) están activos", () => {
    useSyncStore.setState({
      status: "offline",
      simulatedOffline: true,
    });
    const { result } = renderHook(() => useConnectionMode());
    expect(result.current).toBe(true);
  });

  it("reacciona a cambios de status", () => {
    const { result, rerender } = renderHook(() => useConnectionMode());
    
    expect(result.current).toBe(false);
    
    useSyncStore.setState({ status: "offline" });
    rerender();
    
    expect(result.current).toBe(true);
  });

  it("reacciona a cambios de simulatedOffline", () => {
    const { result, rerender } = renderHook(() => useConnectionMode());
    
    expect(result.current).toBe(false);
    
    useSyncStore.setState({ simulatedOffline: true });
    rerender();
    
    expect(result.current).toBe(true);
  });
});
