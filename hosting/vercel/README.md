# Vercel Deployment Configuration

This folder documents and archives the Vercel hosting setup for the **I-Record (OVPRE)** frontend application (`ovpre.vercel.app`).

## Architecture
- **Frontend Source Directory**: `frontend/`
- **Routing**: Handled by root `vercel.json`, which maps all incoming requests to the modular `frontend/` pages and `frontend/assets/`.
- **Database / API**: Connects directly from client-side JS to Supabase Auth & Storage API or prototype localStorage fallback.

## Vercel Dashboard Settings
* **Framework Preset**: Other (Static)
* **Root Directory**: `./` (Default)
* **Build Command**: None (Static deployment)
* **Output Directory**: Automatically routed via `vercel.json`

## Deployment Checklist
1. Ensure `vercel.json` at root is present and points to `/frontend/...`.
2. Push changes to GitHub repository:
   ```bash
   git add .
   git commit -m "Organize code into modular frontend, backend, and hosting structure"
   git push origin main
   ```
3. Vercel will automatically redeploy the site at `https://ovpre.vercel.app`.
