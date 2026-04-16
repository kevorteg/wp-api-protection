# Landing Page Deployment Guide 🚀

## Frontend (Vercel)
1. Conectar repositorio de GitHub.
2. Root Directory: `landing/frontend`.
3. Framework Preset: `Vite`.
4. Environment Variable: `VITE_BACKEND_URL` con la URL de Deno Deploy.

## Backend (Deno Deploy)
1. Conectar repositorio de GitHub.
2. Root Directory: `landing/backend`.
3. Entrypoint: `server.ts`.
4. La base de datos Deno KV se activa automáticamente.

## Estructura
- `frontend/`: React + Vite + Vanilla CSS (Vite aesthetic).
- `backend/`: Deno + Deno KV + SSE (Real-time updates).
