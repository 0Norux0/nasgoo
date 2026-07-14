# Getting Started — Phase 0 Verification

> ⚠️ **Setup (fresh clone) — Phase 6 v7.2 onwards**
>
> **One command does the whole setup:**
>
> ```bash
> php artisan marketplace:setup-demo
> ```
>
> It ensures `.env` exists, generates `APP_KEY` if missing, runs `optimize:clear`, runs `migrate:fresh --seed`, and prints the demo logins. Pass `--force` to skip every confirmation prompt (use this in CI/scripts).
>
> **Prefer the manual sequence?** Run these four commands in order:
>
> ```bash
> cp .env.example .env
> php artisan key:generate
> php artisan optimize:clear
> php artisan migrate:fresh --seed
> ```
>
> Phase 6 stores supplier integration credentials encrypted, so `APP_KEY` is mandatory. If it's missing, the seeder halts with a clear message pointing you at the guided command.
>
> The exact command is `php artisan migrate:fresh --seed` — **no trailing dot**. A trailing dot makes Laravel reject the command with `The "--seed." option does not exist.`

This guide is written for **non-developers**. No prior experience with PHP, Node, Docker, or the command line is needed. You'll verify that Phase 0 of the marketplace platform works correctly, all from your web browser.

You have **two equally valid options**. Pick whichever sounds easier:

| Option | What you do | What it tells you | Time |
|---|---|---|---|
| **A — GitHub Actions** (recommended) | Push the files to a GitHub repo and watch the automated tests run | Clear green ✓ or red ✗ for each Phase 0 check | ~10 min |
| **B — GitHub Codespaces** | Open the project in a browser-based VS Code with everything pre-installed | You see the actual website running | ~15 min |

**You only need to do one of them** to verify Phase 0. Option A is recommended because it's strictly automated — no clicks once you push.

---

## ⚙️ Prerequisites

You need:
1. A **free GitHub account** — sign up at https://github.com/signup if you don't have one
2. The Phase 0 ZIP file I delivered (`marketplace-phase-0-v1.2.zip`)
3. About 10–15 minutes

That's it. No software to install on your computer.

---

## 🅰️ Option A — Verify with GitHub Actions (recommended)

This is the simplest path. You upload the project to GitHub, GitHub's servers automatically test everything, and you see a clear pass/fail.

### Step A1 — Create a new private repository

1. Go to https://github.com/new
2. Set:
   - **Repository name**: `marketplace-platform` (or anything you like)
   - **Visibility**: **Private** (recommended — your code stays yours)
   - **Initialize this repository**: leave everything **unchecked** (no README, no .gitignore, no license)
3. Click **Create repository**

You'll land on an empty repository page. Keep this tab open.

### Step A2 — Upload the Phase 0 files

1. On the empty repository page, click **"uploading an existing file"** (it's a small link in the middle of the page).
2. **Unzip** `marketplace-phase-0-v1.2.zip` on your computer first (so you have a folder called `marketplace`).
3. **Drag the entire contents of the `marketplace` folder** (not the folder itself — its contents) into the GitHub upload area. This includes hidden files like `.github`, `.env.example`, `.eslintrc.cjs`, etc.

   > ⚠️ **Important — including hidden files:**
   > - On **Mac**: in Finder, press `Cmd + Shift + .` to make hidden files visible before dragging
   > - On **Windows**: in File Explorer, go to View → Show → Hidden items
   > - On **Linux**: in Files, press `Ctrl + H` to show hidden files
   >
   > Then select **everything** (Ctrl/Cmd + A) inside the marketplace folder and drag.

4. Scroll down. In the **Commit changes** section, type a short message like `phase 0 initial upload`.
5. Click **Commit changes**.

GitHub will upload all 89 files (this takes ~30 seconds).

### Step A3 — Generate the lock files (one click)

The project doesn't ship with `composer.lock` and `package-lock.json` because they have to be generated against the live npm/Packagist registries (I couldn't do that from my build environment). GitHub Actions will do this for you in 2 minutes.

