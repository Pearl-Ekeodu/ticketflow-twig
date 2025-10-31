# Railway Deployment Guide for Twig Version

## Quick Deploy to Railway (Free PHP Hosting)

### Option 1: Railway Web Dashboard (Easiest)

1. **Go to Railway**: https://railway.app
2. **Sign up/Login** with GitHub
3. **Click "New Project"**
4. **Select "Deploy from GitHub repo"**
5. **Choose your `ticketflow-twig` repository**
6. **Railway will automatically detect PHP** and configure it
7. **Your app will be live in ~2 minutes!**

### Option 2: Railway CLI (If you prefer CLI)

```bash
# Install Railway CLI
npm i -g @railway/cli

# Login
railway login

# Navigate to your twig-version directory
cd twig-version

# Initialize Railway project
railway init

# Deploy
railway up
```

### After Deployment

Railway will give you a URL like: `https://your-app-name.railway.app`

**Update your submission form** with this Railway URL for the Twig version.

---

## Alternative: Render.com (Also Free)

1. Go to https://render.com
2. Sign up with GitHub
3. Click "New +" → "Web Service"
4. Connect your `ticketflow-twig` repository
5. Configure:
   - **Name**: ticketflow-twig
   - **Root Directory**: `twig-version` (if repo is at root)
   - **Build Command**: `composer install`
   - **Start Command**: `cd public && php -S 0.0.0.0:$PORT`
   - **Environment**: PHP

6. Deploy!

---

**Note**: You can update your submission form - most forms allow edits. Just use the Railway/Render link instead of Vercel for the Twig version.


