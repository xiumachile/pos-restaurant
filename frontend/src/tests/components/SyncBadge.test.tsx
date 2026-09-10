import { describe, it, expect } from "vitest";
import { render, screen } from "@testing-library/react";
import { SyncBadge, SyncErrorBox } from "@/components/system";

describe("SyncBadge", () => {
  it("muestra estado pendiente", () => {
    render(<SyncBadge status="pending" />);
    expect(screen.getByText("Pendiente")).toBeInTheDocument();
  });

  it("muestra estado sincronizando", () => {
    render(<SyncBadge status="syncing" />);
    expect(screen.getByText("Sincronizando")).toBeInTheDocument();
  });

  it("muestra estado sincronizado", () => {
    render(<SyncBadge status="synced" />);
    expect(screen.getByText("Sincronizado")).toBeInTheDocument();
  });

  it("muestra estado error", () => {
    render(<SyncBadge status="failed" />);
    expect(screen.getByText("Error")).toBeInTheDocument();
  });

  it("muestra cloud_id cuando showCloudId es true y status es synced", () => {
    render(
      <SyncBadge 
        status="synced" 
        cloudId="abc12345-6789" 
        showCloudId={true} 
      />
    );
    expect(screen.getByText("ID: abc12345...")).toBeInTheDocument();
  });

  it("no muestra cloud_id cuando status no es synced", () => {
    render(
      <SyncBadge 
        status="pending" 
        cloudId="abc12345-6789" 
        showCloudId={true} 
      />
    );
    expect(screen.queryByText(/ID:/)).not.toBeInTheDocument();
  });

  it("aplica variante compact", () => {
    const { container } = render(<SyncBadge status="pending" variant="compact" />);
    const badge = container.firstChild;
    expect(badge).toHaveClass("px-2", "py-1", "text-xs");
  });

  it("aplica variante normal por defecto", () => {
    const { container } = render(<SyncBadge status="pending" />);
    const badge = container.firstChild;
    expect(badge).toHaveClass("px-3", "py-2", "text-sm");
  });

  it("tiene atributos de accesibilidad", () => {
    render(<SyncBadge status="syncing" />);
    const badge = screen.getByRole("status");
    expect(badge).toHaveAttribute("aria-label", "Estado de sincronización: Sincronizando");
  });
});

describe("SyncErrorBox", () => {
  it("muestra mensaje de error", () => {
    render(<SyncErrorBox errorMessage="Network error" />);
    expect(screen.getByText("Network error")).toBeInTheDocument();
  });

  it("muestra título personalizado", () => {
    render(<SyncErrorBox errorMessage="Error" title="Falló la sincronización:" />);
    expect(screen.getByText("Falló la sincronización:")).toBeInTheDocument();
  });

  it("tiene atributos de accesibilidad", () => {
    render(<SyncErrorBox errorMessage="Error" />);
    const alert = screen.getByRole("alert");
    expect(alert).toHaveAttribute("aria-live", "assertive");
  });
});