1. At the top of your repository page, click the **Actions** tab.
2. If GitHub asks "I understand my workflows, go ahead and enable them" — click that button.
3. In the left sidebar, click **"Generate Lock Files"**.
4. On the right side, click the **"Run workflow"** dropdown button, then click the green **"Run workflow"** button.
5. Wait ~2 minutes. Refresh the page. You'll see a yellow circle (running) turn into a green ✓ (success).

When it finishes, your repository will have two new files: `composer.lock` and `package-lock.json`, committed automatically by the workflow.

### Step A4 — Verify Phase 0 (automatic)

The moment the "Generate Lock Files" workflow finished and committed those files, it triggered another workflow called **"Phase 0 Verification"** automatically.

1. Click the **Actions** tab again.
2. Click on the most recent **"Phase 0 Verification"** run (top of the list).
3. Wait for it to finish. **This takes about 5–8 minutes the first time** (it's downloading PHP, Node, Postgres, Redis, Docker images, and running all the tests).
4. When done, you'll see one of these:

#### ✅ All green checkmarks — Phase 0 PASSES

You'll see a summary at the top:

```
🎯 Phase 0 Verification Result
| Check                                    | Status  |
|------------------------------------------|---------|
| 🐘 Laravel (install + migrate + test)    | success |
| ⚛️ Frontend (lint + typecheck + build)   | success |
| 🐳 Docker image build                    | success |

✅ Phase 0 PASSES — ready to approve Phase 1
```

This proves all four of these commands work in a fresh setup:
- `make install` (the composer + migrate parts)
- `make test`
- `make lint`
- `make typecheck`

Plus the Docker production image builds correctly.

**You can now reply "approve Phase 1" to proceed.**

#### ❌ Any red X — Phase 0 fails

If anything failed:
1. Click on the failing job (the one with the red X)
2. Click on the failing step (red X next to a step name)
3. Expand the step — you'll see the exact error message
4. **Paste that error to me** and I'll fix it in the next version

---

## 🅱️ Option B — Verify with GitHub Codespaces

This option lets you actually **see the marketplace running** in your browser instead of just seeing test results.

### Step B1 — Upload files

Follow Steps A1, A2, A3 above (create repo, upload files, generate locks).

### Step B2 — Open a Codespace

1. Go to your repository page.
2. Click the green **"Code"** button.
3. Click the **"Codespaces"** tab.
4. Click **"Create codespace on main"**.

A browser-based VS Code editor opens. It takes about **2–3 minutes** to set up the environment the first time (downloading the universal Docker image).

> **Cost:** GitHub gives every user 60 free Codespace hours per month. Phase 0 verification uses about 1 hour. You can stop the codespace anytime by closing the tab — it pauses automatically after 30 minutes of inactivity.

### Step B3 — Run the platform

When the editor finishes loading, you'll see a terminal at the bottom (if not, press `` Ctrl + ` ``).

Type this command and press Enter:

```bash
make install
```

You'll see lots of output as it:
1. Starts PostgreSQL, Redis, Meilisearch, Mailpit, MinIO (Docker containers)
2. Installs all PHP dependencies (`composer install`)
3. Generates a security key
4. Runs database migrations
5. Creates the dev admin user
6. Installs frontend dependencies (`npm install`)

This takes **3–5 minutes** the first time. When it finishes, you'll see:

```
✓ Install complete.

  Storefront → http://localhost:8000
  Admin login → admin@marketplace.test / password
```

### Step B4 — See it working

Codespaces automatically forwards the ports. You'll see a notification in the bottom-right corner: **"Your application running on port 8000 is available."** Click **"Open in Browser"** (or press the link with the globe icon).

You should see the marketplace welcome page with:
- Four green health badges (PostgreSQL, Redis, Meilisearch, Storage)
- The "Locked Configuration" panel showing KWD / en,ar / guest browsing
- An "Open Admin Panel" button

Click around — try the **Arabic** button (page flips to right-to-left), try the admin panel (login with `admin@marketplace.test` / `password`).

### Step B5 — Run the test commands

Back in the terminal, run each of the four commands and confirm each says "passed":

```bash
make test       # runs the Pest test suite
make lint       # runs ESLint
make typecheck  # runs TypeScript compiler
```

Each one should finish without errors.

---

## 📋 Verification Checklist (for either option)

After running either option, you should be able to tick ALL of these. Note which (if any) fail:

- [ ] **A**: GitHub Actions "Generate Lock Files" workflow shows green ✓
- [ ] **A**: GitHub Actions "Phase 0 Verification" workflow shows green ✓ on all 4 jobs
- [ ] **A**: Summary says "Phase 0 PASSES — ready to approve Phase 1"
- [ ] **B**: `make install` completes without errors
- [ ] **B**: The welcome page loads in the browser with 4 green health badges
- [ ] **B**: The Arabic language button flips the page to RTL
- [ ] **B**: `/admin` login works with `admin@marketplace.test` / `password`
- [ ] **B**: `make test` exits 0 (passes)
- [ ] **B**: `make lint` exits 0 (passes)
- [ ] **B**: `make typecheck` exits 0 (passes)

---

## ❓ FAQ

### Why aren't `composer.lock` and `package-lock.json` included in the ZIP?

Lock files contain cryptographic integrity hashes (SHA-512) for every dependency, fetched from the live npm and Packagist registries. My build environment has no internet access to those registries, so I genuinely cannot create them. Fabricating fake lock files would be **worse** than omitting them — they'd cause integrity check failures.

GitHub Actions has internet access and runs `npm install` / `composer install` against the real registries, producing authentic lock files in 2 minutes. The "Generate Lock Files" workflow does exactly this and commits them back to your repo.

### What does Phase 0 actually verify?

Phase 0 is **the foundation only** — no marketplace business logic yet. It verifies:
- Laravel installs and boots
- PostgreSQL, Redis, Meilisearch are reachable
- The React + TypeScript frontend compiles
- Filament admin panel loads
- Tests run
- Production Docker image builds

It does **not** verify vendor management, products, orders, payments, etc. Those are Phases 1–10.

### How much will Codespaces cost me?

For personal accounts, GitHub gives **60 free Codespace-hours per month**, which renews monthly. Phase 0 uses about 1 hour total. You will not be charged unless you exceed 60 hours.

To be extra safe: after you finish verifying, go to https://github.com/codespaces and click the three-dot menu next to your codespace → "Delete codespace". This frees the storage.

### What if I don't want to use GitHub at all?

You can run everything locally on your computer instead — install Docker Desktop, unzip the project, and run `make install` in a terminal. But that requires installing Docker, which is the main thing the GitHub options let you avoid.

### What if "Phase 0 Verification" fails?

The CI summary will show exactly which job and which step failed. Click into it, expand the failing step, copy the error message, and paste it to me. I'll fix it in the next version of Phase 0 — we won't move to Phase 1 until verification is clean.

### Can I delete the repository after verification?

Yes, anytime. Settings → Danger Zone → Delete this repository. Everything you uploaded is gone.

---

## 📞 If You Get Stuck

At any point — whether something doesn't make sense, an error message looks unfamiliar, or you're not sure where to click — **just describe what you're seeing and I'll guide you through it**. Screenshots help.

The most common issues:
- **"I don't see the Actions tab"** — Make sure you're on the repository page, not your profile. The tabs are: Code, Issues, Pull requests, **Actions**, Projects, Wiki, Security, Insights, Settings.
- **"Run workflow button is missing"** — Click into a specific workflow first (in the left sidebar), then "Run workflow" appears on the right.
- **"My upload missed some files"** — Hidden files (starting with `.`) are easy to miss. The most important hidden directory is `.github` — without it, no CI runs. Re-enable hidden file visibility (see Step A2) and re-upload anything you missed.
