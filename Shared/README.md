# Shared (Transversal)

Código compartido entre módulos según arquitectura v1.0.

## Contenido
- `Domain/Traits/`   → HasUuid, MultiTenant, Syncable, Auditable
- `Domain/Scopes/`   → CompanyScope, BranchScope (aislamiento multiempresa)
- `Domain/Contracts/` → Interfaces compartidas
- `Http/Middleware/`  → SetCompanyContext, SetBranchContext, ResolveLocale
- `Support/`          → Helpers (TranslationResolver, etc.)
