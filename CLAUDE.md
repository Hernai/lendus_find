# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

LendusFind is a white-label **Loan Origination System (LOS) SaaS** for Mexican financial companies (SOFOMES). The system handles credit application onboarding, KYC validation, and integration with external financial systems via webhooks.

**Key Characteristics:**
- **Agnostic**: Only captures clients, validates identity (KYC), and originates credit applications - does NOT manage portfolio or payments
- **Integration First**: Sends standardized JSON via webhooks to external systems (SAP, Core Banking, Lendus, etc.)
- **White-Label**: Multiple tenants use the same installation with custom branding, colors, and subdomains

## Project Structure

```
LendusFind/
├── backend/              # Laravel 12 API (PHP 8.2+)
│   ├── app/
│   │   ├── Http/Controllers/
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Jobs/
│   │   └── Traits/
│   ├── database/migrations/
│   ├── routes/api.php
│   └── tests/
│
├── frontend/             # Vue.js 3 SPA (TypeScript)
│   ├── src/
│   │   ├── assets/
│   │   ├── components/
│   │   ├── composables/
│   │   ├── router/
│   │   ├── stores/       # Pinia stores
│   │   ├── views/
│   │   └── types/
│   ├── tailwind.config.js
│   └── vite.config.ts
│
├── requerimientos/       # Project specifications & wireframes
└── CLAUDE.md
```

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | PHP 8.2+ with Laravel 12 (RESTful API, Sanctum Auth) |
| Frontend | Vue.js 3 (Composition API + TypeScript) + Tailwind CSS |
| Database | PostgreSQL 15+ (with JSONB fields for flexibility) |
| Multitenancy | Single Database with Tenant Scoping (BelongsToTenant trait) |
| Cache/Queue | Redis |
| Storage | S3/MinIO for documents with signed URLs |
| OTP | Twilio or MessageBird for SMS/WhatsApp |

## Development Commands

### Backend (Laravel)

```bash
cd backend

# Install dependencies
composer install

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Start development server
php artisan serve

# Run tests
php artisan test

# Run single test
php artisan test --filter=TestClassName

# Queue worker
php artisan queue:work redis

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan route:clear
```

### Frontend (Vue.js)

```bash
cd frontend

# Install dependencies
npm install

# Start development server
npm run dev

# Build for production
npm run build

# Lint and format
npm run lint
npm run format

# Type check
npm run type-check
```

## Architecture

### Multitenancy

Tenant identification via subdomain (`tenant.losapp.com`) or `X-Tenant-Slug` header. All tenant-scoped models use the `BelongsToTenant` trait which:
- Auto-assigns `tenant_id` on creation
- Adds global scope to filter queries by current tenant

### Database Schema (Main Entities)

- **tenants**: Company configuration, branding (JSONB), webhook config
- **products**: Credit product types (SIMPLE, NOMINA, ARRENDAMIENTO, HIPOTECARIO, PYME) with rules and required docs as JSONB
- **users**: Authentication with roles (APPLICANT, ANALYST, ADMIN)
- **applicants**: Client data (PERSONA_FISICA or PERSONA_MORAL) with JSONB fields for personal_data, address, employment_info
- **applications**: Credit applications with status workflow (DRAFT → SUBMITTED → IN_REVIEW → DOCS_PENDING → APPROVED/REJECTED → SYNCED)
- **documents**: Uploaded files with OCR data, status tracking
- **references**: Personal/work references for applicants
- **audit_logs**: Activity tracking

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/config` | Tenant configuration and branding |
| POST | `/api/auth/otp/send` | Send OTP via SMS/WhatsApp/Email |
| POST | `/api/auth/otp/verify` | Verify OTP code |
| GET | `/api/products` | List available products |
| POST | `/api/simulator` | Calculate loan simulation |
| POST | `/api/applicants` | Create/update applicant |
| POST | `/api/applications` | Create application |
| POST | `/api/applications/{id}/submit` | Submit application |
| POST | `/api/documents` | Upload document |
| GET | `/api/postal-codes/{cp}` | Lookup neighborhoods by postal code |

## Mexican-Specific Validations

The system validates Mexican tax and identification formats:
- **RFC** (Registro Federal de Contribuyentes): 13 chars for individuals, 12 for companies
- **CURP** (Clave Única de Registro de Población): 18 chars with verification digit
- **Phone**: 10 digits
- **Postal Code**: 5 digits with SEPOMEX lookup

## Design Principles

- **Mobile-First**: Single-column layouts on mobile, multi-column on desktop
- **Conversational UI**: One main question per screen in the onboarding wizard
- **Thumb-First**: Action buttons in bottom sticky zone for mobile ergonomics
- **Progressive Disclosure**: Form fields revealed step by step (8-step wizard)
- **White-Label Theming**: CSS variables (`--tenant-primary`, etc.) for dynamic branding
